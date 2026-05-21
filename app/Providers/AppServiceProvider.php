<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use App\Services\SynapCores\SynapCoresClient;
use App\Services\SynapCores\SynapCoresService;
use App\Services\SynapCores\Contracts\SynapCoresClientInterface;
use App\Services\SynapCores\FakeSynapCoresClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SynapCoresClientInterface::class, fn () => new SynapCoresClient(
            baseUrl: (string) config('services.synapcores.base_url'),
            apiKey:  (string) config('services.synapcores.api_key'),
            timeout: (int) config('services.synapcores.timeout', 30),
            jwtTtl:  (int) config('services.synapcores.jwt_ttl', 3600),
        ));
        
        $this->app->singleton(SynapCoresService::class, fn ($app) => new SynapCoresService(
            $app->make(SynapCoresClientInterface::class)
        ));

        if (app()->environment('testing')) {
            $this->app->singleton(SynapCoresClientInterface::class, fn () => new FakeSynapCoresClient());
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
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
