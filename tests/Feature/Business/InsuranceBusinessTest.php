<?php

namespace Tests\Feature\Business;

use App\Models\InsuranceClaim;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class InsuranceBusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_follows_valid_transitions_and_bounds_reimbursement(): void
    {
        $scenario = PharmacyScenario::create();
        $claim = $this->insuredClaim($scenario);

        $this->actingAs($scenario->manager)
            ->post("/assurances/{$claim->id}/status", ['status' => 'rembourse', 'reimbursed_amount' => 8000])
            ->assertRedirect()
            ->assertSessionHasErrors('status');
        $this->assertSame('a_facturer', $claim->fresh()->status);

        $this->actingAs($scenario->manager)
            ->post("/assurances/{$claim->id}/status", ['status' => 'envoye', 'reference' => 'BOR-001'])
            ->assertRedirect();
        $this->assertSame('envoye', $claim->fresh()->status);

        $this->actingAs($scenario->manager)
            ->post("/assurances/{$claim->id}/status", ['status' => 'rembourse', 'reimbursed_amount' => 9000])
            ->assertRedirect()
            ->assertSessionHasErrors('reimbursed_amount');
        $this->assertSame('envoye', $claim->fresh()->status);

        $this->actingAs($scenario->manager)
            ->post("/assurances/{$claim->id}/status", ['status' => 'rembourse', 'reimbursed_amount' => 8000])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('rembourse', $claim->status);
        $this->assertSame(8000, $claim->reimbursed_amount);
        $this->assertNotNull($claim->reimbursed_at);

        $this->actingAs($scenario->manager)
            ->post("/assurances/{$claim->id}/status", ['status' => 'annule'])
            ->assertRedirect()
            ->assertSessionHasErrors('status');
    }

    public function test_claim_can_be_rejected_after_being_sent(): void
    {
        $scenario = PharmacyScenario::create();
        $claim = $this->insuredClaim($scenario);

        $this->actingAs($scenario->manager)
            ->post("/assurances/{$claim->id}/status", ['status' => 'envoye'])
            ->assertRedirect();

        $this->actingAs($scenario->manager)
            ->post("/assurances/{$claim->id}/status", [
                'status' => 'rejete',
                'reference' => 'REJ-001',
                'notes' => 'Justificatif manquant',
            ])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('rejete', $claim->status);
        $this->assertSame(0, $claim->reimbursed_amount);
    }

    private function insuredClaim(PharmacyScenario $scenario): InsuranceClaim
    {
        $this->actingAs($scenario->sellerA)
            ->post('/vente', [
                'pharmacy_id' => $scenario->pharmacyA->id,
                'client_request_id' => (string) Str::uuid(),
                'items' => [['product_id' => $scenario->product->id, 'quantity' => 2]],
                'insurance_company_id' => $scenario->insurer->id,
                'customer_id' => $scenario->customer->id,
                'payments' => [['method' => 'especes', 'amount' => 2000]],
            ])
            ->assertRedirect();

        return InsuranceClaim::query()
            ->whereIn('sale_id', Sale::query()->select('id'))
            ->latest('id')
            ->firstOrFail();
    }
}
