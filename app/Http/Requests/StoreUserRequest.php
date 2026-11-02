<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_users') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'pharmacies' => ['required', 'array', 'min:1'],
            'pharmacies.*.id' => ['required', 'integer', 'distinct', 'exists:pharmacies,id'],
            'pharmacies.*.role' => ['required', 'string', Rule::in(User::ROLES)],
        ];
    }
}
