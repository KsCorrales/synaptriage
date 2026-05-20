<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SynapCoresClient::class, fn () => new SynapCoresClient(
            baseUrl: config('services.synapcores.base_url'),
            apiKey:  config('services.synapcores.api_key'),
            timeout: config('services.synapcores.timeout'),
            jwtTtl:  config('services.synapcores.jwt_ttl'),
        ));
    
        $this->app->singleton(SynapCoresService::class, fn (app) => new SynapCoresService(
            app->make(SynapCoresClient::class)
        ));
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
