<?php

namespace Tests\Feature\Security;

use App\Models\Transfer;
use App\Models\TransferItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class TransferActionFlagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_action_flags_follow_policies_for_each_pharmacy(): void
    {
        $scenario = PharmacyScenario::create();

        $transfer = Transfer::create([
            'reference' => 'TRF-FLAGS-1',
            'from_pharmacy_id' => $scenario->pharmacyA->id,
            'to_pharmacy_id' => $scenario->pharmacyB->id,
            'status' => 'expedie',
            'requested_by' => $scenario->manager->id,
            'shipped_at' => now(),
        ]);
        TransferItem::create([
            'transfer_id' => $transfer->id,
            'product_id' => $scenario->product->id,
            'quantity_requested' => 2,
            'quantity_shipped' => 2,
            'quantity_received' => 0,
        ]);

        $this->actingAs($scenario->manager);
        $transferA = collect($this->get('/transfers')->assertOk()->inertiaProps('transfers'))
            ->firstWhere('id', $transfer->id);
        $this->assertFalse($transferA['can_ship']);
        $this->assertFalse($transferA['can_receive']);
        $this->assertTrue($transferA['can_cancel']);

        $this->actingAs($scenario->managerB);
        $transferB = collect($this->get('/transfers')->assertOk()->inertiaProps('transfers'))
            ->firstWhere('id', $transfer->id);
        $this->assertFalse($transferB['can_ship']);
        $this->assertTrue($transferB['can_receive']);
        $this->assertTrue($transferB['can_cancel']);
    }
}
