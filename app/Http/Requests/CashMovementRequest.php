<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_cash') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:entree,retrait'],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
