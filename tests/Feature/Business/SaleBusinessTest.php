<?php

namespace Tests\Feature\Business;

use App\Models\InsuranceClaim;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class SaleBusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_recomputes_prices_and_accepts_mixed_payments(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->sellerA)
            ->post('/vente', $this->payload($scenario, [
                'items' => [[
                    'product_id' => $scenario->product->id,
                    'quantity' => 2,
                    'unit_price' => 1,
                ]],
                'payments' => [
                    ['method' => 'especes', 'amount' => 3000],
                    ['method' => 'mobile_money', 'amount' => 7000],
                ],
            ]))
            ->assertRedirect();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame(10000, $sale->total);
        $this->assertSame(10000, $sale->client_part);
        $this->assertSame(1, $sale->items()->count());
        $this->assertSame(5000, (int) SaleItem::query()->value('unit_price'));
        $this->assertSame(2, Payment::query()->where('sale_id', $sale->id)->count());
    }

    public function test_same_client_request_id_creates_only_one_sale_and_one_stock_movement(): void
    {
        $scenario = PharmacyScenario::create();
        $payload = $this->payload($scenario, [
            'items' => [['product_id' => $scenario->product->id, 'quantity' => 2]],
            'payments' => [['method' => 'especes', 'amount' => 10000]],
        ]);

        $this->actingAs($scenario->sellerA)->post('/vente', $payload)->assertRedirect();
        $this->actingAs($scenario->sellerA)->post('/vente', $payload)->assertRedirect();

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(38, $this->stock($scenario));
        $this->assertSame(2, (int) Sale::query()->firstOrFail()->items()->sum('quantity'));
    }

    public function test_payment_total_must_match_the_client_part(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->sellerA)
            ->post('/vente', $this->payload($scenario, [
                'payments' => [['method' => 'especes', 'amount' => 4000]],
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('payments');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(40, $this->stock($scenario));
    }

    public function test_discount_above_role_cap_requires_an_approver(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->sellerA)
            ->post('/vente', $this->payload($scenario, [
                'discount_percent' => 10,
                'discount_reason' => 'Geste commercial',
                'payments' => [['method' => 'especes', 'amount' => 9000]],
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('discount');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(40, $this->stock($scenario));
    }

    public function test_manager_can_approve_discount_within_absolute_limit_but_not_beyond_it(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->manager)
            ->post('/vente', $this->payload($scenario, [
                'discount_percent' => 20,
                'discount_reason' => 'Geste commercial',
                'payments' => [['method' => 'especes', 'amount' => 4000]],
            ]))
            ->assertRedirect();

        $this->assertDatabaseCount('sales', 1);

        $this->actingAs($scenario->manager)
            ->post('/vente', $this->payload($scenario, [
                'discount_percent' => 60,
                'discount_reason' => 'Remise excessive',
                'payments' => [['method' => 'especes', 'amount' => 4000]],
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('discount');

        $this->assertDatabaseCount('sales', 1);
    }

    public function test_fixed_discount_cannot_make_the_sale_free(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->manager)
            ->post('/vente', $this->payload($scenario, [
                'discount_amount' => 5000,
                'discount_reason' => 'Remise totale',
                'payments' => [['method' => 'especes', 'amount' => 0]],
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('discount');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_insurance_splits_the_sale_and_creates_a_claim(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->sellerA)
            ->post('/vente', $this->payload($scenario, [
                'items' => [['product_id' => $scenario->product->id, 'quantity' => 2]],
                'insurance_company_id' => $scenario->insurer->id,
                'customer_id' => $scenario->customer->id,
                'payments' => [['method' => 'especes', 'amount' => 2000]],
            ]))
            ->assertRedirect();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame(2000, $sale->client_part);
        $this->assertSame(8000, $sale->insurer_part);

        $claim = InsuranceClaim::query()->where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame('a_facturer', $claim->status);
        $this->assertSame(8000, $claim->insurer_part);
    }

    public function test_global_discount_is_shared_proportionally_with_the_insurer(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->manager)
            ->post('/vente', $this->payload($scenario, [
                'insurance_company_id' => $scenario->insurer->id,
                'customer_id' => $scenario->customer->id,
                'discount_percent' => 20,
                'discount_reason' => 'Geste commercial',
                'payments' => [['method' => 'especes', 'amount' => 800]],
            ]))
            ->assertRedirect();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame(4000, $sale->total);
        $this->assertSame(3200, $sale->insurer_part);
        $this->assertSame(800, $sale->client_part);

        $claim = InsuranceClaim::query()->where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame(3200, $claim->insurer_part);
        $this->assertSame(800, $claim->client_part);
    }

    public function test_cancellation_restocks_once_and_cancels_the_claim(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->sellerA)
            ->post('/vente', $this->payload($scenario, [
                'items' => [['product_id' => $scenario->product->id, 'quantity' => 2]],
                'insurance_company_id' => $scenario->insurer->id,
                'customer_id' => $scenario->customer->id,
                'payments' => [['method' => 'especes', 'amount' => 2000]],
            ]))
            ->assertRedirect();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame(38, $this->stock($scenario));

        $this->actingAs($scenario->manager)
            ->post("/vente/{$sale->id}/cancel", ['reason' => 'Erreur de saisie'])
            ->assertRedirect();

        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertSame('annule', $sale->claim->fresh()->status);
        $this->assertSame(40, $this->stock($scenario));

        $this->actingAs($scenario->manager)
            ->post("/vente/{$sale->id}/cancel", ['reason' => 'Deuxième annulation'])
            ->assertForbidden();

        $this->assertSame(40, $this->stock($scenario));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(PharmacyScenario $scenario, array $overrides = []): array
    {
        return array_merge([
            'pharmacy_id' => $scenario->pharmacyA->id,
            'client_request_id' => (string) Str::uuid(),
            'items' => [['product_id' => $scenario->product->id, 'quantity' => 1]],
            'payments' => [['method' => 'especes', 'amount' => 5000]],
        ], $overrides);
    }

    private function stock(PharmacyScenario $scenario): int
    {
        return (int) StockLevel::query()
            ->where('pharmacy_id', $scenario->pharmacyA->id)
            ->where('product_id', $scenario->product->id)
            ->value('quantity');
    }
}
