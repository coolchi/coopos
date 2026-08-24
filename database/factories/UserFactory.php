<?php

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'type' => UserType::Staff,
            'is_platform_admin' => false,
        ];
    }

    public function platform(): static
    {
        return $this->state(fn () => [
            'type' => UserType::Platform,
            'is_platform_admin' => true,
            'tenant_id' => null,
        ]);
    }

    public function agent(): static
    {
        return $this->state(fn () => [
            'type' => UserType::Platform,
            'is_platform_admin' => false,
            'tenant_id' => null,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
