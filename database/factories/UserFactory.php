<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Department;
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
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'department_id' => null,
            'role' => UserRole::Employee,
            'employee_id' => null,
            'phone' => fake()->phoneNumber(),
            'avatar' => null,
            'is_active' => true,
        ];
    }

    public function inDepartment(Department|int|null $department): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $department instanceof Department ? $department->getKey() : $department,
        ]);
    }

    public function role(UserRole $role): static
    {
        return $this->state(fn (array $attributes) => ['role' => $role]);
    }

    public function support(): static
    {
        return $this->role(UserRole::Support);
    }

    public function technician(): static
    {
        return $this->role(UserRole::Technician);
    }

    public function admin(): static
    {
        return $this->role(UserRole::Admin);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
