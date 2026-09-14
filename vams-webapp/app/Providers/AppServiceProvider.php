<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

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
        // Configure Vite to use the build directory
        Vite::useBuildDirectory('build');

        $this->configureRateLimiting();

        // RBAC: resolve any ability check whose name matches a seeded
        // permission slug ('can:resource.action' middleware, $user->can(),
        // @can Blade directive, $this->authorize() in controllers) against
        // the role_permissions pivot via User::hasPermission(). Ability
        // checks that do not correspond to a known permission slug (e.g.
        // future model policies) fall through to normal Gate/Policy resolution.
        Gate::before(function (User $user, string $ability) {
            if (! Permission::query()->where('slug', $ability)->exists()) {
                return null;
            }

            return $user->hasPermission($ability);
        });
    }

    /**
     * Named rate limiters (manuscript §1.2.2 objective 8 — "rate limiting and
     * traffic filtering to reduce excessive or unauthorized requests and
     * improve resistance to flooding attacks").
     *
     * Rejections are written to `system_logs`, so they surface in the System
     * Logs report alongside the other security events rather than vanishing
     * into a bare 429 — that is objective 9's half of the same story.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('rfid-ingestion', function (Request $request) {
            // Key on the device's API key so one misbehaving reader cannot
            // exhaust the budget for the others; unauthenticated junk falls
            // back to the source IP.
            $key = $request->header('X-Rfid-Api-Key') ?: $request->ip();

            return Limit::perMinute((int) config('rfid.rate_limit_per_minute'))
                ->by($key)
                ->response(function (Request $request) {
                    $this->recordThrottle(
                        'rfid_ingestion_api',
                        'Rate limit exceeded; further detections from this device are being refused.',
                        ['api_key' => $request->header('X-Rfid-Api-Key'), 'ip' => $request->ip()],
                    );

                    return response()->json([
                        'message' => 'Too many detections submitted. Slow down and retry shortly.',
                    ], 429);
                });
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)
                ->by(Str::lower((string) $request->input('email')).'|'.$request->ip())
                ->response(function (Request $request) {
                    $this->recordThrottle(
                        'authentication',
                        'Rate limit exceeded on the login form.',
                        ['email' => $request->input('email'), 'ip' => $request->ip()],
                    );

                    return response()->json([
                        'message' => 'Too many login attempts. Please wait a minute and try again.',
                    ], 429);
                });
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function recordThrottle(string $source, string $message, array $context): void
    {
        // Never let logging a rejection turn into a second failure — the point
        // of the limiter is to shed load, not to add work under pressure.
        try {
            SystemLog::create([
                'level' => 'warning',
                'source' => $source,
                'message' => $message,
                'context' => $context,
            ]);
        } catch (Throwable) {
            // Intentionally ignored.
        }
    }
}
