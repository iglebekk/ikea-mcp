<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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
        // The screen shown to a signed-in user when an MCP client requests
        // OAuth access. Published from laravel/mcp via `vendor:publish`.
        Passport::authorizationView(fn (array $parameters) => view('mcp.authorize', $parameters));
    }
}
