<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tournament>
 */
class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = Carbon::now()->addDays(rand(10, 60));
        $endDate = (clone $startDate)->addDays(rand(0, 3));
        $deadline = (clone $startDate)->subDays(rand(1, 7));

        return [
            'name' => fake()->company().' Open',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'availability_deadline' => $deadline,
            'club_id' => Club::inRandomOrder()->first()?->id ?? Club::factory(),
            'tournament_type_id' => TournamentType::inRandomOrder()->first()?->id ?? TournamentType::factory(),
            'created_by' => User::factory()->zoneAdmin(),
            'status' => Tournament::STATUS_OPEN,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Torneo passato
     */
    public function past(): static
    {
        return $this->state(function (array $attributes) {
            $endDate = Carbon::now()->subDays(rand(1, 30));
            $startDate = (clone $endDate)->subDays(rand(0, 3));

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'availability_deadline' => (clone $startDate)->subDays(rand(7, 14)),
                'status' => Tournament::STATUS_COMPLETED,
            ];
        });
    }

    /**
     * Torneo futuro
     */
    public function upcoming(): static
    {
        return $this->state(function (array $attributes) {
            $startDate = Carbon::now()->addDays(rand(10, 60));
            $endDate = (clone $startDate)->addDays(rand(0, 3));

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'availability_deadline' => (clone $startDate)->subDays(rand(7, 14)),
                'status' => Tournament::STATUS_OPEN,
            ];
        });
    }

    /**
     * Torneo in corso
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            $startDate = Carbon::now()->subDays(1);
            $endDate = Carbon::now()->addDays(2);

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'availability_deadline' => (clone $startDate)->subDays(rand(7, 14)),
                'status' => Tournament::STATUS_ASSIGNED,
            ];
        });
    }

    /**
     * In zona specifica
     */
    public function inZone(int $zoneId): static
    {
        return $this->state(function (array $attributes) use ($zoneId) {
            // Cerca un club nella zona specificata, altrimenti creane uno
            $club = Club::where('zone_id', $zoneId)->inRandomOrder()->first()
                ?? Club::factory()->create(['zone_id' => $zoneId]);

            return [
                'club_id' => $club->id,
            ];
        });
    }

    /**
     * Con tipo specifico
     */
    public function ofType(int $typeId): static
    {
        return $this->state(fn (array $attributes) => [
            'tournament_type_id' => $typeId,
        ]);
    }

    /**
     * Con status specifico
     */
    public function withStatus(string $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * Torneo stesso giorno
     */
    public function singleDay(): static
    {
        return $this->state(function (array $attributes) {
            $startDate = Carbon::now()->addDays(rand(10, 60));

            return [
                'start_date' => $startDate,
                'end_date' => $startDate,
            ];
        });
    }

    /**
     * Con deadline scaduta
     */
    public function deadlinePassed(): static
    {
        return $this->state(function (array $attributes) {
            $startDate = Carbon::now()->addDays(5);
            $endDate = (clone $startDate)->addDays(2);
            $deadline = Carbon::now()->subDays(1); // Scaduta ieri

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'availability_deadline' => $deadline,
            ];
        });
    }
}
