<?php

namespace Tests\Feature\Sync;

use App\Models\CashSession;
use App\Models\Device;
use App\Models\StockLevel;
use App\Models\SyncConflict;
use App\Models\SyncEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class SyncConflictResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_resolution_marks_event_rejected(): void
    {
        $scenario = PharmacyScenario::create();
        [$event, $managerToken] = $this->conflictingMovement($scenario);
        $conflict = SyncConflict::query()->where('sync_event_id', $event->id)->firstOrFail();
        $this->device($scenario->regional, $scenario->pharmacyA->id);

        $this->post("/sync/conflicts/{$conflict->id}/resolve", ['resolution' => 'cancelled'])
            ->assertRedirect();

        $this->assertSame('cancelled', $conflict->fresh()->status);
        $this->assertSame('rejected', $event->fresh()->status);
        $this->assertDatabaseCount('cash_movements', 0);

        $statuses = $this->withHeader('X-Device-Token', $managerToken)
            ->getJson('/api/sync/pull?since=0&events='.urlencode($event->client_event_id))
            ->assertOk()
            ->json('event_statuses');
        $this->assertSame('rejected', $statuses[$event->client_event_id]);
    }

    public function test_applied_resolution_replays_the_original_event(): void
    {
        $scenario = PharmacyScenario::create();
        [$event] = $this->conflictingMovement($scenario);
        $conflict = SyncConflict::query()->where('sync_event_id', $event->id)->firstOrFail();

        $this->actingAs($scenario->manager)
            ->post('/caisses/open', ['opening_amount' => 10000])
            ->assertRedirect();
        $session = CashSession::query()->firstOrFail();

        $this->device($scenario->regional, $scenario->pharmacyA->id);
        $this->post("/sync/conflicts/{$conflict->id}/resolve", ['resolution' => 'applied'])
            ->assertRedirect();

        $this->assertSame('applied', $conflict->fresh()->status);
        $this->assertSame('applied', $event->fresh()->status);
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $session->id,
            'type' => 'entree',
            'amount' => 500,
        ]);
    }

    public function test_adjusted_resolution_replaces_payload_and_applies_it(): void
    {
        $scenario = PharmacyScenario::create();
        $managerToken = $this->device($scenario->manager, $scenario->pharmacyA->id);
        $event = $this->push($managerToken, 'stock.adjusted', [
            'product_id' => $scenario->product->id,
            'type' => 'correction',
            'delta' => -1000,
        ], $scenario->pharmacyA->id);
        $conflict = SyncConflict::query()->where('sync_event_id', $event->id)->firstOrFail();
        $this->assertSame('conflict', $event->fresh()->status);
        $this->assertSame(40, $this->stock($scenario));

        $this->device($scenario->regional, $scenario->pharmacyA->id);
        $this->post("/sync/conflicts/{$conflict->id}/resolve", [
            'resolution' => 'adjusted',
            'payload' => [
                'product_id' => $scenario->product->id,
                'type' => 'correction',
                'delta' => 1,
                'reason' => 'Ajustement validé par le responsable',
            ],
        ])->assertRedirect();

        $this->assertSame('adjusted', $conflict->fresh()->status);
        $this->assertSame('applied', $event->fresh()->status);
        $this->assertSame(41, $this->stock($scenario));
        $this->assertSame(1, (int) $event->fresh()->payload['delta']);
    }

    /**
     * @return array{0: SyncEvent, 1: string}
     */
    private function conflictingMovement(PharmacyScenario $scenario): array
    {
        $token = $this->device($scenario->manager, $scenario->pharmacyA->id);

        return [
            $this->push($token, 'cash.movement', [
                'type' => 'entree',
                'amount' => 500,
                'reason' => 'Mouvement hors ligne',
            ], $scenario->pharmacyA->id),
            $token,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function push(string $token, string $type, array $payload, int $pharmacyId): SyncEvent
    {
        $clientEventId = (string) Str::uuid();
        $response = $this->postJson('/api/sync/push', [
            'device_token' => $token,
            'events' => [[
                'client_event_id' => $clientEventId,
                'type' => $type,
                'pharmacy_id' => $pharmacyId,
                'payload' => $payload,
                'schema_version' => 1,
                'occurred_at' => now()->toIso8601String(),
            ]],
        ])->assertOk();

        $this->assertSame('conflict', $response->json('results.0.status'));

        return SyncEvent::query()->where('client_event_id', $clientEventId)->firstOrFail();
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
