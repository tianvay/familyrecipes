<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            // Punkte sind in Benutzernamen nicht erlaubt (alpha_dash)
            'username' => Str::lower(str_replace('.', '_', fake()->unique()->userName())),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Adult,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin]);
    }

    public function child(): static
    {
        return $this->state(['role' => Role::Child]);
    }
}
