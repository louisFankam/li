<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\Customer;
use App\Models\InsuranceCompany;
use App\Models\InsuranceRule;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;

/**
 * Deterministic two-site fixture used by isolation and business tests.
 *
 * It deliberately does not depend on DemoSeeder so tests control every
 * pharmacy, role, product and stock quantity they exercise.
 */
final class PharmacyScenario
{
    public function __construct(
        public readonly Pharmacy $pharmacyA,
        public readonly Pharmacy $pharmacyB,
        public readonly User $admin,
        public readonly User $manager,
        public readonly User $managerB,
        public readonly User $regional,
        public readonly User $sellerA,
        public readonly User $sellerB,
        public readonly Category $category,
        public readonly Supplier $supplier,
        public readonly Product $product,
        public readonly InsuranceCompany $insurer,
        public readonly Customer $customer,
    ) {}

    public static function create(): self
    {
        $pharmacyA = Pharmacy::factory()->create(['name' => 'Pharmacie Site A']);
        $pharmacyB = Pharmacy::factory()->create(['name' => 'Pharmacie Site B']);

        $admin = User::factory()->create([
            'name' => 'Administrateur Test',
            'email' => 'lot0-admin@example.test',
            'is_super_admin' => true,
        ]);
        $manager = User::factory()->create(['name' => 'Gérant Site A', 'email' => 'lot0-manager@example.test']);
        $managerB = User::factory()->create(['name' => 'Gérant Site B', 'email' => 'lot0-manager-b@example.test']);
        $regional = User::factory()->create(['name' => 'Responsable régional', 'email' => 'lot0-regional@example.test']);
        $sellerA = User::factory()->create(['name' => 'Vendeur Site A', 'email' => 'lot0-seller-a@example.test']);
        $sellerB = User::factory()->create(['name' => 'Vendeur Site B', 'email' => 'lot0-seller-b@example.test']);

        $manager->pharmacies()->attach($pharmacyA->id, ['role' => 'gerant']);
        $managerB->pharmacies()->attach($pharmacyB->id, ['role' => 'gerant']);
        $regional->pharmacies()->attach($pharmacyA->id, ['role' => 'responsable_regional']);
        $sellerA->pharmacies()->attach($pharmacyA->id, ['role' => 'vendeur']);
        $sellerB->pharmacies()->attach($pharmacyB->id, ['role' => 'vendeur']);

        $category = Category::factory()->create(['name' => 'Antibiotiques']);
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'code' => 'LOT0-AMOX',
            'name' => 'Amoxicilline',
            'category_id' => $category->id,
            'sale_price' => 5000,
            'purchase_price' => 2500,
        ]);

        StockLevel::factory()->create([
            'pharmacy_id' => $pharmacyA->id,
            'product_id' => $product->id,
            'quantity' => 40,
        ]);
        StockLevel::factory()->create([
            'pharmacy_id' => $pharmacyB->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $insurer = InsuranceCompany::factory()->create(['name' => 'Assurance Test']);
        InsuranceRule::factory()->create([
            'insurance_company_id' => $insurer->id,
            'type' => 'percentage',
            'value' => 80,
        ]);
        $customer = Customer::factory()->create([
            'name' => 'Client Test',
            'insurance_company_id' => $insurer->id,
        ]);

        return new self(
            $pharmacyA,
            $pharmacyB,
            $admin,
            $manager,
            $managerB,
            $regional,
            $sellerA,
            $sellerB,
            $category,
            $supplier,
            $product,
            $insurer,
            $customer,
        );
    }
}
