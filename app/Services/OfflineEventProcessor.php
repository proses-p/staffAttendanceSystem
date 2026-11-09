<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClientVisit;
use App\Models\FieldAssignment;
use App\Models\ProcessedEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class OfflineEventProcessor
{
    public function __construct(
        private readonly LocationRiskService $risk,
        private readonly VelocityService $velocity,
    ) {
    }

    public function process(User $user, array $events, Request $request): array
    {
        usort($events, fn (array $a, array $b): int => strcmp(
            Carbon::parse($a['recorded_at'])->utc()->toIso8601String(),
            Carbon::parse($b['recorded_at'])->utc()->toIso8601String()
        ));

        $results = [];
        foreach ($events as $event) {
            $results[] = $this->processOne($user, $event, $request);
        }

        return $results;
    }

    private function processOne(User $user, array $event, Request $request): array
    {
        try {
            return DB::transaction(function () use ($user, $event, $request): array {
                if (ProcessedEvent::query()->where('client_uuid', $event['client_uuid'])->exists()) {
                    return ['client_uuid' => $event['client_uuid'], 'status' => 'duplicate'];
                }

                $payload = $this->validatePayload($event['type'], $event['payload']);
                [$at, $deviceTime, $timeScore, $timeReasons] = $this->resolveTimes($event);
                $isOffline = abs(now()->getTimestamp() - $at->getTimestamp()) > 120;
                $location = [
                    ...$payload,
                    'skip_stale_check' => $isOffline,
                    'skip_ip_check' => $isOffline,
                ];
                [$riskScore, $reasons, $isMock] = $this->risk->assess($location, $request);
                $riskScore = min(100, $riskScore + $timeScore);
                $reasons = array_values(array_unique([...$reasons, ...$timeReasons]));

                $this->applyEvent(
                    $user,
                    $event,
                    $payload,
                    $at,
                    $deviceTime,
                    $isOffline,
                    $riskScore,
                    $reasons,
                    $isMock,
                    $request
                );

                ProcessedEvent::create([
                    'client_uuid' => $event['client_uuid'],
                    'user_id' => $user->id,
                    'type' => $event['type'],
                ]);

                return ['client_uuid' => $event['client_uuid'], 'status' => 'applied'];
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueConflict($exception)) {
                return ['client_uuid' => $event['client_uuid'], 'status' => 'duplicate'];
            }

            report($exception);

            return [
                'client_uuid' => $event['client_uuid'],
                'status' => 'rejected',
                'message' => 'Unable to process this event.',
            ];
        } catch (ValidationException $exception) {
            return [
                'client_uuid' => $event['client_uuid'],
                'status' => 'rejected',
                'message' => collect($exception->errors())->flatten()->first() ?? 'The event is invalid.',
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'client_uuid' => $event['client_uuid'],
                'status' => 'rejected',
                'message' => 'Unable to process this event.',
            ];
        }
    }

    private function validatePayload(string $type, array $payload): array
    {
        $rules = [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'captured_at' => ['nullable', 'date'],
            'is_mocked' => ['sometimes', 'boolean'],
            'is_rooted' => ['sometimes', 'boolean'],
            'is_emulator' => ['sometimes', 'boolean'],
        ];

        if ($type === 'field_clock_in') {
            $rules += [
                'task_type' => ['required', 'in:marketing,research,client_meeting,innovation_week'],
                'purpose_description' => ['nullable', 'string', 'max:1000'],
            ];
        } elseif ($type === 'visit_arrive') {
            $rules += [
                'assignment_client_uuid' => ['required', 'uuid'],
                'client_name' => ['required', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ];
        } elseif ($type === 'visit_depart') {
            $rules += [
                'visit_client_uuid' => ['required', 'uuid'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ];
        } else {
            $rules += [
                'assignment_client_uuid' => ['required', 'uuid'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ];
        }

        $validator = Validator::make($payload, $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'payload' => [$validator->errors()->first()],
            ]);
        }

        return $validator->validated();
    }

    private function resolveTimes(array $event): array
    {
        $timezone = config('app.timezone');
        $recorded = Carbon::parse($event['recorded_at'])->setTimezone($timezone);
        $deviceTime = Carbon::parse($event['device_time'])->setTimezone($timezone);
        $score = 0;
        $reasons = [];
        $now = now($timezone);

        if ($recorded->gt($now->copy()->addMinutes(2))) {
            $recorded = $now;
            $score += 60;
            $reasons[] = 'future_timestamp_clamped';
        }
        if ($recorded->lt($now->copy()->subHours((int) config('attendance.max_offline_hours')))) {
            throw ValidationException::withMessages([
                'recorded_at' => ['Event is too old to accept. Contact your admin.'],
            ]);
        }
        if ($event['time_source'] === 'device') {
            $score += 30;
            $reasons[] = 'untrusted_device_clock';
        } elseif (abs($recorded->diffInSeconds($deviceTime)) > (int) config('attendance.max_clock_drift_s')) {
            $score += 40;
            $reasons[] = 'device_clock_drift';
        }

        return [$recorded, $deviceTime, $score, $reasons];
    }

    private function applyEvent(
        User $user,
        array $event,
        array $payload,
        Carbon $at,
        Carbon $deviceTime,
        bool $isOffline,
        int $score,
        array $reasons,
        bool $isMock,
        Request $request
    ): void {
        $base = [
            'is_offline' => $isOffline,
            'time_source' => $event['time_source'],
            'synced_at' => now(),
        ];

        if ($event['type'] === 'field_clock_in') {
            $overlap = FieldAssignment::query()
                ->where('user_id', $user->id)
                ->where(function ($query) use ($at) {
                    $query->whereNull('clock_out_at')->orWhere('clock_out_at', '>', $at);
                })
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages([
                    'assignment' => ['A field assignment already overlaps this time.'],
                ]);
            }

            if ($this->overlapsOfficeAttendance($user, $at)) {
                $score = min(100, $score + 30);
                $reasons[] = 'overlaps_office_session';
            }

            FieldAssignment::create([
                ...$base,
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'task_type' => $payload['task_type'],
                'purpose_description' => $payload['purpose_description'] ?? null,
                'clock_in_at' => $at,
                'clock_in_lat' => $payload['latitude'],
                'clock_in_lng' => $payload['longitude'],
                'last_lat' => $payload['latitude'],
                'last_lng' => $payload['longitude'],
                'last_location_at' => $at,
                'gps_accuracy' => $payload['accuracy'] ?? null,
                'device_uuid' => $request->header('X-Device-UUID'),
                'is_mock_location' => $isMock,
                'is_flagged' => $score >= (int) config('attendance.flag_threshold'),
                'flag_reasons' => array_values(array_unique($reasons)),
                'risk_score' => min(100, $score),
                'client_uuid' => $event['client_uuid'],
                'client_device_time' => $deviceTime,
            ]);

            return;
        }

        if ($event['type'] === 'visit_arrive') {
            $assignment = FieldAssignment::query()
                ->where('user_id', $user->id)
                ->where('client_uuid', $payload['assignment_client_uuid'])
                ->first();
            if (! $assignment || $at->lt($assignment->clock_in_at) || $assignment->clock_out_at) {
                throw ValidationException::withMessages([
                    'assignment' => ['The field assignment is missing, closed, or begins after this visit.'],
                ]);
            }
            if (ClientVisit::query()->where('user_id', $user->id)->whereNull('departed_at')->exists()) {
                throw ValidationException::withMessages([
                    'visit' => ['Depart from the open client visit before arriving at another client.'],
                ]);
            }

            $velocity = $this->velocity->check(
                $user->id,
                (float) $payload['latitude'],
                (float) $payload['longitude'],
                $at
            );
            $flagged = $velocity !== null;
            if ($velocity !== null) {
                $assignment->is_flagged = true;
                $assignment->risk_score = min(100, $assignment->risk_score + 60);
                $assignment->flag_reasons = array_values(array_unique([
                    ...($assignment->flag_reasons ?? []),
                    $velocity,
                ]));
            }

            ClientVisit::create([
                ...$base,
                'field_assignment_id' => $assignment->id,
                'user_id' => $user->id,
                'client_name' => $payload['client_name'],
                'notes' => $payload['notes'] ?? null,
                'arrived_at' => $at,
                'arrived_lat' => $payload['latitude'],
                'arrived_lng' => $payload['longitude'],
                'arrived_accuracy' => $payload['accuracy'] ?? null,
                'is_flagged' => $flagged,
                'client_uuid' => $event['client_uuid'],
            ]);
            $assignment->update([
                'last_lat' => $payload['latitude'],
                'last_lng' => $payload['longitude'],
                'last_location_at' => $at,
                'gps_accuracy' => $payload['accuracy'] ?? null,
            ]);

            return;
        }

        if ($event['type'] === 'visit_depart') {
            $visit = ClientVisit::query()
                ->where('user_id', $user->id)
                ->where('client_uuid', $payload['visit_client_uuid'])
                ->whereNull('departed_at')
                ->first();
            if (! $visit) {
                throw ValidationException::withMessages([
                    'visit' => ['The client visit is missing or already closed.'],
                ]);
            }
            $departedAt = $at->lt($visit->arrived_at) ? $visit->arrived_at : $at;
            $visit->update([
                'departed_at' => $departedAt,
                'departed_lat' => $payload['latitude'],
                'departed_lng' => $payload['longitude'],
                'notes' => $payload['notes'] ?? $visit->notes,
                ...$base,
            ]);
            $visit->assignment()->update([
                'last_lat' => $payload['latitude'],
                'last_lng' => $payload['longitude'],
                'last_location_at' => $departedAt,
            ]);

            return;
        }

        $assignment = FieldAssignment::query()
            ->where('user_id', $user->id)
            ->where('client_uuid', $payload['assignment_client_uuid'])
            ->whereNull('clock_out_at')
            ->first();
        if (! $assignment || $at->lt($assignment->clock_in_at)) {
            throw ValidationException::withMessages([
                'assignment' => ['The field assignment is missing, closed, or clock-out precedes clock-in.'],
            ]);
        }

        $openVisits = ClientVisit::query()
            ->where('field_assignment_id', $assignment->id)
            ->whereNull('departed_at')
            ->get();
        foreach ($openVisits as $visit) {
            $visit->update([
                'departed_at' => $at->lt($visit->arrived_at) ? $visit->arrived_at : $at,
                'departed_lat' => $payload['latitude'],
                'departed_lng' => $payload['longitude'],
                ...$base,
            ]);
        }
        $assignment->update([
            'clock_out_at' => $at,
            'clock_out_lat' => $payload['latitude'],
            'clock_out_lng' => $payload['longitude'],
            'last_lat' => $payload['latitude'],
            'last_lng' => $payload['longitude'],
            'last_location_at' => $at,
            'gps_accuracy' => $payload['accuracy'] ?? null,
        ]);
    }

    private function overlapsOfficeAttendance(User $user, Carbon $at): bool
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->whereDate('attendance_date', $at->toDateString())
            ->get()
            ->contains(function (Attendance $attendance) use ($at): bool {
                $start = $attendance->check_in_at;
                $end = $attendance->check_out_at;

                return $start && $at->gte($start) && (! $end || $at->lte($end));
            });
    }

    private function isUniqueConflict(Throwable $exception): bool
    {
        if (! $exception instanceof QueryException) {
            return false;
        }

        $state = $exception->errorInfo[0] ?? null;

        return in_array($state, ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique constraint');
    }
}
