<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\FieldAssignment;
use App\Models\StaffDevice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FieldOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $staff;
    private array $keys;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Africa/Dar_es_salaam']);
        Carbon::setTestNow(Carbon::parse('2026-11-08 12:00:00', 'UTC'));
        $this->company = Company::create([
            'name' => 'Field Operations Test',
            'slug' => 'field-operations-test',
        ]);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->staff->company_id = $this->company->id;
        $this->staff->save();
        $this->keys = $this->keyPair();
        StaffDevice::create([
            'user_id' => $this->staff->id,
            'platform' => 'android',
            'public_key' => $this->keys['public'],
            'public_key_fingerprint' => hash('sha256', $this->keys['public']),
            'status' => 'active',
            'approved_at' => now(),
        ]);
        Sanctum::actingAs($this->staff);
    }

    public function test_batch_is_idempotent_and_keeps_the_captured_instant_in_app_timezone(): void
    {
        $uuid = fake()->uuid();
        $event = $this->clockInEvent($uuid, '2026-11-08T08:30:00.000Z');

        $this->sendBatch([$event])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'applied');
        $this->sendBatch([$event])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'duplicate');

        $this->assertSame(1, FieldAssignment::count());
        $assignment = FieldAssignment::firstOrFail();
        $this->assertSame('2026-11-08 11:30:00', $assignment->getRawOriginal('clock_in_at'));
        $this->assertSame('innovation_week', $assignment->task_type);
        $this->assertTrue($assignment->is_offline);
    }

    public function test_events_are_sorted_and_clock_out_closes_open_visits(): void
    {
        $clockIn = $this->clockInEvent(fake()->uuid(), '2026-11-08T10:00:00.000Z');
        $arrive = $this->locationEvent(
            fake()->uuid(),
            'visit_arrive',
            '2026-11-08T10:10:00.000Z',
            [
                'assignment_client_uuid' => $clockIn['client_uuid'],
                'client_name' => 'Client A',
            ]
        );
        $clockOut = $this->locationEvent(
            fake()->uuid(),
            'field_clock_out',
            '2026-11-08T11:00:00.000Z',
            ['assignment_client_uuid' => $clockIn['client_uuid']]
        );

        $this->sendBatch([$clockOut, $arrive, $clockIn])->assertOk()
            ->assertJsonPath('results.0.status', 'applied')
            ->assertJsonPath('results.1.status', 'applied')
            ->assertJsonPath('results.2.status', 'applied');

        $assignment = FieldAssignment::firstOrFail();
        $this->assertNotNull($assignment->clock_out_at);
        $this->assertNotNull($assignment->visits()->firstOrFail()->departed_at);
        $this->assertSame(0, Attendance::count());
    }

    public function test_overlapping_clock_in_is_rejected_without_stopping_other_events(): void
    {
        $first = $this->clockInEvent(fake()->uuid(), '2026-11-08T10:00:00.000Z');
        $second = $this->clockInEvent(fake()->uuid(), '2026-11-08T10:01:00.000Z');

        $response = $this->sendBatch([$second, $first])->assertOk();
        $this->assertSame('applied', $response->json('results.0.status'));
        $this->assertSame('rejected', $response->json('results.1.status'));
        $this->assertSame(1, FieldAssignment::count());
    }

    public function test_future_events_are_clamped_and_mock_locations_are_flagged_without_employee_disclosure(): void
    {
        $event = $this->clockInEvent(
            fake()->uuid(),
            '2026-11-08T12:10:00.000Z',
            ['is_mocked' => true]
        );
        $this->sendBatch([$event])->assertOk();

        $assignment = FieldAssignment::firstOrFail();
        $this->assertSame(now()->format('Y-m-d H:i:s'), $assignment->getRawOriginal('clock_in_at'));
        $this->assertTrue($assignment->is_flagged);
        $this->assertContains('future_timestamp_clamped', $assignment->flag_reasons);
        $this->assertContains('mock_location_enabled', $assignment->flag_reasons);
        $this->getJson('/api/v1/dashboard/employee')
            ->assertOk()
            ->assertJsonMissingPath('risk_score')
            ->assertJsonMissingPath('flag_reasons')
            ->assertJsonMissingPath('is_flagged');
    }

    public function test_events_older_than_limit_are_rejected(): void
    {
        $event = $this->clockInEvent(fake()->uuid(), '2026-11-04T11:00:00.000Z');
        $this->sendBatch([$event])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'rejected')
            ->assertJsonPath('results.0.message', 'Event is too old to accept. Contact your admin.');
        $this->assertSame(0, FieldAssignment::count());
    }

    public function test_admin_review_is_company_scoped_and_staff_cannot_access_admin_endpoints(): void
    {
        $event = $this->clockInEvent(fake()->uuid(), '2026-11-08T11:00:00.000Z', [
            'is_mocked' => true,
        ]);
        $this->sendBatch([$event])->assertOk();
        $assignment = FieldAssignment::firstOrFail();

        $this->getJson('/api/v1/admin/field/flagged')->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->company_id = $this->company->id;
        $admin->save();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/field/flagged')
            ->assertOk()
            ->assertJsonPath('data.0.assignment_id', $assignment->id)
            ->assertJsonPath('data.0.is_mock_location', true);
        $this->postJson("/api/v1/admin/field/{$assignment->id}/review")
            ->assertOk();
        $this->assertSame($admin->id, $assignment->fresh()->reviewed_by);
        $this->getJson('/api/v1/admin/field/flagged')->assertJsonCount(0, 'data');
    }

    private function clockInEvent(string $uuid, string $recordedAt, array $extras = []): array
    {
        return [
            'client_uuid' => $uuid,
            'type' => 'field_clock_in',
            'recorded_at' => $recordedAt,
            'device_time' => $recordedAt,
            'time_source' => 'trusted',
            'payload' => [
                ...$this->locationPayload($extras),
                'task_type' => 'innovation_week',
                'purpose_description' => 'Approved outreach',
            ],
        ];
    }

    private function locationEvent(
        string $uuid,
        string $type,
        string $recordedAt,
        array $extraPayload = []
    ): array {
        return [
            'client_uuid' => $uuid,
            'type' => $type,
            'recorded_at' => $recordedAt,
            'device_time' => $recordedAt,
            'time_source' => 'trusted',
            'payload' => [...$this->locationPayload(), ...$extraPayload],
        ];
    }

    private function locationPayload(array $extras = []): array
    {
        return [
            'latitude' => -6.8,
            'longitude' => 39.2,
            'accuracy' => 8,
            'captured_at' => '2026-11-08T11:59:00.000Z',
            'is_mocked' => false,
            'is_rooted' => false,
            'is_emulator' => false,
            ...$extras,
        ];
    }

    private function sendBatch(array $events)
    {
        $challenge = $this->postJson('/api/v1/sync/challenge', [])->assertCreated()->json();
        $eventsJson = json_encode($events, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hash = hash('sha256', $eventsJson);
        $message = implode("\n", [
            'field-sync-v1',
            (string) $challenge['challenge_id'],
            $challenge['nonce'],
            (string) $this->staff->id,
            (string) $challenge['device_id'],
            $hash,
        ]);
        $signature = '';
        $this->assertTrue(openssl_sign($message, $signature, $this->keys['private'], OPENSSL_ALGO_SHA256));

        return $this->postJson('/api/v1/sync/batch', [
            'events' => $events,
            'events_json' => $eventsJson,
            'challenge_id' => $challenge['challenge_id'],
            'device_id' => $challenge['device_id'],
            'payload_hash' => $hash,
            'signature' => base64_encode($signature),
        ]);
    }

    private function keyPair(): array
    {
        $private = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        $this->assertNotFalse($private);
        $details = openssl_pkey_get_details($private);
        $this->assertNotFalse($details);

        return ['private' => $private, 'public' => $details['key']];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
