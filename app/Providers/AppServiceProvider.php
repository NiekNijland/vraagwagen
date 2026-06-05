<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\QueryPlan\CostEstimator;
use App\Services\QueryPlan\SocrataStorageTypes;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use NiekNijland\RDW\Http\Configuration as RdwConfiguration;
use NiekNijland\RDW\Rdw;
use NiekNijland\RDW\Schema\SchemaRegistry;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Generous timeout: unfiltered group-bys over the ~16M-row dataset are slow on a cold Socrata cache.
        $this->app->singleton(Rdw::class, static fn (): Rdw => new Rdw(new RdwConfiguration(
            appToken: config('vraagwagen.rdw_app_token'),
            userAgent: 'vraagwagen/0.1 (laravel)',
            timeoutSeconds: 25.0,
        )));

        // Share the Rdw client's SchemaRegistry so schema lookups can't diverge across collaborators.
        $this->app->singleton(SchemaRegistry::class, static fn ($app): SchemaRegistry => $app->make(Rdw::class)->schemas());

        // Reads + decodes a ~30 KB Socrata metadata file per dataset and memoises it on the instance;
        // share one instance so that work happens once per process, not once per query request.
        $this->app->singleton(SocrataStorageTypes::class);

        // Bind (not singleton) so a post-boot config()->set('vraagwagen.model_prices') is honoured.
        $this->app->bind(CostEstimator::class, static fn (): CostEstimator => new CostEstimator(
            (array) config('vraagwagen.model_prices', []),
        ));
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Public RDW endpoint: per-IP burst + per-IP daily cap + global daily cap protect the OpenAI budget.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('rdw-query', fn (Request $request): array => [
            Limit::perMinute((int) config('vraagwagen.rate_limit.per_minute'))->by((string) $request->ip()),
            Limit::perDay((int) config('vraagwagen.rate_limit.per_day_ip'))->by('rdw-query:ip:' . $request->ip()),
            Limit::perDay((int) config('vraagwagen.rate_limit.per_day_global'))->by('rdw-query:global'),
        ]);

        RateLimiter::for('rdw-feedback', fn (Request $request): array => [
            Limit::perMinute((int) config('vraagwagen.rate_limit.feedback_per_minute'))->by((string) $request->ip()),
        ]);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

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
