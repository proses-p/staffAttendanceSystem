<?php

namespace App\Providers;

use App\Models\User;
use App\Models\StaffLeaveRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define(
            'admin-oversight',
            fn (User $user): bool => in_array($user->role, ['admin', 'manager'], true)
        );

        View::composer('components.admin-sidebar', function ($view): void {
            $companyId = auth()->user()?->company_id;
            $unreadLeaveCount = $companyId
                ? StaffLeaveRecord::query()
                    ->where('company_id', $companyId)
                    ->where('is_read', false)
                    ->count()
                : 0;

            $view->with('unreadLeaveCount', $unreadLeaveCount);
        });
    }
}
