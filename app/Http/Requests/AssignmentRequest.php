<?php

namespace App\Http\Requests;

use App\Enums\AssignmentRole;
use App\Enums\TournamentStatus;
use App\Enums\UserType;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignmentRequest extends FormRequest
{
    /**
     * Determina se l'utente è autorizzato a fare questa richiesta.
     * Usa i metodi tipizzati del model User invece di confronti stringa.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Solo gli admin possono creare assegnazioni
        if (! $user->isAdmin()) {
            return false;
        }

        $tournament = Tournament::find($this->tournament_id);
        if (! $tournament) {
            return false;
        }

        // Zone admin: solo tornei della propria zona
        if ($user->isZoneAdmin() && $tournament->zone_id !== $user->zone_id) {
            return false;
        }

        return true;
    }

    /**
     * Regole di validazione con Enum type-safe.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tournament_id' => [
                'required',
                'exists:tournaments,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    /** @var Tournament|null $tournament */
                    $tournament = Tournament::with('tournamentType')->find($value);

                    if (! $tournament instanceof Tournament) {
                        return;
                    }

                    // Usa l'Enum TournamentStatus per verificare lo stato
                    if (! $tournament->status->isActive()) {
                        $fail('Il torneo non è in uno stato che permette assegnazioni (stato: '.$tournament->status->label().').');
                    }

                    // Verifica limite massimo arbitri
                    $maxReferees = $tournament->tournamentType?->max_referees ?? 4;
                    if ($tournament->assignments()->count() >= $maxReferees) {
                        $fail('Il torneo ha già raggiunto il numero massimo di '.$maxReferees.' arbitri.');
                    }
                },
            ],
            'user_id' => [
                'required',
                'exists:users,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    /** @var User|null $user */
                    $user = User::find($value);

                    if (! $user) {
                        return;
                    }

                    // Usa il metodo tipizzato isReferee()
                    if (! $user->isReferee()) {
                        $fail("L'utente selezionato non è un arbitro.");
                    }

                    if (! $user->is_active) {
                        $fail("L'arbitro selezionato non è attivo.");
                    }

                    $tournament = Tournament::with('tournamentType')->find($this->tournament_id);

                    if ($tournament && Assignment::where('tournament_id', $tournament->id)
                        ->where('user_id', $user->id)
                        ->exists()
                    ) {
                        $fail('Questo arbitro è già stato assegnato a questo torneo.');
                    }

                    // Verifica livello minimo richiesto
                    if ($tournament?->tournamentType && $tournament->tournamentType->required_level) {
                        $requiredLevel = $tournament->tournamentType->required_level;
                        $levels        = array_keys(User::LEVELS);
                        $requiredIndex = array_search($requiredLevel, $levels);
                        $userIndex     = array_search($user->level, $levels);

                        if ($userIndex !== false && $requiredIndex !== false && $userIndex < $requiredIndex) {
                            $fail("L'arbitro non ha il livello richiesto per questo torneo.");
                        }
                    }

                    // Per tornei zonali: stesso zona
                    if ($tournament && ! ($tournament->tournamentType?->is_national ?? false)) {
                        if ($user->zone_id !== $tournament->zone_id) {
                            $fail("L'arbitro appartiene a una zona diversa dal torneo.");
                        }
                    }
                },
            ],
            // Usa Rule::enum() di Laravel 10+ invece di Rule::in() con stringhe
            'role' => [
                'required',
                Rule::enum(AssignmentRole::class),
            ],
            'notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Restituisce il ruolo come valore Enum tipizzato.
     */
    public function resolvedRole(): AssignmentRole
    {
        return $this->role
            ? AssignmentRole::from($this->role)
            : AssignmentRole::Referee;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tournament_id.required' => 'Il torneo è obbligatorio.',
            'tournament_id.exists' => 'Il torneo selezionato non è valido.',
            'user_id.required' => 'L\'arbitro è obbligatorio.',
            'user_id.exists' => 'L\'arbitro selezionato non è valido.',
            'role.required' => 'Il ruolo è obbligatorio.',
            'role.in' => 'Il ruolo selezionato non è valido.',
            'notes.max' => 'Le note non possono superare i 500 caratteri.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'tournament_id' => 'torneo',
            'user_id' => 'arbitro',
            'role' => 'ruolo',
            'notes' => 'note',
        ];
    }
}
