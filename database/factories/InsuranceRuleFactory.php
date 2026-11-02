<?php

namespace Database\Factories;

use App\Models\InsuranceCompany;
use App\Models\InsuranceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InsuranceRule>
 */
class InsuranceRuleFactory extends Factory
{
    protected $model = InsuranceRule::class;

    public function definition(): array
    {
        return [
            'insurance_company_id' => InsuranceCompany::factory(),
            'type' => 'percentage',
            'value' => 80,
            'category_id' => null,
            'product_id' => null,
            'version' => 0,
        ];
    }
}
