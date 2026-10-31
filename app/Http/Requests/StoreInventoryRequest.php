<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryRequest extends FormRequest
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
            'counts' => ['required', 'array', 'min:1'],
            'counts.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'counts.*.counted' => ['required', 'integer', 'min:0'],
        ];
    }
}
