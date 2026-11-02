<?php

namespace Tests\Feature\Business;

use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class StockBusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_loss_and_damage_are_always_negative_regardless_of_input_sign(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->manager)
            ->post('/inventaires/adjust', [
                'product_id' => $scenario->product->id,
                'type' => 'perte',
                'quantity' => 2,
                'reason' => 'Produit périmé',
            ])
            ->assertRedirect();

        $this->assertSame(38, $this->stock($scenario));
        $this->assertSame(-2, (int) StockMovement::query()->where('type', 'perte')->value('quantity'));

        $this->actingAs($scenario->manager)
            ->post('/inventaires/adjust', [
                'product_id' => $scenario->product->id,
                'type' => 'casse',
                'quantity' => -3,
                'reason' => 'Flacon cassé',
            ])
            ->assertRedirect();

        $this->assertSame(35, $this->stock($scenario));
        $this->assertSame(-3, (int) StockMovement::query()->where('type', 'casse')->value('quantity'));
    }

    public function test_correction_is_signed_and_negative_stock_is_rejected(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->manager)
            ->post('/inventaires/adjust', [
                'product_id' => $scenario->product->id,
                'type' => 'correction',
                'quantity' => 4,
                'reason' => 'Réception oubliée',
            ])
            ->assertRedirect();

        $this->assertSame(44, $this->stock($scenario));

        $this->actingAs($scenario->manager)
            ->post('/inventaires/adjust', [
                'product_id' => $scenario->product->id,
                'type' => 'correction',
                'quantity' => -1000,
                'reason' => 'Correction impossible',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('quantity');

        $this->assertSame(44, $this->stock($scenario));
    }

    public function test_inventory_count_records_only_the_real_delta(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->manager)
            ->post('/inventaires', [
                'counts' => [['product_id' => $scenario->product->id, 'counted' => 10]],
            ])
            ->assertRedirect();

        $this->assertSame(10, $this->stock($scenario));
        $this->assertSame(1, StockMovement::query()->where('type', 'inventaire')->count());
        $this->assertSame(-30, (int) StockMovement::query()->where('type', 'inventaire')->value('quantity'));

        $this->actingAs($scenario->manager)
            ->post('/inventaires', [
                'counts' => [['product_id' => $scenario->product->id, 'counted' => 10]],
            ])
            ->assertRedirect();

        $this->assertSame(1, StockMovement::query()->where('type', 'inventaire')->count());
    }

    public function test_stock_version_is_incremented_once_per_movement(): void
    {
        $scenario = PharmacyScenario::create();
        $stock = StockLevel::query()
            ->where('pharmacy_id', $scenario->pharmacyA->id)
            ->where('product_id', $scenario->product->id)
            ->firstOrFail();
        $version = $stock->version;

        app(StockService::class)->apply(
            $scenario->pharmacyA,
            $scenario->product,
            1,
            'correction',
            'Test de version',
            $scenario->manager->id,
        );

        $this->assertSame($version + 1, $stock->fresh()->version);
    }

    private function stock(PharmacyScenario $scenario): int
    {
        return (int) StockLevel::query()
            ->where('pharmacy_id', $scenario->pharmacyA->id)
            ->where('product_id', $scenario->product->id)
            ->value('quantity');
    }
}
