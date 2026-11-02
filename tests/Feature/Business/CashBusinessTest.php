<?php

namespace Tests\Feature\Business;

use App\Http\Controllers\CashController;
use App\Models\CashSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class CashBusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_expected_cash_only_counts_cash_payments_and_movements(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->manager)
            ->post('/caisses/open', ['opening_amount' => 10000])
            ->assertRedirect();

        $this->actingAs($scenario->sellerA)
            ->post('/vente', [
                'pharmacy_id' => $scenario->pharmacyA->id,
                'client_request_id' => (string) Str::uuid(),
                'items' => [['product_id' => $scenario->product->id, 'quantity' => 2]],
                'payments' => [
                    ['method' => 'especes', 'amount' => 3000],
                    ['method' => 'mobile_money', 'amount' => 7000],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($scenario->manager)
            ->post('/caisses/'.CashSession::query()->value('id').'/movement', [
                'type' => 'entree',
                'amount' => 500,
                'reason' => 'Fond supplémentaire',
            ])
            ->assertRedirect();

        $this->actingAs($scenario->manager)
            ->post('/caisses/'.CashSession::query()->value('id').'/movement', [
                'type' => 'retrait',
                'amount' => 200,
                'reason' => 'Dépense',
            ])
            ->assertRedirect();

        $session = CashSession::query()->firstOrFail();
        $expected = app(CashController::class)->expectedAmount($session);
        $this->assertSame(13300, $expected);

        $this->actingAs($scenario->manager)
            ->post("/caisses/{$session->id}/close", [
                'closing_amount' => 13300,
                'notes' => 'Clôture conforme',
            ])
            ->assertRedirect();

        $session->refresh();
        $this->assertSame('closed', $session->status);
        $this->assertSame(13300, $session->expected_amount);
        $this->assertSame(0, $session->difference);

        $this->actingAs($scenario->manager)
            ->post("/caisses/{$session->id}/movement", [
                'type' => 'entree',
                'amount' => 100,
                'reason' => 'Après clôture',
            ])
            ->assertForbidden();
    }

    public function test_second_open_is_rejected_for_the_same_pharmacy(): void
    {
        $scenario = PharmacyScenario::create();

        $this->actingAs($scenario->manager)
            ->post('/caisses/open', ['opening_amount' => 10000])
            ->assertRedirect();

        $this->actingAs($scenario->manager)
            ->post('/caisses/open', ['opening_amount' => 5000])
            ->assertRedirect()
            ->assertSessionHasErrors('opening_amount');

        $this->assertDatabaseCount('cash_sessions', 1);
    }
}
