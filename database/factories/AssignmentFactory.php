<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->referee(),
            'tournament_id' => Tournament::factory(),
            'role' => fake()->randomElement([
                'Direttore di Torneo',
                'Arbitro',
                'Osservatore',
            ]),
            'status' => 'assigned',
            'assigned_at' => now(),
            'assigned_by' => User::factory()->zoneAdmin(),
            'is_confirmed' => false,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Assignment confermato
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'is_confirmed' => true,
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Assignment completato
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'is_confirmed' => true,
            'confirmed_at' => now()->subDays(7),
        ]);
    }

    /**
     * Come Direttore di Torneo
     */
    public function asTournamentDirector(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Direttore di Torneo',
        ]);
    }

    /**
     * Come Arbitro
     */
    public function asReferee(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Arbitro',
        ]);
    }

    /**
     * Come Osservatore
     */
    public function asObserver(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Osservatore',
        ]);
    }

    /**
     * Per utente specifico
     */
    public function forUser(User|int $user): static
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    /**
     * Per torneo specifico
     */
    public function forTournament(Tournament|int $tournament): static
    {
        $tournamentId = $tournament instanceof Tournament ? $tournament->id : $tournament;

        return $this->state(fn (array $attributes) => [
            'tournament_id' => $tournamentId,
        ]);
    }
}
