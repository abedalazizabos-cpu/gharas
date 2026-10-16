<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '05'.fake()->unique()->numerify('########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password123'),
            'role' => User::ROLE_PARENT,
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => User::ROLE_ADMIN]);
    }

    public function superAdmin(): static
    {
        return $this->state(['role' => User::ROLE_SUPERADMIN]);
    }

    public function teacher(): static
    {
        return $this->state(['role' => User::ROLE_TEACHER]);
    }

    public function parent(): static
    {
        return $this->state(['role' => User::ROLE_PARENT]);
    }

    public function visitor(): static
    {
        return $this->state(['role' => User::ROLE_VISITOR]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }
}
