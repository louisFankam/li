<?php

namespace Database\Factories;

use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockLevel>
 */
class StockLevelFactory extends Factory
{
    protected $model = StockLevel::class;

    public function definition(): array
    {
        return [
            'pharmacy_id' => Pharmacy::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(0, 100),
            'version' => 0,
        ];
    }
}
