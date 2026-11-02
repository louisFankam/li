<?php

namespace Tests\Feature\Sync;

use App\Models\Device;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\SyncConflict;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class SyncPushContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_event_and_missing_permission_are_rejected_without_side_effects(): void
    {
        $scenario = PharmacyScenario::create();
        $token = $this->device($scenario->sellerA, $scenario->pharmacyA->id);

        $unknown = $this->event('unknown.event', [], $scenario->pharmacyA->id);
        $response = $this->push($token, [$unknown])->assertOk();
        $this->assertSame('rejected', $response->json('results.0.status'));
        $this->assertDatabaseHas('sync_events', [
            'client_event_id' => $unknown['client_event_id'],
            'status' => 'rejected',
        ]);

        $stock = $this->event('stock.adjusted', [
            'product_id' => $scenario->product->id,
            'type' => 'correction',
            'delta' => 1,
        ], $scenario->pharmacyA->id);
        $this->assertSame('rejected', $this->push($token, [$stock])->json('results.0.status'));
        $this->assertDatabaseCount('stock_movements', 0);

        $regionalToken = $this->device($scenario->regional, $scenario->pharmacyA->id);
        $cash = $this->event('cash.opened', ['opening_amount' => 10000], $scenario->pharmacyA->id);
        $this->assertSame('rejected', $this->push($regionalToken, [$cash])->json('results.0.status'));
        $this->assertDatabaseCount('cash_sessions', 0);
    }

    public function test_replay_is_idempotent_and_altered_payload_is_rejected(): void
    {
        $scenario = PharmacyScenario::create();
        $token = $this->device($scenario->manager, $scenario->pharmacyA->id);
        $event = $this->event('stock.adjusted', [
            'product_id' => $scenario->product->id,
            'type' => 'correction',
            'delta' => 2,
            'reason' => 'Test idempotence',
        ], $scenario->pharmacyA->id);

        $this->assertSame('applied', $this->push($token, [$event])->json('results.0.status'));
        $this->assertSame(42, $this->stock($scenario));
        $this->assertSame(1, StockMovement::query()->count());

        $this->assertSame('duplicate', $this->push($token, [$event])->json('results.0.status'));
        $this->assertSame(42, $this->stock($scenario));
        $this->assertSame(1, StockMovement::query()->count());

        $altered = $event;
        $altered['payload']['delta'] = 5;
        $response = $this->push($token, [$altered])->assertOk();

        $this->assertSame('rejected', $response->json('results.0.status'));
        $this->assertSame(42, $this->stock($scenario));
        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_cross_pharmacy_event_is_rejected_before_creation(): void
    {
        $scenario = PharmacyScenario::create();
        $token = $this->device($scenario->sellerA, $scenario->pharmacyA->id);
        $event = $this->event('stock.adjusted', [
            'product_id' => $scenario->product->id,
            'type' => 'correction',
            'delta' => 1,
        ], $scenario->pharmacyB->id);

        $this->assertSame('rejected', $this->push($token, [$event])->json('results.0.status'));
        $this->assertDatabaseMissing('sync_events', ['client_event_id' => $event['client_event_id']]);
    }

    public function test_cash_conflict_and_missing_sale_cancellation_are_explicit(): void
    {
        $scenario = PharmacyScenario::create();
        $token = $this->device($scenario->manager, $scenario->pharmacyA->id);

        $open = $this->event('cash.opened', ['opening_amount' => 10000], $scenario->pharmacyA->id);
        $this->assertSame('applied', $this->push($token, [$open])->json('results.0.status'));
        $this->assertDatabaseCount('cash_sessions', 1);

        $secondOpen = $this->event('cash.opened', ['opening_amount' => 5000], $scenario->pharmacyA->id);
        $response = $this->push($token, [$secondOpen])->assertOk();
        $this->assertSame('conflict', $response->json('results.0.status'));
        $this->assertDatabaseCount('cash_sessions', 1);

        $cancel = $this->event('sale.cancelled', [
            'client_request_id' => (string) Str::uuid(),
            'reason' => 'Vente locale absente',
        ], $scenario->pharmacyA->id);
        $this->assertSame('conflict', $this->push($token, [$cancel])->json('results.0.status'));

        $this->assertSame(2, SyncConflict::query()->where('status', 'open')->count());
        $this->assertDatabaseHas('sync_events', ['client_event_id' => $secondOpen['client_event_id'], 'status' => 'conflict']);
        $this->assertDatabaseHas('sync_events', ['client_event_id' => $cancel['client_event_id'], 'status' => 'conflict']);
    }

    public function test_sale_created_and_cancelled_events_use_server_services(): void
    {
        $scenario = PharmacyScenario::create();
        $sellerToken = $this->device($scenario->sellerA, $scenario->pharmacyA->id);
        $clientRequestId = (string) Str::uuid();

        $created = $this->event('sale.created', [
            'client_request_id' => $clientRequestId,
            'items' => [['product_id' => $scenario->product->id, 'quantity' => 1]],
            'payments' => [['method' => 'especes', 'amount' => 5000]],
        ], $scenario->pharmacyA->id);

        $result = $this->push($sellerToken, [$created])->json('results.0');
        $this->assertSame('applied', $result['status']);
        $this->assertGreaterThan(0, $result['sale']['id']);
        $this->assertNotEmpty($result['sale']['sale_number']);
        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(39, $this->stock($scenario));

        $duplicate = $this->push($sellerToken, [$created])->json('results.0');
        $this->assertSame('duplicate', $duplicate['status']);
        $this->assertSame($result['sale']['id'], $duplicate['sale']['id']);
        $this->assertDatabaseCount('sales', 1);

        $managerToken = $this->device($scenario->manager, $scenario->pharmacyA->id);
        $cancelled = $this->event('sale.cancelled', [
            'client_request_id' => $clientRequestId,
            'reason' => 'Annulation hors ligne',
        ], $scenario->pharmacyA->id);

        $this->assertSame('applied', $this->push($managerToken, [$cancelled])->json('results.0.status'));
        $this->assertSame('cancelled', Sale::query()->firstOrFail()->status);
        $this->assertSame(40, $this->stock($scenario));
    }

    public function test_inventory_event_applies_and_unknown_schema_is_rejected(): void
    {
        $scenario = PharmacyScenario::create();
        $token = $this->device($scenario->manager, $scenario->pharmacyA->id);

        $futureSchema = $this->event('stock.adjusted', [
            'product_id' => $scenario->product->id,
            'type' => 'correction',
            'delta' => 1,
        ], $scenario->pharmacyA->id);
        $futureSchema['schema_version'] = 2;

        $this->assertSame('rejected', $this->push($token, [$futureSchema])->json('results.0.status'));
        $this->assertSame(40, $this->stock($scenario));

        $inventory = $this->event('inventory.counted', [
            'counts' => [['product_id' => $scenario->product->id, 'counted' => 10]],
        ], $scenario->pharmacyA->id);

        $this->assertSame('applied', $this->push($token, [$inventory])->json('results.0.status'));
        $this->assertSame(10, $this->stock($scenario));
        $this->assertDatabaseHas('stock_movements', [
            'type' => 'inventaire',
            'quantity' => -30,
        ]);
    }

    public function test_invalid_payload_is_rejected_without_conflict_record(): void
    {
        $scenario = PharmacyScenario::create();
        $token = $this->device($scenario->manager, $scenario->pharmacyA->id);
        $event = $this->event('inventory.counted', ['counts' => []], $scenario->pharmacyA->id);

        $this->assertSame('rejected', $this->push($token, [$event])->json('results.0.status'));
        $this->assertDatabaseCount('sync_conflicts', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    private function push(string $token, array $events)
    {
        return $this->postJson('/api/sync/push', [
            'device_token' => $token,
            'events' => $events,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function event(string $type, array $payload, int $pharmacyId): array
    {
        return [
            'client_event_id' => (string) Str::uuid(),
            'type' => $type,
            'pharmacy_id' => $pharmacyId,
            'payload' => $payload,
            'schema_version' => 1,
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    private function device(User $user, int $pharmacyId): string
    {
        $token = Str::random(64);
        Device::create([
            'user_id' => $user->id,
            'pharmacy_id' => $pharmacyId,
            'name' => 'Test device',
            'token_hash' => hash('sha256', $token),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($user);

        return $token;
    }

    private function stock(PharmacyScenario $scenario): int
    {
        return (int) StockLevel::query()
            ->where('pharmacy_id', $scenario->pharmacyA->id)
            ->where('product_id', $scenario->product->id)
            ->value('quantity');
    }
}
