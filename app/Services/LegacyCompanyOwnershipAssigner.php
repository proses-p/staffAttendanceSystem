<?php

namespace App\Services;

use App\Models\Company;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LegacyCompanyOwnershipAssigner
{
    private const TABLES = [
        'users',
        'office_locations',
        'attendance_settings',
        'attendance_holidays',
        'staff_invitations',
        'staff_leave_records',
        'attendances',
    ];

    /**
     * @param  array{records: array<string, array<int, int>>, deferred: array<string, array<int, int>>}  $mapping
     * @return array<string, array{assigned: int, deferred: int}>
     */
    public function assign(
        int $companyId,
        array $mapping,
        string $operator,
        bool $apply
    ): array {
        $this->validateMapping($mapping, $operator);

        return DB::transaction(function () use ($companyId, $mapping, $operator, $apply): array {
            $company = Company::query()->lockForUpdate()->find($companyId);
            if (! $company || $company->status !== Company::STATUS_ACTIVE) {
                throw new DomainException('The target company does not exist or is not active.');
            }

            $selected = [];
            foreach (self::TABLES as $table) {
                $ids = $mapping['records'][$table];
                $rows = $ids === []
                    ? collect()
                    : DB::table($table)
                        ->whereIn('id', $ids)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                if ($rows->count() !== count($ids)) {
                    throw new DomainException("One or more mapped {$table} records do not exist.");
                }

                foreach ($rows as $row) {
                    if ($row->company_id !== null && (int) $row->company_id !== $companyId) {
                        throw new DomainException("A mapped {$table} record already belongs to another company.");
                    }
                }

                $selected[$table] = $rows->keyBy('id');
            }

            foreach (self::TABLES as $table) {
                $selectedNullIds = $selected[$table]
                    ->filter(fn (object $row): bool => $row->company_id === null)
                    ->keys()
                    ->map(fn ($id): int => (int) $id);
                $deferredIds = collect($mapping['deferred'][$table]);
                $currentNullIds = DB::table($table)
                    ->whereNull('company_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id);

                $expectedNullIds = $selectedNullIds
                    ->merge($deferredIds)
                    ->sort()
                    ->values();

                if ($expectedNullIds->unique()->count() !== $expectedNullIds->count()) {
                    throw new DomainException("The {$table} mapping assigns or defers the same ID more than once.");
                }

                if ($expectedNullIds->all() !== $currentNullIds->sort()->values()->all()) {
                    throw new DomainException("The {$table} mapping must explicitly assign or defer every currently unassigned record.");
                }
            }

            foreach ($selected['users'] as $user) {
                if ($user->role === 'super_admin') {
                    throw new DomainException('Super-admin accounts must remain outside company ownership assignments.');
                }
            }

            $plannedUserIds = $selected['users']->keys()->map(fn ($id): int => (int) $id);
            $referencedUserIds = collect()
                ->merge($plannedUserIds)
                ->merge($selected['attendances']->pluck('user_id')->map(fn ($id): int => (int) $id))
                ->merge($selected['staff_leave_records']->pluck('user_id')->map(fn ($id): int => (int) $id))
                ->merge($selected['staff_leave_records']->pluck('recorded_by')->filter()->map(fn ($id): int => (int) $id))
                ->unique()
                ->values();

            $referencedUsers = $referencedUserIds->isEmpty()
                ? collect()
                : DB::table('users')
                    ->whereIn('id', $referencedUserIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id', 'company_id', 'role'])
                    ->keyBy('id');

            $userAfterAssignment = function (int $userId) use (
                $plannedUserIds,
                $selected,
                $referencedUsers
            ): ?object {
                return $plannedUserIds->contains($userId)
                    ? $selected['users']->get($userId)
                    : $referencedUsers->get($userId);
            };

            $belongsToTargetAfterAssignment = function (int $userId) use (
                $companyId,
                $plannedUserIds,
                $userAfterAssignment
            ): bool {
                if ($plannedUserIds->contains($userId)) {
                    return true;
                }

                $user = $userAfterAssignment($userId);

                return $user && (int) $user->company_id === $companyId;
            };

            foreach ($selected['attendances'] as $attendance) {
                if (! $belongsToTargetAfterAssignment((int) $attendance->user_id)) {
                    throw new DomainException('Every mapped attendance must belong to a user explicitly assigned to the target company.');
                }
            }

            foreach ($selected['staff_leave_records'] as $leaveRecord) {
                $staffMember = $userAfterAssignment((int) $leaveRecord->user_id);
                if (
                    ! $staffMember ||
                    $staffMember->role !== 'staff' ||
                    ! $belongsToTargetAfterAssignment((int) $leaveRecord->user_id)
                ) {
                    throw new DomainException('Every mapped leave record must belong to a staff member assigned to the target company.');
                }

                if ($leaveRecord->recorded_by !== null) {
                    $recorder = $referencedUsers->get((int) $leaveRecord->recorded_by);
                    if (
                        ! $recorder ||
                        ($recorder->role !== 'super_admin' &&
                            ($recorder->role !== 'admin' || ! $belongsToTargetAfterAssignment((int) $recorder->id)))
                    ) {
                        throw new DomainException('A leave record recorder must be a target-company admin or a super-admin.');
                    }
                }
            }

            $this->validateUniqueCompanyPolicies($companyId, $selected);

            $changes = [];
            foreach (self::TABLES as $table) {
                $changes[$table] = [
                    'assigned' => 0,
                    'deferred' => count($mapping['deferred'][$table]),
                ];
                foreach ($selected[$table] as $row) {
                    if ($row->company_id !== null) {
                        continue;
                    }

                    $changes[$table]['assigned']++;
                    if ($apply) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->whereNull('company_id')
                            ->update(['company_id' => $companyId]);

                        DB::table('company_ownership_audits')->insert([
                            'company_id' => $companyId,
                            'record_type' => $table,
                            'record_id' => $row->id,
                            'operator' => $operator,
                            'created_at' => now(),
                        ]);
                    }
                }
            }

            return $changes;
        });
    }

    /**
     * @param  array{records: array<string, array<int, int>>, deferred: array<string, array<int, int>>}  $mapping
     */
    private function validateMapping(array $mapping, string $operator): void
    {
        if (
            array_diff(['records', 'deferred'], array_keys($mapping)) !== [] ||
            array_diff(array_keys($mapping), ['records', 'deferred']) !== [] ||
            ! is_array($mapping['records']) ||
            ! is_array($mapping['deferred'])
        ) {
            throw new InvalidArgumentException('The mapping must include explicit records and deferred sections.');
        }

        foreach (['records', 'deferred'] as $section) {
            if (
                array_diff(self::TABLES, array_keys($mapping[$section])) !== [] ||
                array_diff(array_keys($mapping[$section]), self::TABLES) !== []
            ) {
                throw new InvalidArgumentException("The {$section} section must explicitly include every supported record type and no others.");
            }
        }

        if (trim($operator) === '' || mb_strlen($operator) > 128) {
            throw new InvalidArgumentException('An operator identifier of 1 to 128 characters is required.');
        }

        foreach (['records', 'deferred'] as $section) {
            foreach (self::TABLES as $table) {
                if (! is_array($mapping[$section][$table])) {
                    throw new InvalidArgumentException("The {$section}.{$table} value must be an array of explicit record IDs.");
                }

                foreach ($mapping[$section][$table] as $id) {
                    if (! is_int($id) || $id < 1) {
                        throw new InvalidArgumentException("Every {$section}.{$table} ID must be a positive integer.");
                    }
                }

                if (count($mapping[$section][$table]) !== count(array_unique($mapping[$section][$table]))) {
                    throw new InvalidArgumentException("The {$section}.{$table} list contains duplicate IDs.");
                }
            }
        }

        foreach (self::TABLES as $table) {
            if (array_intersect($mapping['records'][$table], $mapping['deferred'][$table]) !== []) {
                throw new InvalidArgumentException("The {$table} list cannot both assign and defer the same ID.");
            }
        }
    }

    /**
     * @param  array<string, Collection<int, object>>  $selected
     */
    private function validateUniqueCompanyPolicies(int $companyId, array $selected): void
    {
        $selectedSettings = $selected['attendance_settings'];
        if ($selectedSettings->isNotEmpty()) {
            $otherSettings = DB::table('attendance_settings')
                ->where('company_id', $companyId)
                ->whereNotIn('id', $selectedSettings->keys())
                ->count();

            if ($otherSettings + $selectedSettings->count() > 1) {
                throw new DomainException('The mapping would assign more than one attendance settings record to this company.');
            }
        }

        $selectedHolidays = $selected['attendance_holidays'];
        if ($selectedHolidays->isEmpty()) {
            return;
        }

        $dates = $selectedHolidays->pluck('holiday_date')->unique()->values();
        if ($dates->count() !== $selectedHolidays->count()) {
            throw new DomainException('The mapping contains duplicate holiday dates for this company.');
        }

        $existingHoliday = DB::table('attendance_holidays')
            ->where('company_id', $companyId)
            ->whereIn('holiday_date', $dates)
            ->whereNotIn('id', $selectedHolidays->keys())
            ->exists();

        if ($existingHoliday) {
            throw new DomainException('The mapping would duplicate a holiday date already assigned to this company.');
        }
    }
}
