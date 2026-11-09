<?php

namespace Tests\Feature;

use App\Mail\StaffInvitationMail;
use App\Models\Company;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlatformDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_dashboard_is_available_to_super_admin_and_uses_real_owned_metrics(): void
    {
        $activeCompany = $this->company('active-platform');
        $suspendedCompany = $this->company('suspended-platform', Company::STATUS_SUSPENDED);
        $staff = $this->user($activeCompany, 'staff');
        $this->user($activeCompany, 'admin');
        $this->user($suspendedCompany, 'staff');
        $this->user(null, 'staff');
        $activeCompany->attendances()->create([
            'user_id' => $staff->id,
            'attendance_date' => today(),
            'check_in_time' => now()->format('H:i:s'),
            'latitude' => 0,
            'longitude' => 0,
            'distance' => 0,
            'status' => 'present',
        ]);
        $invitation = $activeCompany->staffInvitations()->create([
            'email' => 'pending-admin@example.test',
            'token' => 'private-token',
            'expires_at' => now()->addDay(),
        ]);
        $invitation->role = 'admin';
        $invitation->save();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)
            ->get('/platform')
            ->assertOk()
            ->assertSee('Platform overview')
            ->assertSee('Total companies')
            ->assertSee('Attendance today')
            ->assertSee($activeCompany->name)
            ->assertViewHas('stats', fn (array $stats): bool => $stats['companies'] === 2
                && $stats['active_companies'] === 1
                && $stats['suspended_companies'] === 1
                && $stats['staff'] === 2
                && $stats['company_admins'] === 1
                && $stats['attendance_today'] === 1
                && $stats['pending_admin_invitations'] === 1)
            ->assertDontSee('private-token');
    }

    public function test_platform_dashboard_and_company_pages_are_restricted_to_super_admins(): void
    {
        $company = $this->company('role-check');
        $admin = $this->user($company, 'admin');
        $staff = $this->user($company, 'staff');

        $this->get('/platform')->assertRedirect(route('login'));
        $this->actingAs($admin)->get('/platform')->assertForbidden();
        $this->actingAs($staff)->get('/platform/companies')->assertForbidden();
        $this->getJson('/platform/companies')->assertForbidden();
    }

    public function test_companies_page_supports_search_status_filters_and_company_details(): void
    {
        $first = $this->company('directory-first');
        $this->company('directory-second', Company::STATUS_SUSPENDED);
        $staff = $this->user($first, 'staff');
        $admin = $this->user($first, 'admin');
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)
            ->get('/platform/companies?search=directory-first&status=active')
            ->assertOk()
            ->assertSee($first->name)
            ->assertDontSee('directory-second');

        $this->get(route('platform.companies.show', $first))
            ->assertOk()
            ->assertSee('Company profile')
            ->assertSee($first->slug)
            ->assertSee('Invite a company admin')
            ->assertSee('1') // staff count and admin count are both real relations
            ->assertDontSee($staff->password)
            ->assertDontSee($admin->password);
    }

    public function test_company_creation_and_status_changes_work_without_changing_json_contract(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($superAdmin);

        $this->post('/platform/companies', [
            'name' => 'Created Company',
            'slug' => 'created-company',
        ])->assertRedirect();

        $company = Company::where('slug', 'created-company')->firstOrFail();
        $this->assertSame(Company::STATUS_ACTIVE, $company->status);

        $this->patch(route('platform.companies.update', $company), [
            'status' => Company::STATUS_SUSPENDED,
        ])->assertRedirect(route('platform.companies.show', $company));
        $this->assertSame(Company::STATUS_SUSPENDED, $company->fresh()->status);

        $this->patchJson(route('platform.companies.update', $company), [
            'status' => Company::STATUS_ACTIVE,
        ])->assertOk()
            ->assertJsonPath('company.status', Company::STATUS_ACTIVE);

        $this->getJson('/platform/companies')
            ->assertOk()
            ->assertExactJson([
                'companies' => [[
                    'id' => $company->id,
                    'name' => 'Created Company',
                    'slug' => 'created-company',
                    'status' => Company::STATUS_ACTIVE,
                ]],
            ]);
    }

    public function test_company_creation_validation_is_rendered_and_duplicate_slug_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->company('existing-slug');

        $this->from('/platform/companies')->post('/platform/companies', [
            'name' => '',
            'slug' => 'existing-slug',
        ])->assertRedirect('/platform/companies')
            ->assertSessionHasErrors(['name', 'slug']);
    }

    public function test_super_admin_can_invite_company_admin_for_selected_company_only(): void
    {
        Mail::fake();
        $company = $this->company('invitation-target');
        $otherCompany = $this->company('invitation-other');
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($superAdmin);

        $this->post(route('platform.companies.admin-invitations.store', $company), [
            'email' => 'company-admin@example.test',
        ])->assertRedirect();

        $invitation = StaffInvitation::where('email', 'company-admin@example.test')->firstOrFail();
        $this->assertSame($company->id, $invitation->company_id);
        $this->assertSame('admin', $invitation->role);
        $this->assertNotSame($otherCompany->id, $invitation->company_id);
        Mail::assertSent(StaffInvitationMail::class);
    }

    public function test_suspended_company_cannot_receive_admin_invitation(): void
    {
        Mail::fake();
        $company = $this->company('suspended-invites', Company::STATUS_SUSPENDED);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($superAdmin);

        $this->from(route('platform.companies.show', $company))
            ->post(route('platform.companies.admin-invitations.store', $company), [
                'email' => 'blocked-admin@example.test',
            ])
            ->assertRedirect(route('platform.companies.show', $company))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('staff_invitations', ['email' => 'blocked-admin@example.test']);
        Mail::assertNothingSent();
    }

    private function company(string $slug, string $status = Company::STATUS_ACTIVE): Company
    {
        $company = Company::create([
            'name' => str_replace('-', ' ', ucfirst($slug)),
            'slug' => $slug,
        ]);
        $company->status = $status;
        $company->save();

        return $company;
    }

    private function user(?Company $company, string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        if ($company) {
            $user->company_id = $company->id;
            $user->save();
        }

        return $user;
    }
}
