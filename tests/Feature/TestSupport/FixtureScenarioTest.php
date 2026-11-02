<?php

namespace Tests\Feature\TestSupport;

use App\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class FixtureScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_scenario_builds_two_isolated_sites_without_demo_seeder(): void
    {
        $scenario = PharmacyScenario::create();

        $this->assertNotSame($scenario->pharmacyA->id, $scenario->pharmacyB->id);
        $this->assertSame('gerant', $scenario->manager->roleFor($scenario->pharmacyA));
        $this->assertSame('vendeur', $scenario->sellerA->roleFor($scenario->pharmacyA));
        $this->assertNull($scenario->sellerA->roleFor($scenario->pharmacyB));

        $this->assertTrue($scenario->sellerA->canAccessPharmacy($scenario->pharmacyA));
        $this->assertFalse($scenario->sellerA->canAccessPharmacy($scenario->pharmacyB));
        $this->assertTrue($scenario->admin->canAccessPharmacy($scenario->pharmacyB));

        $this->assertSame(40, StockLevel::query()
            ->whereBelongsTo($scenario->pharmacyA)
            ->whereBelongsTo($scenario->product)
            ->value('quantity'));
        $this->assertSame(3, StockLevel::query()
            ->whereBelongsTo($scenario->pharmacyB)
            ->whereBelongsTo($scenario->product)
            ->value('quantity'));

        $this->assertDatabaseCount('pharmacies', 2);
        $this->assertDatabaseCount('users', 6);
        $this->assertDatabaseMissing('users', ['email' => 'admin@pharmagest.test']);
        $this->assertSame(80, (int) $scenario->insurer->rules()->value('value'));
        $this->assertSame($scenario->insurer->id, $scenario->customer->insurance_company_id);
    }
}
