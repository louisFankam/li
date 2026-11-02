<?php

namespace Tests\Feature\Business;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class AuditTrailBusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_and_cash_actions_are_audited_with_actor_and_pharmacy(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->sellerA)
            ->post('/vente', [
                'pharmacy_id' => $scenario->pharmacyA->id,
                'client_request_id' => (string) Str::uuid(),
                'items' => [['product_id' => $scenario->product->id, 'quantity' => 1]],
                'payments' => [['method' => 'especes', 'amount' => 5000]],
            ])
            ->assertRedirect();

        $saleLog = AuditLog::query()->where('action', 'sale.created')->firstOrFail();
        $this->assertSame($scenario->sellerA->id, $saleLog->user_id);
        $this->assertSame($scenario->pharmacyA->id, $saleLog->pharmacy_id);

        $this->actingAs($scenario->manager)
            ->post('/caisses/open', ['opening_amount' => 10000])
            ->assertRedirect();

        $cashLog = AuditLog::query()->where('action', 'cash.opened')->firstOrFail();
        $this->assertSame($scenario->manager->id, $cashLog->user_id);
        $this->assertSame($scenario->pharmacyA->id, $cashLog->pharmacy_id);
    }
}
