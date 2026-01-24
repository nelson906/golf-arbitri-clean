<?php

namespace Database\Factories;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'name' => $firstName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone' => fake()->phoneNumber(),
            'user_type' => 'referee',
            'zone_id' => Zone::inRandomOrder()->first()?->id ?? 1,
            'level' => fake()->randomElement(['Aspirante', '1_livello', 'Regionale', 'Nazionale', 'Internazionale']),
            'is_active' => true,
            'city' => fake()->city(),
            'gender' => fake()->randomElement(['male', 'female', 'mixed']),
        ];
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

    /**
     * User è un arbitro
     */
    public function referee(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'referee',
            'level' => fake()->randomElement([
                'Aspirante',
                '1_livello',
                'Regionale',
                'Nazionale',
                'Internazionale',
            ]),
        ]);
    }

    /**
     * User è admin di zona
     */
    public function zoneAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'admin',
            'level' => '1_livello',  // Admin hanno comunque un livello
        ]);
    }

    /**
     * User è admin nazionale
     */
    public function nationalAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'national_admin',
            'level' => '1_livello',  // Admin hanno comunque un livello
        ]);
    }

    /**
     * User è super admin
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'super_admin',
            'level' => '1_livello',  // Admin hanno comunque un livello
        ]);
    }

    /**
     * User con livello specifico
     */
    public function withLevel(string $level): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => $level,
        ]);
    }

    /**
     * User in zona specifica
     */
    public function inZone(int $zoneId): static
    {
        return $this->state(fn (array $attributes) => [
            'zone_id' => $zoneId,
        ]);
    }

    /**
     * User inattivo
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
