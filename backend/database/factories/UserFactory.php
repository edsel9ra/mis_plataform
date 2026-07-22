<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = \App\Models\User::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'client_type' => 'personal',
            'role' => 'mentee',
            'name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'sex' => 'N',
            'birth_date' => fake()->date(),
            'locale' => 'es',
            'is_active' => true,
        ];
    }

    public function mentor(): static
    {
        return $this->state(fn(array $attr) => [
            'role' => 'mentor',
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn(array $attr) => [
            'role' => 'admin',
        ]);
    }
}
