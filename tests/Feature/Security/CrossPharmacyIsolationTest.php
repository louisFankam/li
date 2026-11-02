<?php

namespace Tests\Feature\Security;

use App\Models\CashSession;
use App\Models\InsuranceClaim;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\Transfer;
use App\Models\TransferItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class CrossPharmacyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_cannot_act_on_resources_owned_by_another_pharmacy(): void
    {
        $scenario = PharmacyScenario::create();

        $sale = Sale::create([
            'sale_number' => 'SALE-B-1',
            'pharmacy_id' => $scenario->pharmacyB->id,
            'user_id' => $scenario->sellerB->id,
            'subtotal' => 5000,
            'total' => 5000,
            'client_part' => 5000,
            'insurer_part' => 0,
            'status' => 'completed',
        ]);

        $cash = CashSession::create([
            'pharmacy_id' => $scenario->pharmacyB->id,
            'user_id' => $scenario->sellerB->id,
            'opening_amount' => 10000,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $purchase = Purchase::create([
            'supplier_id' => $scenario->supplier->id,
            'pharmacy_id' => $scenario->pharmacyB->id,
            'status' => 'envoyee',
            'total' => 5000,
        ]);
        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $scenario->product->id,
            'quantity_ordered' => 2,
            'quantity_received' => 0,
            'unit_price' => 2500,
        ]);

        $claim = InsuranceClaim::create([
            'sale_id' => $sale->id,
            'insurance_company_id' => $scenario->insurer->id,
            'total_amount' => 5000,
            'client_part' => 1000,
            'insurer_part' => 4000,
            'status' => 'a_facturer',
        ]);

        $transfer = Transfer::create([
            'reference' => 'TRF-B-1',
            'from_pharmacy_id' => $scenario->pharmacyA->id,
            'to_pharmacy_id' => $scenario->pharmacyB->id,
            'status' => 'expedie',
            'requested_by' => $scenario->manager->id,
            'shipped_at' => now(),
        ]);
        $transferItem = TransferItem::create([
            'transfer_id' => $transfer->id,
            'product_id' => $scenario->product->id,
            'quantity_requested' => 2,
            'quantity_shipped' => 2,
            'quantity_received' => 0,
        ]);

        $this->actingAs($scenario->manager);

        $this->get("/vente/{$sale->id}/ticket")->assertForbidden();
        $this->post("/vente/{$sale->id}/cancel", ['reason' => 'Test'])->assertForbidden();
        $this->post("/caisses/{$cash->id}/close", ['closing_amount' => 10000])->assertForbidden();
        $this->post("/caisses/{$cash->id}/movement", ['type' => 'entree', 'amount' => 1000])->assertForbidden();
        $this->post("/achats/{$purchase->id}/receive", [
            'items' => [['id' => $purchaseItem->id, 'quantity_received' => 1]],
        ])->assertForbidden();
        $this->post("/achats/{$purchase->id}/status", ['status' => 'annulee'])->assertForbidden();
        $this->post("/assurances/{$claim->id}/status", ['status' => 'envoye'])->assertForbidden();
        $this->post("/transfers/{$transfer->id}/receive", [
            'items' => [['id' => $transferItem->id, 'quantity_received' => 1]],
        ])->assertForbidden();
    }
}
