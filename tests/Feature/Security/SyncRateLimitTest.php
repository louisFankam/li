<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SyncRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_routes_are_rate_limited(): void
    {
        foreach (['sync.device', 'sync.push', 'sync.pull'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} introuvable.");
            $this->assertContains('throttle:sync', $route->gatherMiddleware(), "Route {$name} non limitée.");
        }
    }
}
