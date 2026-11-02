<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('MED-####')),
            'barcode' => fake()->unique()->ean13(),
            'name' => fake()->unique()->words(2, true),
            'generic_name' => fake()->optional()->word(),
            'dosage' => fake()->randomElement(['500 mg', '1 g', '250 mg']),
            'form' => fake()->randomElement(['comprimé', 'sirop', 'gélule']),
            'laboratory' => fake()->company(),
            'category_id' => null,
            'base_unit' => 'boite',
            'sale_unit' => 'boite',
            'conversion_factor' => 1,
            'purchase_price' => fake()->numberBetween(1000, 5000),
            'sale_price' => fake()->numberBetween(6000, 15000),
            'alert_threshold' => fake()->numberBetween(5, 20),
            'is_active' => true,
            'version' => 0,
        ];
    }
}
