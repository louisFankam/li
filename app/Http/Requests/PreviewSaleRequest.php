<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewSaleRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'insurance_company_id' => ['nullable', 'integer', 'exists:insurance_companies,id'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
