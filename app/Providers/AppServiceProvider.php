<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

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
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Named rate limiters, used on routes as 'throttle:<name>'.
     *
     * Why named instead of 'throttle:30,1' on each route:
     *   - the rule lives in one place and has a meaning ("enquiries")
     *   - it can depend on the request: per user, per IP, per token
     *   - several limits can apply at once (per minute AND per day)
     *   - it can return a custom response when the limit is hit
     *
     * by(): what is counted. Each distinct key has its own counter, stored in
     * the cache (so with several servers it must be a shared cache: Redis).
     */
    protected function configureRateLimiting(): void
    {
        // Sending enquiries: stop bots and accidental double sends, per USER.
        RateLimiter::for('enquiries', function (Request $request): array {
            $user = 'user:'.$request->user()?->id;

            return [
                // A list = all limits apply. The first one exceeded answers.
                // Each limit needs its OWN key ("minute:" / "day:"): with the same
                // key, both limits would share one counter and break each other.
                Limit::perMinute(5)->by('minute:'.$user)->response($this->tooManyEnquiries(...)),
                Limit::perDay(20)->by('day:'.$user)->response($this->tooManyEnquiries(...)),
            ];
        });

        // Create / update / delete in the admin area, per logged-in user.
        RateLimiter::for('admin-writes', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('user:'.$request->user()?->id));

        // CSV exports are heavy: a few per minute is plenty.
        RateLimiter::for('exports', fn (Request $request): Limit => Limit::perMinute(5)
            ->by('user:'.$request->user()?->id));

        // The JSON API (Block 5): per token user when logged in, else per IP.
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip()));
    }

    /**
     * Instead of a bare "429 Too Many Requests" page, send the user back to the
     * form with a normal validation-style error under the message field.
     * The Retry-After header still tells clients how long to wait.
     *
     * @param  array<string, string>  $headers
     */
    private function tooManyEnquiries(Request $request, array $headers): Response
    {
        return back()
            ->withErrors(['message' => 'You are sending enquiries too quickly. Please wait a little and try again.'])
            ->withHeaders($headers);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        // Gates: simple yes/no checks that are not about one model.
        // (Rules about a specific model live in policies, e.g. PropertyPolicy.)

        // Enter the admin area at all: admins and agents.
        Gate::define('access-admin', fn (User $user): bool => $user->is_admin || $user->isAgent());

        // Site-wide settings (features list, admin notifications): admins only.
        Gate::define('manage-site', fn (User $user): bool => $user->is_admin);

        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
