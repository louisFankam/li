<?php
namespace Tests\Feature;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_decrements_stock(): void
    {
        $this->seed(\Database\Seeders\DemoSeeder::class);
        $u = User::first();
        $u->email_verified_at = now();
        $u->save();
        $ph = $u->pharmacies()->first();
        $product = Product::first();
        $sl = StockLevel::where('pharmacy_id',$ph->id)->where('product_id',$product->id)->first();
        $before = $sl->quantity;

        $res = $this->actingAs($u)->post('/vente', [
            'pharmacy_id' => $ph->id,
            'items' => [['product_id'=>$product->id,'quantity'=>2,'unit_price'=>$product->sale_price]],
            'payments' => [['method'=>'especes','amount'=>$product->sale_price*2]],
        ]);
        $res->assertStatus(302);
        $sl->refresh();
        $this->assertEquals($before - 2, $sl->quantity);
    }

    public function test_sale_blocked_if_insufficient_stock(): void
    {
        $this->seed(\Database\Seeders\DemoSeeder::class);
        $u = User::first();
        $u->email_verified_at = now();
        $u->save();
        $ph = $u->pharmacies()->first();
        $product = Product::where('code','IBU400')->first();
        $sl = StockLevel::where('pharmacy_id',$ph->id)->where('product_id',$product->id)->first();
        // stock 0 at pharmacy 0
        $this->assertEquals(0, $sl->quantity);
        $res = $this->actingAs($u)->post('/vente', [
            'pharmacy_id' => $ph->id,
            'items' => [['product_id'=>$product->id,'quantity'=>1,'unit_price'=>$product->sale_price]],
            'payments' => [['method'=>'especes','amount'=>$product->sale_price]],
        ]);
        $res->assertStatus(500); // transaction throws -> 500
    }
}
