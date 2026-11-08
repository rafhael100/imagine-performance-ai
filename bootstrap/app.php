<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * F1-02-B: intentionally no web routes, API routes, health endpoint, login,
 * queues, database migrations, provider connections, or remote services.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(commands: __DIR__.'/../routes/console.php')
    ->withMiddleware(function (Middleware $middleware): void {
        // No middleware or authentication adapters enabled in this bootstrap.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // No remote logging or special exception exporters registered.
    })
    ->create();
