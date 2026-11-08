<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class NoPublicEndpointsTest extends TestCase
{
    public function test_no_http_routes_are_registered(): void
    {
        self::assertCount(0, Route::getRoutes());
    }

    public function test_unknown_request_returns_not_found(): void
    {
        $this->get('/f102b-demo-disabled')->assertNotFound();
    }
}
