<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\StaffDevice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceRecordingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private array $deviceKeys = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->company = Company::create([
            'name' => 'Attendance Test Company',
            'slug' => 'attendance-test-company',
        ]);
        $this->company->officeLocations()->create([
            'office_name' => 'Main Office',
            'latitude' => 0,
            'longitude' => 0,
            'allowed_radius' => 100,
        ]);
        $user = $this->createCompanyUser();
        $this->registerTestDevice($user);
        Sanctum::actingAs($user);
    }

    public function test_check_in_records_arrival_without_checkout_and_dashboard_shows_in_progress(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 08:15:00', 'Africa/Dar_es_salaam'));

        $this->postJson(
            '/api/v1/attendance/check-in',
            $this->signedAttendancePayload('check-in')
        )->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attendance_state', 'in_progress')
            ->assertJsonPath('data.check_out_at', null);

        $attendance = Attendance::firstOrFail();
        $this->assertSame('08:15:00', $attendance->getRawOriginal('check_in_time'));
        $this->assertNull($attendance->getRawOriginal('check_out_time'));
        $this->assertNull($attendance->getRawOriginal('check_out_latitude'));
        $this->assertNull($attendance->getRawOriginal('check_out_longitude'));
        $this->assertNull($attendance->getRawOriginal('check_out_distance'));
        $this->assertNull($attendance->getRawOriginal('sign_out_location'));
        $this->assertSame('present', $attendance->status);
        $this->assertNull($attendance->work_duration);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('attendance_state', 'in_progress')
            ->assertJsonPath('is_checked_in', true)
            ->assertJsonPath('check_out_time', '--:-- --')
            ->assertJsonPath('worked_hours', '--');
    }

    public function test_check_out_updates_the_existing_attendance_and_duplicate_requests_are_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 08:15:00', 'Africa/Dar_es_salaam'));
        $user = auth()->user();
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'attendance_date' => '2026-10-17',
            'check_in_time' => '08:15:00',
            'latitude' => 0,
            'longitude' => 0,
            'distance' => 0,
            'status' => 'present',
        ]);
        $attendance->company_id = $this->company->id;
        $attendance->save();

        Carbon::setTestNow(Carbon::parse('2026-10-17 16:30:00', 'Africa/Dar_es_salaam'));
        $this->postJson(
            '/api/v1/attendance/check-out',
            $this->signedAttendancePayload('check-out')
        )->assertOk()
            ->assertJsonPath('data.attendance_state', 'completed')
            ->assertJsonPath('data.work_duration', '08h 15m');

        $this->assertSame(1, Attendance::count());
        $attendance = Attendance::firstOrFail();
        $this->assertSame('08:15:00', $attendance->getRawOriginal('check_in_time'));
        $this->assertSame('16:30:00', $attendance->getRawOriginal('check_out_time'));
        $this->assertEquals(0.0, (float) $attendance->getRawOriginal('check_out_latitude'));
        $this->assertEquals(0.0, (float) $attendance->getRawOriginal('check_out_longitude'));
        $this->assertSame('present', $attendance->status);
        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('attendance_state', 'completed')
            ->assertJsonPath('is_checked_in', false)
            ->assertJsonPath('worked_hours', '08h 15m')
            ->assertJsonPath('check_in_time', '08:15 AM')
            ->assertJsonPath('check_out_time', '04:30 PM');

        $this->postJson(
            '/api/v1/attendance/check-out',
            $this->signedAttendancePayload('check-out')
        )->assertConflict();

        $this->assertSame(1, Attendance::count());
        $this->assertSame('16:30:00', $attendance->fresh()->getRawOriginal('check_out_time'));
    }

    public function test_duplicate_check_in_is_rejected_and_out_of_geofence_check_in_is_not_recorded(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 08:15:00', 'Africa/Dar_es_salaam'));
        $this->postJson(
            '/api/v1/attendance/check-in',
            $this->signedAttendancePayload('check-in')
        )->assertCreated();
        $this->postJson(
            '/api/v1/attendance/check-in',
            $this->signedAttendancePayload('check-in')
        )->assertConflict();

        $this->assertSame(1, Attendance::count());
        $this->assertNull(Attendance::firstOrFail()->getRawOriginal('check_out_time'));

        $otherUser = $this->createCompanyUser();
        $this->registerTestDevice($otherUser);
        Sanctum::actingAs($otherUser);
        $this->postJson(
            '/api/v1/attendance/check-in',
            $this->signedAttendancePayload('check-in', 1, 1)
        )->assertForbidden();

        $this->assertSame(1, Attendance::count());
    }

    public function test_signed_attendance_rejects_modified_payload_replays_and_stale_locations(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 08:15:00', 'Africa/Dar_es_salaam'));
        $modified = $this->signedAttendancePayload('check-in');
        $modified['latitude'] = '0.0000001';
        $this->postJson('/api/v1/attendance/check-in', $modified)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('signature');
        $this->assertSame(0, Attendance::count());

        $payload = $this->signedAttendancePayload('check-in');
        $this->postJson('/api/v1/attendance/check-in', $payload)->assertCreated();
        $this->postJson('/api/v1/attendance/check-in', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge');
        $this->assertSame(1, Attendance::count());

        $stalePayload = $this->signedAttendancePayload('check-out');
        $stalePayload['location_timestamp'] = '2026-10-17T04:00:00.000Z';
        $this->postJson('/api/v1/attendance/check-out', $stalePayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('location_timestamp');
    }

    public function test_attendance_rejects_location_accuracy_over_thirty_metres(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 08:15:00', 'Africa/Dar_es_salaam'));
        $payload = $this->signedAttendancePayload('check-in');
        $payload['accuracy'] = '30.01';

        $this->postJson('/api/v1/attendance/check-in', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accuracy');
        $this->assertSame(0, Attendance::count());
    }

    public function test_attendance_history_is_paginated_newest_first_and_limited_to_the_authenticated_user(): void
    {
        $user = auth()->user();
        $otherUser = $this->createCompanyUser();

        foreach (range(1, 8) as $day) {
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'attendance_date' => Carbon::parse("2026-10-{$day}")->toDateString(),
                'check_in_time' => '08:15:00',
                'check_out_time' => $day === 8 ? null : '16:30:00',
                'latitude' => 0,
                'longitude' => 0,
                'distance' => 0,
                'sign_in_location' => $day === 8 ? null : 'Main Office',
                'sign_out_location' => $day === 8 ? null : 'Main Office',
                'status' => 'present',
            ]);
            $attendance->company_id = $this->company->id;
            $attendance->save();
        }
        $otherAttendance = Attendance::create([
            'user_id' => $otherUser->id,
            'attendance_date' => '2026-10-18',
            'check_in_time' => '09:00:00',
            'latitude' => 0,
            'longitude' => 0,
            'distance' => 0,
            'status' => 'present',
        ]);
        $otherAttendance->company_id = $this->company->id;
        $otherAttendance->save();

        $this->getJson('/api/v1/attendance/history')
            ->assertOk()
            ->assertJsonPath('data.current_page', 1)
            ->assertJsonPath('data.last_page', 2)
            ->assertJsonPath('data.per_page', 6)
            ->assertJsonPath('data.total', 8)
            ->assertJsonCount(6, 'data.records')
            ->assertJsonPath('data.records.0.attendance_date', '2026-10-08')
            ->assertJsonPath('data.records.0.check_in_time', '08:15 AM')
            ->assertJsonPath('data.records.0.check_out_time', null)
            ->assertJsonPath('data.records.0.sign_in_location', null)
            ->assertJsonPath('data.records.0.sign_out_location', null)
            ->assertJsonPath('data.records.5.attendance_date', '2026-10-03');

        $this->getJson('/api/v1/attendance/history?page=2')
            ->assertOk()
            ->assertJsonPath('data.current_page', 2)
            ->assertJsonPath('data.records.0.attendance_date', '2026-10-02')
            ->assertJsonPath('data.records.1.attendance_date', '2026-10-01')
            ->assertJsonCount(2, 'data.records');
    }

    public function test_attendance_history_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/attendance/history')->assertUnauthorized();
    }

    public function test_dashboard_statistics_sum_only_completed_attendance_for_the_authenticated_user(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 11:00:00', 'Africa/Dar_es_salaam'));
        $user = auth()->user();

        foreach ([
            ['2026-10-03', '08:00:00', '16:00:00'],
            ['2026-10-04', '08:30:00', '15:45:00'],
            ['2026-10-05', '09:00:00', null],
        ] as [$date, $checkIn, $checkOut]) {
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'attendance_date' => $date,
                'check_in_time' => $checkIn,
                'check_out_time' => $checkOut,
                'latitude' => 0,
                'longitude' => 0,
                'distance' => 0,
                'status' => 'present',
            ]);
            $attendance->company_id = $this->company->id;
            $attendance->save();
        }

        $otherUser = $this->createCompanyUser();
        $attendance = Attendance::create([
            'user_id' => $otherUser->id,
            'attendance_date' => '2026-10-06',
            'check_in_time' => '07:00:00',
            'check_out_time' => '18:00:00',
            'latitude' => 0,
            'longitude' => 0,
            'distance' => 0,
            'status' => 'present',
        ]);
        $attendance->company_id = $this->company->id;
        $attendance->save();

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('statistics.reporting_period.start_date', '2026-10-01')
            ->assertJsonPath('statistics.reporting_period.end_date', '2026-10-22')
            ->assertJsonPath('statistics.reporting_period.timezone', 'Africa/Dar_es_salaam')
            ->assertJsonPath('statistics.total_worked_minutes', 915)
            ->assertJsonPath('statistics.has_attendance_history', true)
            ->assertJsonPath('statistics.overtime_minutes', null)
            ->assertJsonPath('statistics.on_time_rate_percent', null)
            ->assertJsonPath('statistics.attendance_rate_percent', null)
            ->assertJsonPath('statistics.approved_leave_days', null)
            ->assertJsonPath('statistics.incomplete_days', null);
    }

    public function test_dashboard_statistics_returns_zero_hours_for_an_empty_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 11:00:00', 'Africa/Dar_es_salaam'));

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('statistics.total_worked_minutes', 0)
            ->assertJsonPath('statistics.has_attendance_history', false);
    }

    private function createCompanyUser(): User
    {
        $user = User::factory()->create(['role' => 'staff']);
        $user->company_id = $this->company->id;
        $user->save();

        return $user;
    }

    private function registerTestDevice(User $user): void
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        $this->assertNotFalse($privateKey);
        $details = openssl_pkey_get_details($privateKey);
        $this->assertNotFalse($details);
        $this->deviceKeys[$user->id] = $privateKey;

        StaffDevice::create([
            'user_id' => $user->id,
            'platform' => 'android',
            'public_key' => $details['key'],
            'public_key_fingerprint' => hash('sha256', $details['key']),
            'status' => 'active',
            'approved_at' => now(),
        ]);
    }

    private function signedAttendancePayload(
        string $operation,
        float $latitude = 0,
        float $longitude = 0
    ): array {
        /** @var User $user */
        $user = auth()->user();
        $device = StaffDevice::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();
        $challenge = $this->postJson('/api/v1/attendance/challenges', [
            'operation' => $operation,
        ])->assertCreated()->json();
        $accuracy = 5.0;
        $latitudeText = number_format($latitude, 7, '.', '');
        $longitudeText = number_format($longitude, 7, '.', '');
        $accuracyText = number_format($accuracy, 2, '.', '');
        $locationTimestamp = now('UTC')->format('Y-m-d\TH:i:s.v\Z');
        $message = implode("\n", [
            'attendance-v1',
            (string) $challenge['challenge_id'],
            $challenge['nonce'],
            (string) $user->id,
            (string) $device->id,
            $operation,
            $latitudeText,
            $longitudeText,
            $accuracyText,
            $locationTimestamp,
        ]);
        $this->assertTrue(openssl_sign(
            $message,
            $signature,
            $this->deviceKeys[$user->id],
            OPENSSL_ALGO_SHA256
        ));

        return [
            'latitude' => $latitudeText,
            'longitude' => $longitudeText,
            'accuracy' => $accuracyText,
            'challenge_id' => $challenge['challenge_id'],
            'device_id' => $device->id,
            'location_timestamp' => $locationTimestamp,
            'signature' => base64_encode($signature),
        ];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
