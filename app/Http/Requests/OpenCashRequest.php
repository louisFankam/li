<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashRequest extends FormRequest
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
            'opening_amount' => ['required', 'integer', 'min:0'],
        ];
    }
}
