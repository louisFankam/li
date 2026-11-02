<?php

namespace Tests\Feature\Business;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class PurchaseBusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_and_complete_reception_are_bounded_and_idempotent(): void
    {
        $scenario = PharmacyScenario::create();
        $productVersion = $scenario->product->version;
        $purchase = $this->createPurchase($scenario, 2);
        $item = $purchase->items()->firstOrFail();

        $this->receive($scenario, $purchase, $item, 1)
            ->assertRedirect();

        $this->assertSame('partiellement_recue', $purchase->fresh()->status);
        $this->assertSame(41, $this->stock($scenario));
        $this->assertSame($productVersion + 1, $scenario->product->fresh()->version);
        $this->assertSame(2500, $scenario->product->fresh()->purchase_price);

        $this->receive($scenario, $purchase, $item, 1)->assertRedirect();
        $this->assertSame(41, $this->stock($scenario));

        $this->receive($scenario, $purchase, $item, 2)->assertRedirect();
        $this->assertSame('recue', $purchase->fresh()->status);
        $this->assertSame(42, $this->stock($scenario));
        $this->assertNotNull($purchase->fresh()->received_at);

        $this->receive($scenario, $purchase, $item, 2)->assertForbidden();
        $this->assertSame(42, $this->stock($scenario));
    }

    public function test_reception_rejects_quantity_above_order_and_line_from_another_purchase(): void
    {
        $scenario = PharmacyScenario::create();
        $purchase = $this->createPurchase($scenario, 2);
        $item = $purchase->items()->firstOrFail();
        $otherPurchase = $this->createPurchase($scenario, 1);
        $otherItem = $otherPurchase->items()->firstOrFail();

        $this->receive($scenario, $purchase, $item, 3)
            ->assertRedirect()
            ->assertSessionHasErrors('items');
        $this->assertSame(40, $this->stock($scenario));

        $this->receive($scenario, $purchase, $otherItem, 1)
            ->assertRedirect()
            ->assertSessionHasErrors('items');
        $this->assertSame(40, $this->stock($scenario));

        $this->assertSame(0, $item->fresh()->quantity_received);
        $this->assertSame(0, $otherItem->fresh()->quantity_received);
    }

    private function createPurchase(PharmacyScenario $scenario, int $quantity): Purchase
    {
        $this->actingAs($scenario->manager)
            ->post('/achats', [
                'supplier_id' => $scenario->supplier->id,
                'items' => [[
                    'product_id' => $scenario->product->id,
                    'quantity' => $quantity,
                    'unit_price' => 2500,
                ]],
            ])
            ->assertRedirect();

        $purchase = Purchase::query()->latest('id')->firstOrFail();
        $this->actingAs($scenario->manager)
            ->post("/achats/{$purchase->id}/status", ['status' => 'envoyee'])
            ->assertRedirect();

        return $purchase->fresh();
    }

    private function receive(PharmacyScenario $scenario, Purchase $purchase, PurchaseItem $item, int $quantity)
    {
        return $this->actingAs($scenario->manager)
            ->post("/achats/{$purchase->id}/receive", [
                'items' => [['id' => $item->id, 'quantity_received' => $quantity]],
            ]);
    }

    private function stock(PharmacyScenario $scenario): int
    {
        return (int) StockLevel::query()
            ->where('pharmacy_id', $scenario->pharmacyA->id)
            ->where('product_id', $scenario->product->id)
            ->value('quantity');
    }
}
