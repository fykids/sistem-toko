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
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'admin',
            'remember_token' => Str::random(10),
        ];
    }

    public function master(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'master',
        ]);
    }

    public function marketing(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'marketing',
        ]);
    }
}
