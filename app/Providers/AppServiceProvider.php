<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('admin-access', fn (User $user): bool => $user->hasRole(User::ROLE_SUPER_ADMIN));
        Gate::define('manage-cultural-content', fn (User $user): bool => $user->hasRole(User::ROLE_SUPER_ADMIN));
        Gate::define('manage-media', fn (User $user): bool => $user->hasRole(User::ROLE_SUPER_ADMIN));
        Gate::define('manage-regions', fn (User $user): bool => $user->hasRole(User::ROLE_SUPER_ADMIN));
        Gate::define('manage-categories', fn (User $user): bool => $user->hasRole(User::ROLE_SUPER_ADMIN));
        Gate::define('manage-sources', fn (User $user): bool => $user->hasRole(User::ROLE_SUPER_ADMIN));
        Gate::define('verify-content', fn (User $user): bool => $user->hasRole(User::ROLE_SUPER_ADMIN));
        Gate::define('manage-admin-accounts', fn (User $user): bool => $user->hasRole(
            User::ROLE_SUPER_ADMIN
        ));

        RateLimiter::for('admin-login', function (Request $request): array {
            $emailInput = $request->input('email');
            $email = is_string($emailInput) ? Str::lower(trim($emailInput)) : '';

            return [
                Limit::perMinute(10)->by('admin-login-ip:'.$request->ip()),
                Limit::perMinute(5)->by('admin-login-email:'.hash('sha256', $email)),
            ];
        });
    }
}
