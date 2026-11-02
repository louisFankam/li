<?php

namespace Tests\Feature\Sync;

use App\Models\Customer;
use App\Models\Device;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class SyncPullContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_pull_without_device_token_is_unauthorized(): void
    {
        $scenario = PharmacyScenario::create();
        $this->actingAs($scenario->sellerA)
            ->getJson('/api/sync/pull?since=0')
            ->assertUnauthorized();
    }

    public function test_snapshot_is_scoped_to_accessible_pharmacies_and_sets_a_cursor(): void
    {
        $scenario = PharmacyScenario::create();
        $token = $this->device($scenario->sellerA, $scenario->pharmacyA->id);

        $response = $this->getJson($this->pullUrl($token, 0))->assertOk();
        $this->assertSame('snapshot', $response->json('mode'));
        $this->assertFalse($response->json('has_more'));

        $records = collect($response->json('records'));
        $product = $records->firstWhere('entity_type', 'product');
        $this->assertTrue($product['payload']['is_active']);

        $stockPharmacyIds = $records->where('entity_type', 'stock_level')->pluck('pharmacy_id')->unique()->values();
        $this->assertSame([$scenario->pharmacyA->id], $stockPharmacyIds->all());
        $this->assertGreaterThan(0, $response->json('next_cursor'));
        $this->assertSame(50, $response->json('settings.discounts.absolute_max_percent'));
    }

    public function test_new_record_with_low_version_is_delivered_after_high_cursor(): void
    {
        $scenario = PharmacyScenario::create();
        $token = $this->device($scenario->sellerA, $scenario->pharmacyA->id);
        $cursor = (int) $this->getJson($this->pullUrl($token, 0))->json('next_cursor');

        $created = Product::factory()->create(['code' => 'NEW-LOW-VERSION']);

        $response = $this->getJson($this->pullUrl($token, $cursor, 100))->assertOk();
        $records = collect($response->json('records'));
        $record = $records->first(fn (array $r) => $r['entity_type'] === 'product' && $r['payload']['id'] === $created->id);

        $this->assertNotNull($record);
        $this->assertGreaterThan($cursor, $response->json('next_cursor'));
    }

    public function test_changes_are_paginated_without_loss_or_duplicates(): void
    {
        $scenario = PharmacyScenario::create();
        $token = $this->device($scenario->sellerA, $scenario->pharmacyA->id);
        $cursor = (int) $this->getJson($this->pullUrl($token, 0))->json('next_cursor');

        $created = collect([
            Customer::factory()->create(['name' => 'Pagination A']),
            Customer::factory()->create(['name' => 'Pagination B']),
            Customer::factory()->create(['name' => 'Pagination C']),
        ]);

        $seen = [];
        $current = $cursor;
        $pages = 0;

        do {
            $response = $this->getJson($this->pullUrl($token, $current, 1))->assertOk();
            $records = collect($response->json('records'));

            foreach ($records as $record) {
                if ($record['entity_type'] === 'customer') {
                    $seen[] = $record['payload']['id'];
                }
            }

            $current = (int) $response->json('next_cursor');
            $pages++;
        } while ($response->json('has_more') && $pages < 10);

        sort($seen);
        $expected = $created->pluck('id')->sort()->values()->all();
        $this->assertSame($expected, $seen);
        $this->assertSame(3, $pages);
    }

    public function test_deactivation_and_deletion_are_propagated_as_changes(): void
    {
        $scenario = PharmacyScenario::create();
        $token = $this->device($scenario->sellerA, $scenario->pharmacyA->id);
        $cursor = (int) $this->getJson($this->pullUrl($token, 0))->json('next_cursor');

        $scenario->product->update(['is_active' => false]);
        $scenario->customer->delete();

        $records = collect($this->getJson($this->pullUrl($token, $cursor, 100))->json('records'));

        $product = $records->first(fn (array $r) => $r['entity_type'] === 'product' && $r['payload']['id'] === $scenario->product->id);
        $customer = $records->first(fn (array $r) => $r['entity_type'] === 'customer' && $r['payload']['id'] === $scenario->customer->id);

        $this->assertSame('upsert', $product['operation']);
        $this->assertFalse($product['payload']['is_active']);
        $this->assertSame('delete', $customer['operation']);
    }

    public function test_stock_changes_from_another_pharmacy_are_not_delivered(): void
    {
        $scenario = PharmacyScenario::create();
        $token = $this->device($scenario->sellerA, $scenario->pharmacyA->id);
        $cursor = (int) $this->getJson($this->pullUrl($token, 0))->json('next_cursor');

        StockLevel::query()
            ->where('pharmacy_id', $scenario->pharmacyB->id)
            ->where('product_id', $scenario->product->id)
            ->firstOrFail()
            ->update(['quantity' => 99]);

        $records = collect($this->getJson($this->pullUrl($token, $cursor, 100))->json('records'));
        $stockRecord = $records->first(fn (array $r) => $r['entity_type'] === 'stock_level');

        $this->assertNull($stockRecord);
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

    private function pullUrl(string $token, int $since, int $limit = 100)
    {
        $this->withHeader('X-Device-Token', $token);

        return '/api/sync/pull?'.http_build_query([
            'since' => $since,
            'limit' => $limit,
        ]);
    }
}
