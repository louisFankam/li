<?php

namespace Database\Factories;

use App\Models\Pharmacy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pharmacy>
 */
class PharmacyFactory extends Factory
{
    protected $model = Pharmacy::class;

    public function definition(): array
    {
        return [
            'name' => 'Pharmacie '.fake()->unique()->city(),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
