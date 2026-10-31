<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_inventory') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'type' => ['required', 'in:perte,casse,correction'],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
