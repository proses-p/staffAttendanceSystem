<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create([
            'name' => 'Profile Test Company',
            'slug' => 'profile-test-company',
        ]);
    }

    public function test_authenticated_user_can_fetch_only_public_profile_fields(): void
    {
        $user = User::factory()->create([
            'organization' => 'Example Company',
            'phone' => '+255700000000',
            'employee_id' => 'EMP-001',
        ]);
        $this->assignCompany($user);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.organization', 'Example Company')
            ->assertJsonPath('user.employee_id', 'EMP-001')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');
    }

    public function test_profile_update_only_changes_name_and_phone(): void
    {
        $user = User::factory()->create([
            'organization' => 'Original Company',
            'employee_id' => 'EMP-002',
        ]);
        $this->assignCompany($user);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/user', [
            'name' => 'Updated Name',
            'phone' => '+255711111111',
            'email' => 'changed@example.test',
            'organization' => 'Changed Company',
            'employee_id' => 'EMP-999',
            'role' => 'admin',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('user.name', 'Updated Name')
            ->assertJsonPath('user.phone', '+255711111111')
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.organization', 'Original Company')
            ->assertJsonPath('user.employee_id', 'EMP-002')
            ->assertJsonPath('user.role', 'staff')
            ->assertJsonPath('user.is_active', true);
    }

    public function test_profile_update_validates_editable_fields(): void
    {
        Sanctum::actingAs($this->createCompanyUser());

        $this->putJson('/api/v1/user', [
            'name' => '',
            'phone' => str_repeat('1', 31),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone']);
    }

    public function test_password_change_requires_the_current_password_and_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => 'original-password',
        ]);
        $this->assignCompany($user);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->putJson('/api/v1/user/password', [
            'current_password' => 'original-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_profile_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
        $this->putJson('/api/v1/user', ['name' => 'Updated'])->assertUnauthorized();
        $this->putJson('/api/v1/user/password', [])->assertUnauthorized();
    }

    private function createCompanyUser(): User
    {
        $user = User::factory()->create();
        $this->assignCompany($user);

        return $user;
    }

    private function assignCompany(User $user): void
    {
        $user->company_id = $this->company->id;
        $user->save();
    }
}
