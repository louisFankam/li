<?php

namespace Tests\Feature\Security;

use App\Models\SyncConflict;
use App\Models\SyncEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class SyncConflictAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regional_manager_can_only_resolve_conflicts_from_his_pharmacy(): void
    {
        $scenario = PharmacyScenario::create();
        $conflictA = $this->conflict($scenario, $scenario->pharmacyA->id);
        $conflictB = $this->conflict($scenario, $scenario->pharmacyB->id);

        $this->actingAs($scenario->manager)->get('/sync/conflicts')->assertForbidden();

        $response = $this->actingAs($scenario->regional)->get('/sync/conflicts')->assertOk();
        $props = $response->inertiaProps();
        $this->assertCount(1, $props['conflicts']);
        $this->assertSame($conflictA->id, $props['conflicts'][0]['id']);

        $this->post("/sync/conflicts/{$conflictB->id}/resolve", ['resolution' => 'cancelled'])
            ->assertForbidden();

        $this->post("/sync/conflicts/{$conflictA->id}/resolve", ['resolution' => 'applied'])
            ->assertRedirect();

        $this->assertSame('applied', $conflictA->fresh()->status);
        $this->assertSame($scenario->regional->id, $conflictA->fresh()->resolved_by);
    }

    public function test_super_admin_can_resolve_any_conflict_without_pharmacy_pivot(): void
    {
        $scenario = PharmacyScenario::create();
        $conflictB = $this->conflict($scenario, $scenario->pharmacyB->id);

        $this->actingAs($scenario->admin)
            ->post("/sync/conflicts/{$conflictB->id}/resolve", ['resolution' => 'adjusted'])
            ->assertRedirect();

        $this->assertSame('adjusted', $conflictB->fresh()->status);
    }

    private function conflict(PharmacyScenario $scenario, int $pharmacyId): SyncConflict
    {
        $event = SyncEvent::create([
            'client_event_id' => (string) Str::uuid(),
            'pharmacy_id' => $pharmacyId,
            'user_id' => $scenario->sellerA->id,
            'type' => 'stock.adjusted',
            'payload' => ['product_id' => $scenario->product->id, 'delta' => -1],
            'schema_version' => 1,
            'occurred_at' => now(),
            'received_at' => now(),
            'status' => 'conflict',
        ]);

        return SyncConflict::create([
            'sync_event_id' => $event->id,
            'pharmacy_id' => $pharmacyId,
            'type' => 'stock.adjusted',
            'details' => ['error' => 'Stock insuffisant'],
            'status' => 'open',
        ]);
    }
}
