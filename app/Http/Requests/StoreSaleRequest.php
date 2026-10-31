<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('make_sales') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pharmacy_id' => ['nullable', 'integer', 'exists:pharmacies,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'max:30'],
            'payments.*.amount' => ['required', 'integer', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'insurance_company_id' => ['nullable', 'integer', 'exists:insurance_companies,id'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'client_request_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Le panier est vide.',
            'items.*.quantity.min' => 'La quantité doit être au moins 1.',
            'payments.required' => 'Au moins un règlement est requis.',
            'discount_percent.max' => 'La remise ne peut pas dépasser 100 %.',
        ];
    }
}
