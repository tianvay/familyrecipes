<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * Passwort ist beim Bearbeiten optional – leer lassen behält das bisherige.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'username' => ['required', 'string', 'alpha_dash:ascii', 'max:50', Rule::unique(User::class)->ignore($this->route('user'))],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $editsSelf = $this->route('user')->is($this->user());

                if ($editsSelf && $this->enum('role', Role::class) !== Role::Admin) {
                    $validator->errors()->add('role', 'Du kannst dir selbst die Rolle Familienoberhaupt nicht entziehen.');
                }
            },
        ];
    }
}
