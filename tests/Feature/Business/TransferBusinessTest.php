<?php

namespace Tests\Feature\Business;

use App\Models\StockLevel;
use App\Models\Transfer;
use App\Models\TransferItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class TransferBusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_shipping_and_reception_move_stock_only_once(): void
    {
        $scenario = PharmacyScenario::create();
        $transfer = $this->createTransfer($scenario, 2);
        $item = $transfer->items()->firstOrFail();

        $this->ship($scenario, $transfer, $item, 1)
            ->assertRedirect();
        $this->assertSame(39, $this->stock($scenario, $scenario->pharmacyA->id));
        $this->assertSame('partiellement_expedie', $transfer->fresh()->status);

        $this->actingAs($scenario->manager)
            ->post("/transfers/{$transfer->id}/ship", [])
            ->assertRedirect();
        $this->assertSame(38, $this->stock($scenario, $scenario->pharmacyA->id));
        $this->assertSame('expedie', $transfer->fresh()->status);

        $this->actingAs($scenario->managerB)
            ->post("/transfers/{$transfer->id}/receive", [
                'items' => [['id' => $item->id, 'quantity_received' => 1]],
            ])
            ->assertRedirect();
        $this->assertSame(4, $this->stock($scenario, $scenario->pharmacyB->id));
        $this->assertSame('partiellement_recu', $transfer->fresh()->status);

        $this->actingAs($scenario->managerB)
            ->post("/transfers/{$transfer->id}/receive", [
                'items' => [['id' => $item->id, 'quantity_received' => 2]],
            ])
            ->assertRedirect();
        $this->assertSame(5, $this->stock($scenario, $scenario->pharmacyB->id));
        $this->assertSame('recu', $transfer->fresh()->status);
        $this->assertSame(38, $this->stock($scenario, $scenario->pharmacyA->id));
    }

    public function test_cancellation_restocks_only_what_was_shipped_but_not_received(): void
    {
        $scenario = PharmacyScenario::create();
        $transfer = $this->createTransfer($scenario, 2);
        $item = $transfer->items()->firstOrFail();

        $this->ship($scenario, $transfer, $item, 1)->assertRedirect();
        $this->assertSame(39, $this->stock($scenario, $scenario->pharmacyA->id));

        $this->actingAs($scenario->manager)
            ->post("/transfers/{$transfer->id}/cancel")
            ->assertRedirect();

        $this->assertSame('annule', $transfer->fresh()->status);
        $this->assertSame(40, $this->stock($scenario, $scenario->pharmacyA->id));
        $this->assertSame(3, $this->stock($scenario, $scenario->pharmacyB->id));
    }

    public function test_reception_cannot_exceed_shipped_quantity_or_use_another_line(): void
    {
        $scenario = PharmacyScenario::create();
        $transfer = $this->createTransfer($scenario, 2);
        $item = $transfer->items()->firstOrFail();
        $otherTransfer = $this->createTransfer($scenario, 1);
        $otherItem = $otherTransfer->items()->firstOrFail();

        $this->ship($scenario, $transfer, $item, 1)->assertRedirect();
        $this->ship($scenario, $otherTransfer, $otherItem, 1)->assertRedirect();

        $this->actingAs($scenario->managerB)
            ->post("/transfers/{$transfer->id}/receive", [
                'items' => [['id' => $item->id, 'quantity_received' => 2]],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('items');
        $this->assertSame(3, $this->stock($scenario, $scenario->pharmacyB->id));

        $this->actingAs($scenario->managerB)
            ->post("/transfers/{$transfer->id}/receive", [
                'items' => [['id' => $otherItem->id, 'quantity_received' => 1]],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('items');
        $this->assertSame(3, $this->stock($scenario, $scenario->pharmacyB->id));
    }

    private function createTransfer(PharmacyScenario $scenario, int $quantity): Transfer
    {
        $this->actingAs($scenario->admin)
            ->post('/transfers', [
                'from_pharmacy_id' => $scenario->pharmacyA->id,
                'to_pharmacy_id' => $scenario->pharmacyB->id,
                'items' => [['product_id' => $scenario->product->id, 'quantity' => $quantity]],
            ])
            ->assertRedirect();

        return Transfer::query()->latest('id')->firstOrFail();
    }

    private function ship(PharmacyScenario $scenario, Transfer $transfer, TransferItem $item, int $quantity)
    {
        return $this->actingAs($scenario->manager)
            ->post("/transfers/{$transfer->id}/ship", [
                'items' => [['id' => $item->id, 'quantity_shipped' => $quantity]],
            ]);
    }

    private function stock(PharmacyScenario $scenario, int $pharmacyId): int
    {
        return (int) StockLevel::query()
            ->where('pharmacy_id', $pharmacyId)
            ->where('product_id', $scenario->product->id)
            ->value('quantity');
    }
}
