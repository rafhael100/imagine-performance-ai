<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Deliberately empty until individual integration gates are approved.
    }

    public function boot(): void
    {
        // Never register routes, DB access, auth or provider adapters implicitly.
    }
}
