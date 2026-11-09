<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_user_can_log_in_through_web_and_api(): void
    {
        $company = $this->company();
        $user = $this->user(['role' => 'staff', 'company_id' => $company->id]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/staff/dashboard');

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.company_id', $company->id)
            ->assertJsonStructure(['token']);
    }

    public function test_missing_company_has_the_same_actionable_message_in_web_and_api_login(): void
    {
        $user = $this->user(['role' => 'staff']);
        $message = 'Your account has no company assignment. Contact a platform administrator to assign it.';

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => $message]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden()
            ->assertJsonPath('message', $message)
            ->assertJsonMissingPath('token');
    }

    public function test_suspended_company_remains_blocked_consistently(): void
    {
        $company = $this->company();
        $company->status = Company::STATUS_SUSPENDED;
        $company->save();
        $user = $this->user(['role' => 'staff', 'company_id' => $company->id]);
        $message = 'Your company is suspended. Contact platform support.';

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => $message]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden()
            ->assertJsonPath('message', $message)
            ->assertJsonMissingPath('token');
    }

    public function test_super_admin_without_company_can_log_in_and_reach_platform_management(): void
    {
        $superAdmin = $this->user(['role' => 'super_admin']);

        $this->post('/login', [
            'email' => $superAdmin->email,
            'password' => 'password',
        ])->assertRedirect('/platform');

        $this->get('/platform/companies')->assertOk();

        $this->postJson('/api/v1/login', [
            'email' => $superAdmin->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('user.role', 'super_admin')
            ->assertJsonPath('user.company_id', null)
            ->assertJsonStructure(['token']);
    }

    public function test_inactive_super_admin_is_denied_platform_management(): void
    {
        $superAdmin = $this->user(['role' => 'super_admin', 'is_active' => false]);
        Sanctum::actingAs($superAdmin);

        $this->getJson('/platform/companies')
            ->assertForbidden();
    }

    public function test_company_admin_cannot_manage_platform_companies(): void
    {
        $company = $this->company();
        $admin = $this->user(['role' => 'admin', 'company_id' => $company->id]);
        Sanctum::actingAs($admin);

        $this->getJson('/platform/companies')->assertForbidden();
        $this->postJson('/platform/companies', [
            'name' => 'Unauthorized Company',
            'slug' => 'unauthorized-company',
        ])->assertForbidden();
    }

    private function company(): Company
    {
        return Company::create([
            'name' => 'Login Test Company',
            'slug' => 'login-test-company',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function user(array $attributes = []): User
    {
        $user = User::factory()->create();
        $user->forceFill($attributes)->save();

        return $user->fresh();
    }
}
