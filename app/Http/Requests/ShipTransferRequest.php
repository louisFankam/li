<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShipTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_transfers') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required_with:items', 'integer', 'exists:transfer_items,id'],
            'items.*.quantity_shipped' => ['required_with:items', 'integer', 'min:0'],
        ];
    }
}
