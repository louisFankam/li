<?php

namespace App\Support;

class SyncEventTypes
{
    public const SCHEMA_VERSION = 1;

    /**
     * @return array{permission: string, pharmacy_required: bool, rules: array<string, mixed>}|null
     */
    public static function definition(string $type): ?array
    {
        $saleRules = [
            'client_request_id' => ['nullable', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'max:30'],
            'payments.*.amount' => ['required', 'integer', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'insurance_company_id' => ['nullable', 'integer', 'exists:insurance_companies,id'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
        ];

        return match ($type) {
            'sale.created' => [
                'permission' => Permissions::MAKE_SALES,
                'pharmacy_required' => true,
                'rules' => $saleRules,
            ],
            'sale.cancelled' => [
                'permission' => Permissions::CANCEL_SALE,
                'pharmacy_required' => true,
                'rules' => [
                    'client_request_id' => ['required', 'uuid'],
                    'reason' => ['required', 'string', 'min:3', 'max:500'],
                ],
            ],
            'cash.opened' => [
                'permission' => Permissions::MANAGE_CASH,
                'pharmacy_required' => true,
                'rules' => [
                    'opening_amount' => ['required', 'integer', 'min:0'],
                ],
            ],
            'cash.closed' => [
                'permission' => Permissions::MANAGE_CASH,
                'pharmacy_required' => true,
                'rules' => [
                    'session_id' => ['nullable', 'integer'],
                    'closing_amount' => ['required', 'integer', 'min:0'],
                    'notes' => ['nullable', 'string', 'max:1000'],
                ],
            ],
            'cash.movement' => [
                'permission' => Permissions::MANAGE_CASH,
                'pharmacy_required' => true,
                'rules' => [
                    'session_id' => ['nullable', 'integer'],
                    'type' => ['required', 'in:entree,retrait'],
                    'amount' => ['required', 'integer', 'min:1'],
                    'reason' => ['nullable', 'string', 'max:500'],
                ],
            ],
            'stock.adjusted' => [
                'permission' => Permissions::MANAGE_INVENTORY,
                'pharmacy_required' => true,
                'rules' => [
                    'product_id' => ['required', 'integer', 'exists:products,id'],
                    'type' => ['required', 'in:correction,perte,casse,inventaire'],
                    'delta' => ['required_without:quantity', 'nullable', 'integer', 'not_in:0'],
                    'quantity' => ['required_without:delta', 'nullable', 'integer', 'not_in:0'],
                    'reason' => ['nullable', 'string', 'max:500'],
                ],
            ],
            'inventory.counted' => [
                'permission' => Permissions::MANAGE_INVENTORY,
                'pharmacy_required' => true,
                'rules' => [
                    'counts' => ['required', 'array', 'min:1'],
                    'counts.*.product_id' => ['required', 'integer', 'exists:products,id'],
                    'counts.*.counted' => ['required', 'integer', 'min:0'],
                ],
            ],
            default => null,
        };
    }

    public static function supports(string $type): bool
    {
        return self::definition($type) !== null;
    }
}
