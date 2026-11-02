<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\InsuranceCompany;
use App\Models\InsuranceRule;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\SyncChange;
use Illuminate\Database\Eloquent\Model;

class SyncChangeRecorder
{
    /**
     * @return array<string, mixed>|null
     */
    public function record(Model $model, string $operation): ?array
    {
        $type = $this->typeFor($model);

        if ($type === null) {
            return null;
        }

        $payload = $operation === 'delete'
            ? $this->deletionPayload($model)
            : $this->payload($model);

        SyncChange::create([
            'entity_type' => $type,
            'entity_id' => (int) $model->getKey(),
            'pharmacy_id' => $model instanceof StockLevel ? $model->pharmacy_id : null,
            'operation' => $operation,
            'payload' => $payload,
            'changed_at' => now(),
        ]);

        return $payload;
    }

    public function typeFor(Model $model): ?string
    {
        return match (true) {
            $model instanceof Product => 'product',
            $model instanceof Customer => 'customer',
            $model instanceof InsuranceCompany => 'insurer',
            $model instanceof InsuranceRule => 'insurance_rule',
            $model instanceof StockLevel => 'stock_level',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Model $model): array
    {
        return match (true) {
            $model instanceof Product => [
                'id' => $model->id,
                'code' => $model->code,
                'barcode' => $model->barcode,
                'name' => $model->name,
                'generic_name' => $model->generic_name,
                'form' => $model->form,
                'dosage' => $model->dosage,
                'laboratory' => $model->laboratory,
                'category_id' => $model->category_id,
                'base_unit' => $model->base_unit,
                'sale_unit' => $model->sale_unit,
                'conversion_factor' => $model->conversion_factor,
                'purchase_price' => $model->purchase_price,
                'sale_price' => $model->sale_price,
                'alert_threshold' => $model->alert_threshold,
                'is_active' => $model->is_active,
                'version' => $model->version,
            ],
            $model instanceof Customer => [
                'id' => $model->id,
                'name' => $model->name,
                'phone' => $model->phone,
                'email' => $model->email,
                'insurance_company_id' => $model->insurance_company_id,
                'version' => $model->version,
            ],
            $model instanceof InsuranceCompany => [
                'id' => $model->id,
                'name' => $model->name,
                'code' => $model->code,
                'is_active' => $model->is_active,
                'version' => $model->version,
            ],
            $model instanceof InsuranceRule => [
                'id' => $model->id,
                'insurance_company_id' => $model->insurance_company_id,
                'product_id' => $model->product_id,
                'category_id' => $model->category_id,
                'type' => $model->type,
                'value' => $model->value,
                'version' => $model->version,
            ],
            $model instanceof StockLevel => [
                'pharmacy_id' => $model->pharmacy_id,
                'product_id' => $model->product_id,
                'quantity' => $model->quantity,
                'version' => $model->version,
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function deletionPayload(Model $model): array
    {
        if ($model instanceof StockLevel) {
            return [
                'pharmacy_id' => $model->pharmacy_id,
                'product_id' => $model->product_id,
            ];
        }

        return ['id' => (int) $model->getKey()];
    }
}
