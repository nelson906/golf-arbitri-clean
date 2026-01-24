<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Club>
 */
class ClubFactory extends Factory
{
    protected $model = Club::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Golf Club ' . fake()->city(),
            'zone_id' => Zone::inRandomOrder()->first()?->id ?? 1,
            'code' => strtoupper(fake()->unique()->bothify('GC###??')),
            'city' => fake()->city(),
            'province' => fake()->stateAbbr(),
            'address' => fake()->streetAddress(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'is_active' => true,
        ];
    }

    /**
     * Club in zona specifica
     */
    public function inZone(int $zoneId): static
    {
        return $this->state(fn (array $attributes) => [
            'zone_id' => $zoneId,
        ]);
    }

    /**
     * Club inattivo
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
