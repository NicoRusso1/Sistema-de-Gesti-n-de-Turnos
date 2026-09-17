<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Patient;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'role_id' => Role::where('name', RoleName::OperationalUser->value)->first()?->id
                ?? Role::factory(),
            'status' => 1,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}