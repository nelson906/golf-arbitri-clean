<?php

namespace Database\Factories;

use App\Models\TournamentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TournamentType>
 */
class TournamentTypeFactory extends Factory
{
    protected $model = TournamentType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Genera short_name univoco evitando quelli già usati dal seeder (NAZ, ZON, GIO)
        static $counter = 0;
        $counter++;

        return [
            'name' => fake()->words(2, true),
            'short_name' => 'T'.str_pad($counter, 2, '0', STR_PAD_LEFT),  // T01, T02, etc
            'description' => fake()->optional()->sentence(),
            'is_national' => fake()->boolean(30),
            'level' => fake()->randomElement(['zonale', 'nazionale']),
            'required_level' => fake()->randomElement(['aspirante', '1_livello', 'regionale', 'nazionale', 'internazionale']),
            'calendar_color' => fake()->hexColor(),
            'min_referees' => fake()->numberBetween(1, 2),
            'max_referees' => fake()->numberBetween(2, 4),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    /**
     * Tipo nazionale
     */
    public function national(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_national' => true,
            'level' => 'nazionale',
            'required_level' => 'nazionale',
            'name' => 'Tipo Nazionale',
            'short_name' => 'TN'.fake()->unique()->numberBetween(10, 99),
        ]);
    }

    /**
     * Tipo zonale
     */
    public function zonal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_national' => false,
            'level' => 'zonale',
            'required_level' => '1_livello',
            'name' => 'Tipo Zonale',
            'short_name' => 'TZ'.fake()->unique()->numberBetween(10, 99),
        ]);
    }

    /**
     * Tipo giovanile
     */
    public function youth(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_national' => false,
            'level' => 'zonale',
            'required_level' => 'aspirante',
            'name' => 'Tipo Giovanile',
            'short_name' => 'TG'.fake()->unique()->numberBetween(10, 99),
        ]);
    }

    /**
     * Inattivo
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
