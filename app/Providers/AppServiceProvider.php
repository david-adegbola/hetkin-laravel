<?php

namespace App\Providers;

use App\Domain\Auth\Models\SessionRecord;
use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Policies\InvitationPolicy;
use App\Policies\MemberPolicy;
use App\Policies\MeteringPointPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\SchedulePolicy;
use App\Policies\SessionPolicy;
use App\Policies\SitePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton, not a fresh instance per resolution: every
        // OrganizationScope check within one request must see the same
        // bound organization — see CurrentOrganization's docblock for why
        // this is safe under standard php-fpm/CGI but not Octane as-is
        // (MIGRATION_PLAN.md ADR-12).
        $this->app->singleton(CurrentOrganization::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Single source of truth for password strength, applied by every
        // password-setting Form Request (reset, change) via Password::defaults()
        // rather than each one repeating the rule. Matches the Account
        // Security module's stated minimum: 12 chars, upper + lower case,
        // at least one number, at least one symbol. `uncompromised()` checks
        // the password against the Have I Been Pwned range API (k-anonymity
        // — only a hash prefix leaves the server) via Laravel's built-in
        // integration; fails open (does not block submission) if the API is
        // unreachable, so this can never turn into an outage.
        Password::defaults(function () {
            return Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });

        // Forgot-password abuse looks different from login brute-forcing —
        // the attacker's goal is mail-bombing an arbitrary inbox, not
        // guessing a secret — so it gets its own limiter rather than
        // reusing the login route's throttle:20,1. Keyed by IP: keying by
        // the submitted email too would let an attacker learn "this email
        // is close to its limit" as a side channel for account
        // enumeration, which is exactly what this module is required not
        // to leak.
        RateLimiter::for('password-reset-link', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // Explicit registration, not relying on Laravel's naming-convention
        // auto-discovery: these all live under App\Domain\*\Models, not
        // App\Models, which the default guesser doesn't check.
        Gate::policy(SessionRecord::class, SessionPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Member::class, MemberPolicy::class);
        Gate::policy(Invitation::class, InvitationPolicy::class);
        Gate::policy(Site::class, SitePolicy::class);
        Gate::policy(MeteringPoint::class, MeteringPointPolicy::class);
        Gate::policy(ProductionSchedule::class, SchedulePolicy::class);
    }
}
