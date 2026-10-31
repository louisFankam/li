<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_stock') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('products', 'code')->ignore($product->id)],
            'barcode' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:190'],
            'generic_name' => ['nullable', 'string', 'max:190'],
            'dosage' => ['nullable', 'string', 'max:60'],
            'form' => ['nullable', 'string', 'max:60'],
            'laboratory' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'base_unit' => ['nullable', 'string', 'max:30'],
            'sale_unit' => ['nullable', 'string', 'max:30'],
            'conversion_factor' => ['nullable', 'integer', 'min:1'],
            'purchase_price' => ['required', 'integer', 'min:0'],
            'sale_price' => ['required', 'integer', 'min:0'],
            'alert_threshold' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
