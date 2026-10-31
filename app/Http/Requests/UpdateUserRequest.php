<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($target->id)],
            'pharmacies' => ['required', 'array', 'min:1'],
            'pharmacies.*.id' => ['required', 'integer', 'exists:pharmacies,id'],
            'pharmacies.*.role' => ['required', 'string', Rule::in(User::ROLES)],
        ];
    }
}
