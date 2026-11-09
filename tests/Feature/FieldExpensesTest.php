<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FieldAssignment;
use App\Models\FieldExpense;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FieldExpensesTest extends TestCase
{
    private Company $company;

    private User $staff;

    private FieldAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createExpenseTestSchema();
        Storage::fake(FieldExpense::RECEIPT_DISK);

        $this->company = Company::create([
            'name' => 'Expense Test Company',
            'slug' => 'expense-test-company',
        ]);
        $this->staff = User::factory()->create([
            'role' => 'staff',
            'company_id' => $this->company->id,
        ]);
        $this->assignment = $this->makeAssignment($this->staff);
        Sanctum::actingAs($this->staff);
    }

    public function test_staff_can_submit_an_expense_for_an_open_assignment(): void
    {
        $this->postJson('/api/v1/field/expenses', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('expense.field_assignment_id', $this->assignment->id)
            ->assertJsonPath('expense.currency', 'TZS')
            ->assertJsonPath('expense.status', 'pending')
            ->assertJsonPath('expense.receipt_available', false);

        $this->assertDatabaseHas('field_expenses', [
            'field_assignment_id' => $this->assignment->id,
            'user_id' => $this->staff->id,
            'status' => 'pending',
        ]);
    }

    public function test_multiple_expenses_can_belong_to_one_assignment(): void
    {
        $this->postJson('/api/v1/field/expenses', $this->validPayload())
            ->assertCreated();
        $this->postJson('/api/v1/field/expenses', [
            ...$this->validPayload(),
            'category' => 'meals_refreshments',
            'amount' => '12000.00',
        ])->assertCreated();

        $this->assertSame(2, $this->assignment->expenses()->count());
    }

    public function test_staff_cannot_submit_an_expense_for_another_employees_assignment(): void
    {
        $otherStaff = User::factory()->create([
            'role' => 'staff',
            'company_id' => $this->company->id,
        ]);
        $otherAssignment = $this->makeAssignment($otherStaff);

        $this->postJson('/api/v1/field/expenses', $this->validPayload([
            'field_assignment_id' => $otherAssignment->id,
        ]))->assertForbidden();

        $this->assertDatabaseCount('field_expenses', 0);
    }

    public function test_invalid_expense_category_is_rejected(): void
    {
        $this->postJson('/api/v1/field/expenses', $this->validPayload([
            'category' => 'office_supplies',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('category');
    }

    public function test_zero_and_negative_amounts_are_rejected(): void
    {
        foreach (['0', '-1.00'] as $amount) {
            $this->postJson('/api/v1/field/expenses', $this->validPayload([
                'amount' => $amount,
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors('amount');
        }

        $this->assertDatabaseCount('field_expenses', 0);
    }

    public function test_invalid_receipt_file_is_rejected(): void
    {
        $this->post('/api/v1/field/expenses', [
            ...$this->validPayload(),
            'receipt' => UploadedFile::fake()->createWithContent(
                'receipt.txt',
                'not an image'
            ),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('receipt');
    }

    public function test_valid_receipt_is_stored_on_the_private_disk(): void
    {
        $this->post('/api/v1/field/expenses', [
            ...$this->validPayload(),
            'receipt' => $this->pngReceipt(),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('expense.receipt_available', true)
            ->assertJsonPath(
                'expense.receipt_endpoint',
                '/api/v1/field/expenses/1/receipt'
            )
            ->assertJsonMissingPath('expense.receipt_image_path');

        $expense = FieldExpense::query()->firstOrFail();
        $this->assertNotSame('receipt.png', basename($expense->receipt_image_path));
        Storage::disk(FieldExpense::RECEIPT_DISK)->assertExists($expense->receipt_image_path);
    }

    public function test_expense_owner_can_download_receipt_through_the_authorized_endpoint(): void
    {
        $expense = $this->createExpenseFor($this->staff, $this->assignment, true);

        $this->get("/api/v1/field/expenses/{$expense->id}/receipt")
            ->assertOk();
    }

    public function test_duplicate_client_uuid_returns_existing_expense_without_creating_another(): void
    {
        $payload = $this->validPayload(['client_uuid' => fake()->uuid()]);

        $first = $this->postJson('/api/v1/field/expenses', $payload)
            ->assertCreated()
            ->assertJsonPath('duplicate', false);
        $second = $this->postJson('/api/v1/field/expenses', $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame($first->json('expense.id'), $second->json('expense.id'));
        $this->assertDatabaseCount('field_expenses', 1);
    }

    public function test_employee_cannot_access_another_employees_expense(): void
    {
        $expense = $this->createExpenseFor($this->staff, $this->assignment);
        $otherStaff = User::factory()->create([
            'role' => 'staff',
            'company_id' => $this->company->id,
        ]);
        Sanctum::actingAs($otherStaff);

        $this->getJson("/api/v1/field/expenses/{$expense->id}")
            ->assertNotFound();
    }

    public function test_employee_cannot_access_another_employees_receipt(): void
    {
        $expense = $this->createExpenseFor($this->staff, $this->assignment, true);
        $otherStaff = User::factory()->create([
            'role' => 'staff',
            'company_id' => $this->company->id,
        ]);
        Sanctum::actingAs($otherStaff);

        $this->get("/api/v1/field/expenses/{$expense->id}/receipt")
            ->assertNotFound();
    }

    public function test_same_company_admin_can_access_an_expense(): void
    {
        $expense = $this->createExpenseFor($this->staff, $this->assignment);
        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/field/expenses/{$expense->id}")
            ->assertOk()
            ->assertJsonPath('expense.id', $expense->id);
    }

    public function test_admin_and_manager_can_view_company_expenses_but_staff_cannot(): void
    {
        $expense = $this->createExpenseFor($this->staff, $this->assignment);
        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/field-expenses?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.id', $expense->id)
            ->assertJsonPath('data.0.employee.name', $this->staff->name)
            ->assertJsonPath('data.0.field_assignment.id', $this->assignment->id);

        $manager = User::factory()->create([
            'role' => 'manager',
            'company_id' => $this->company->id,
        ]);
        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/admin/field-expenses')
            ->assertOk()
            ->assertJsonPath('total', 1);

        Sanctum::actingAs($this->staff);
        $this->getJson('/api/v1/admin/field-expenses')->assertForbidden();
        $this->getJson("/api/v1/admin/field-expenses/{$expense->id}")->assertForbidden();
        $this->postJson("/api/v1/admin/field-expenses/{$expense->id}/approve")
            ->assertForbidden();
        $this->postJson("/api/v1/admin/field-expenses/{$expense->id}/reject", [
            'admin_notes' => 'This claim has been checked and rejected.',
        ])->assertForbidden();
    }

    public function test_admin_expense_details_include_assignment_evidence_without_device_credentials(): void
    {
        $this->assignment->update([
            'clock_in_lat' => -6.8,
            'clock_in_lng' => 39.2,
            'clock_out_at' => now(),
            'clock_out_lat' => -6.81,
            'clock_out_lng' => 39.21,
            'last_lat' => -6.82,
            'last_lng' => 39.22,
            'gps_accuracy' => 12.5,
            'is_mock_location' => true,
            'is_flagged' => true,
            'flag_reasons' => ['mock_location'],
            'risk_score' => 70,
        ]);
        $expense = $this->createExpenseFor($this->staff, $this->assignment);
        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/admin/field-expenses/{$expense->id}")
            ->assertOk()
            ->assertJsonPath('expense.employee.name', $this->staff->name)
            ->assertJsonPath('expense.field_assignment.clock_in_location.latitude', -6.8)
            ->assertJsonPath('expense.field_assignment.clock_out_location.longitude', 39.21)
            ->assertJsonPath('expense.field_assignment.last_known_location.latitude', -6.82)
            ->assertJsonPath('expense.field_assignment.is_mock_location', true)
            ->assertJsonPath('expense.field_assignment.is_flagged', true)
            ->assertJsonPath('expense.field_assignment.risk_score', 70)
            ->assertJsonMissingPath('expense.field_assignment.device_uuid')
            ->assertJsonMissingPath('expense.device_uuid');
    }

    public function test_admin_expense_filters_and_pagination_are_company_scoped(): void
    {
        $fuel = $this->createExpenseFor($this->staff, $this->assignment);
        $meal = $this->createExpenseFor($this->staff, $this->assignment);
        $meal->update(['category' => 'meals_refreshments']);
        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/field-expenses?category=meals_refreshments&employee='.$this->staff->id
            .'&field_assignment_id='.$this->assignment->id.'&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $meal->id)
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('total', 1);
        $this->getJson('/api/v1/admin/field-expenses?date_from='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertJsonPath('total', 0);
        $this->assertNotSame($fuel->id, $meal->id);
    }

    public function test_reviewers_cannot_review_their_own_claim(): void
    {
        $assignment = $this->makeAssignment($this->staff);
        $expense = $this->createExpenseFor($this->staff, $assignment);
        $this->staff->update(['role' => 'manager']);
        Sanctum::actingAs($this->staff);

        $this->postJson("/api/v1/admin/field-expenses/{$expense->id}/approve")
            ->assertForbidden();
        $this->assertDatabaseHas('field_expenses', [
            'id' => $expense->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('field_expense_review_audits', 0);
    }

    public function test_pending_claim_can_be_approved_once_and_audit_is_created(): void
    {
        $expense = $this->createExpenseFor($this->staff, $this->assignment);
        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/field-expenses/{$expense->id}/approve")
            ->assertOk()
            ->assertJsonPath('expense.status', 'approved')
            ->assertJsonPath('expense.review.reviewed_by', $admin->name);

        $this->assertDatabaseHas('field_expenses', [
            'id' => $expense->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('field_expense_review_audits', [
            'field_expense_id' => $expense->id,
            'previous_status' => 'pending',
            'new_status' => 'approved',
            'reviewer_id' => $admin->id,
            'action' => 'approve',
        ]);
        $this->postJson("/api/v1/admin/field-expenses/{$expense->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expense');
        $this->assertDatabaseCount('field_expense_review_audits', 1);
    }

    public function test_pending_claim_can_be_rejected_only_with_a_meaningful_reason(): void
    {
        $expense = $this->createExpenseFor($this->staff, $this->assignment);
        $manager = User::factory()->create([
            'role' => 'manager',
            'company_id' => $this->company->id,
        ]);
        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/admin/field-expenses/{$expense->id}/reject")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('admin_notes');
        $this->postJson("/api/v1/admin/field-expenses/{$expense->id}/reject", [
            'admin_notes' => 'No',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('admin_notes');

        $reason = 'Receipt does not match the claimed field assignment.';
        $this->postJson("/api/v1/admin/field-expenses/{$expense->id}/reject", [
            'admin_notes' => $reason,
        ])->assertOk()
            ->assertJsonPath('expense.status', 'rejected')
            ->assertJsonPath('expense.review.admin_notes', $reason);

        $this->assertDatabaseHas('field_expense_review_audits', [
            'field_expense_id' => $expense->id,
            'new_status' => 'rejected',
            'reviewer_id' => $manager->id,
            'admin_notes' => $reason,
            'action' => 'reject',
        ]);
        $this->postJson("/api/v1/admin/field-expenses/{$expense->id}/approve")
            ->assertUnprocessable();
        $this->assertDatabaseCount('field_expense_review_audits', 1);
    }

    public function test_admin_receipt_access_is_company_scoped_and_staff_history_remains_available(): void
    {
        $expense = $this->createExpenseFor($this->staff, $this->assignment, true);
        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        Sanctum::actingAs($admin);
        $this->get("/api/v1/admin/field-expenses/{$expense->id}/receipt")->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.field-expenses.receipt', $expense))
            ->assertOk();

        $otherCompany = Company::create([
            'name' => 'Other Receipt Company',
            'slug' => 'other-receipt-company',
        ]);
        $otherAdmin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $otherCompany->id,
        ]);
        Sanctum::actingAs($otherAdmin);
        $this->get("/api/v1/admin/field-expenses/{$expense->id}/receipt")->assertNotFound();

        Sanctum::actingAs($this->staff);
        $this->getJson('/api/v1/field/expenses')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_field_expense_review_web_queue_and_manager_actions_are_company_scoped(): void
    {
        $expense = $this->createExpenseFor($this->staff, $this->assignment);
        $manager = User::factory()->create([
            'role' => 'manager',
            'company_id' => $this->company->id,
        ]);
        $this->actingAs($manager)
            ->get(route('admin.field-expenses.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSeeText('Pending Field Reimbursements')
            ->assertSeeText($this->staff->name);

        $this->post(route('admin.field-expenses.reject', $expense), [
            'admin_notes' => 'The claimed purpose is not supported by the assignment evidence.',
        ])->assertRedirect(route('admin.field-expenses.show', $expense));

        $this->get(route('admin.field-expenses.show', $expense))
            ->assertOk()
            ->assertSeeText('Rejected')
            ->assertSeeText('The claimed purpose is not supported by the assignment evidence.');
    }

    public function test_manager_login_opens_the_expense_review_queue(): void
    {
        Auth::shouldUse('web');
        $manager = User::factory()->create([
            'role' => 'manager',
            'company_id' => $this->company->id,
        ]);

        $this->post('/login', [
            'email' => $manager->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.field-expenses.index'));
    }

    public function test_admin_cannot_access_another_companys_expense(): void
    {
        $expense = $this->createExpenseFor($this->staff, $this->assignment);
        $otherCompany = Company::create([
            'name' => 'Other Expense Company',
            'slug' => 'other-expense-company',
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $otherCompany->id,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/field/expenses/{$expense->id}")
            ->assertNotFound();
    }

    public function test_submission_cannot_set_review_fields_and_new_expenses_remain_pending(): void
    {
        $this->postJson('/api/v1/field/expenses', $this->validPayload([
            'user_id' => User::factory()->create([
                'role' => 'staff',
                'company_id' => $this->company->id,
            ])->id,
            'status' => 'approved',
            'reviewed_by' => $this->staff->id,
            'reviewed_at' => now()->toIso8601String(),
            'admin_notes' => 'Approve this claim',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors([
                'user_id',
                'status',
                'reviewed_by',
                'reviewed_at',
                'admin_notes',
            ]);

        $this->postJson('/api/v1/field/expenses', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('expense.status', 'pending');
    }

    public function test_offline_expense_can_sync_to_its_closed_offline_assignment(): void
    {
        $this->assignment->update([
            'clock_out_at' => now(),
            'is_offline' => true,
        ]);

        $this->postJson('/api/v1/field/expenses', $this->validPayload([
            'client_uuid' => fake()->uuid(),
            'is_offline' => true,
        ]))->assertCreated()
            ->assertJsonPath('expense.is_offline', true);

        $this->postJson('/api/v1/field/expenses', $this->validPayload())
            ->assertUnprocessable();
    }

    public function test_existing_active_field_assignment_endpoint_remains_available(): void
    {
        $this->getJson('/api/v1/field/active')
            ->assertOk()
            ->assertJsonPath('assignment.id', $this->assignment->id);
    }

    public function test_only_tzs_currency_is_accepted_until_another_currency_is_supported(): void
    {
        $this->postJson('/api/v1/field/expenses', $this->validPayload([
            'currency' => 'USD',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('currency');
    }

    public function test_expenses_prevent_assignment_and_owner_deletion(): void
    {
        $this->createExpenseFor($this->staff, $this->assignment);

        try {
            $this->assignment->delete();
            $this->fail('Deleting an assignment with expenses should be restricted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('field_expenses', [
                'field_assignment_id' => $this->assignment->id,
            ]);
        }

        try {
            $this->staff->delete();
            $this->fail('Deleting an expense owner should be restricted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('field_expenses', [
                'user_id' => $this->staff->id,
            ]);
        }
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'field_assignment_id' => $this->assignment->id,
            'category' => 'transport_fuel',
            'amount' => '25000.00',
            'description' => 'Fuel for client visit',
            ...$overrides,
        ];
    }

    private function makeAssignment(User $user): FieldAssignment
    {
        return FieldAssignment::query()->create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'task_type' => 'marketing',
            'purpose_description' => 'Customer outreach',
            'clock_in_at' => now()->subHour(),
        ]);
    }

    private function createExpenseFor(
        User $user,
        FieldAssignment $assignment,
        bool $withReceipt = false
    ): FieldExpense {
        $path = $withReceipt
            ? $this->pngReceipt()->store('receipts', FieldExpense::RECEIPT_DISK)
            : null;

        return FieldExpense::query()->create([
            'field_assignment_id' => $assignment->id,
            'user_id' => $user->id,
            'category' => 'transport_fuel',
            'amount' => '25000.00',
            'currency' => 'TZS',
            'receipt_image_path' => $path,
            'status' => 'pending',
        ]);
    }

    private function pngReceipt(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'receipt.png',
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l7sAAAAASUVORK5CYII=',
                true
            )
        );
    }

    private function createExpenseTestSchema(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('employee_id')->nullable()->unique();
            $table->string('role')->default('staff');
            $table->boolean('is_active')->default(true);
            $table->string('organization')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('remember_token')->nullable();
            $table->timestamps();
        });
        Schema::create('field_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id');
            $table->string('task_type', 32);
            $table->text('purpose_description')->nullable();
            $table->timestamp('clock_in_at')->nullable();
            $table->timestamp('clock_out_at')->nullable();
            $table->decimal('clock_in_lat', 10, 7)->nullable();
            $table->decimal('clock_in_lng', 10, 7)->nullable();
            $table->decimal('clock_out_lat', 10, 7)->nullable();
            $table->decimal('clock_out_lng', 10, 7)->nullable();
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->float('gps_accuracy')->nullable();
            $table->string('device_uuid', 128)->nullable();
            $table->boolean('is_mock_location')->default(false);
            $table->boolean('is_flagged')->default(false);
            $table->json('flag_reasons')->nullable();
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->boolean('is_offline')->default(false);
            $table->timestamps();
        });
        Schema::create('client_visits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('field_assignment_id');
            $table->unsignedBigInteger('user_id');
            $table->string('client_name');
            $table->text('notes')->nullable();
            $table->timestamp('arrived_at');
            $table->decimal('arrived_lat', 10, 7)->nullable();
            $table->decimal('arrived_lng', 10, 7)->nullable();
            $table->float('arrived_accuracy')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->decimal('departed_lat', 10, 7)->nullable();
            $table->decimal('departed_lng', 10, 7)->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->uuid('client_uuid')->nullable();
            $table->boolean('is_offline')->default(false);
            $table->string('time_source', 16)->default('server');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // The repository's field-operations migration extends this table but does not create it.
        $migration = require base_path(
            'database/migrations/2026_11_09_010000_create_field_expenses_table.php'
        );
        $migration->up();
        $auditMigration = require base_path(
            'database/migrations/2026_11_09_020000_create_field_expense_review_audits_table.php'
        );
        $auditMigration->up();
    }
}
