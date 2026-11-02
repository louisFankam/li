<?php

namespace Tests\Feature\Business;

use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\PharmacyScenario;
use Tests\TestCase;

class ConcurrentSaleTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_cashiers_cannot_sell_the_same_last_item(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Le test de concurrence nécessite MySQL.');
        }

        $scenario = PharmacyScenario::create();
        $stock = StockLevel::query()
            ->where('pharmacy_id', $scenario->pharmacyA->id)
            ->where('product_id', $scenario->product->id)
            ->firstOrFail();
        $stock->update(['quantity' => 1]);

        $startAt = microtime(true) + 2;
        $processes = [
            $this->saleProcess($scenario, Str::uuid()->toString(), $startAt),
            $this->saleProcess($scenario, Str::uuid()->toString(), $startAt),
        ];

        foreach ($processes as $process) {
            $process->start();
        }

        foreach ($processes as $process) {
            $process->wait();
        }

        $outputs = array_map(fn (Process $process) => trim($process->getOutput()), $processes);
        $errors = array_map(fn (Process $process) => trim($process->getErrorOutput()), $processes);

        $this->assertSame(1, count(array_filter($outputs, fn (string $output) => $output === 'OK')), implode("\n", array_merge($outputs, $errors)));
        $this->assertSame(1, count(array_filter($outputs, fn (string $output) => str_contains($output, 'Stock insuffisant'))), implode("\n", array_merge($outputs, $errors)));

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(0, (int) $stock->fresh()->quantity);
        $this->assertSame(1, StockMovement::query()->where('type', 'vente')->count());
    }

    private function saleProcess(PharmacyScenario $scenario, string $clientRequestId, float $startAt): Process
    {
        $process = new Process([
            PHP_BINARY,
            base_path('tests/Support/concurrent_sale.php'),
            (string) $scenario->sellerA->id,
            (string) $scenario->pharmacyA->id,
            $clientRequestId,
            (string) $startAt,
            (string) $scenario->product->id,
        ], base_path());

        $process->setEnv([
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => 'pharmagest_test',
        ]);

        return $process;
    }
}
