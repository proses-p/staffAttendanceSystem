<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_command_refuses_non_local_environment_without_promoting_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->artisan('app:bootstrap-super-admin', [
            '--operator' => 'CHANGE-TEST',
        ])->assertExitCode(1);

        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertDatabaseCount('users', 1);
    }
}
