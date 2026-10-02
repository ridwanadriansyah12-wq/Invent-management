<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // ── Gates Operasional PRISM Stock ─────────────────────────────────────

        // Hak Akses Khusus Administrator
        Gate::define('admin-only', fn(User $user) => $user->isAdmin());
        Gate::define('manage-settings', fn(User $user) => $user->isAdmin());
        Gate::define('run-pipeline', fn(User $user) => $user->isAdmin());
        Gate::define('manage-sku', fn(User $user) => $user->isAdmin());

        // Hak Akses Approver (dan Admin)
        Gate::define('review-parameters', fn(User $user) => $user->isAdmin() || $user->isApprover());
        Gate::define('manage-pr', fn(User $user) => $user->isAdmin() || $user->isApprover());
        Gate::define('resolve-alerts', fn(User $user) => $user->isAdmin() || $user->isApprover());
        Gate::define('view-system-status', fn(User $user) => $user->isAdmin() || $user->isApprover());

        // Hak Akses Staff (Gudang) beserta Approver & Admin
        Gate::define('record-movement', fn(User $user) => in_array($user->role, ['admin', 'approver', 'staff'], true));
        Gate::define('view-inventory', fn(User $user) => in_array($user->role, ['admin', 'approver', 'staff'], true));
    }
}
