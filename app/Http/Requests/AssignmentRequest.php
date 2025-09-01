<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Assignment;

class AssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Check user type
        if (!in_array($user->user_type, ['admin', 'national_admin', 'super_admin'])) {
            return false;
        }

        // Check tournament access
        $tournament = Tournament::find($this->tournament_id);
        if (!$tournament) {
            return false;
        }

        // Zone admins can only assign in their zone
        if ($user->user_type === 'admin' && $tournament->zone_id !== $user->zone_id) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tournament_id' => [
                'required',
                'exists:tournaments,id',
                function ($attribute, $value, $fail) {
                    $tournament = Tournament::find($value);
                    if (!$tournament) {
                        return;
                    }

                    // Check if tournament can accept assignments
                    if (!in_array($tournament->status, ['open', 'closed'])) {
                        $fail('Il torneo non è in uno stato che permette assegnazioni.');
                    }

                    // Check if tournament has reached max referees
                    if ($tournament->assignments()->count() >= $tournament->max_referees) {
                        $fail('Il torneo ha già raggiunto il numero massimo di arbitri.');
                    }
                },
            ],
            'user_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = User::find($value);
                    if (!$user) {
                        return;
                    }

                    // Check if user is a referee
                    if ($user->user_type !== 'referee') {
                        $fail('L\'utente selezionato non è un arbitro.');
                    }

                    // Check if user is active
                    if (!$user->is_active) {
                        $fail('L\'arbitro selezionato non è attivo.');
                    }

                    // Check if already assigned
                    $tournament = Tournament::find($this->tournament_id);
                    if ($tournament && Assignment::where('tournament_id', $tournament->id)
                        ->where('user_id', $user->id)
                        ->exists()) {
                        $fail('Questo arbitro è già stato assegnato a questo torneo.');
                    }

                    // Check referee level
                    if ($tournament) {
                        $requiredLevel = $tournament->tournamentType->required_referee_level;
                        $levels = array_keys(\App\Models\TournamentType::REFEREE_LEVELS);
                        $requiredIndex = array_search($requiredLevel, $levels);
                        $userIndex = array_search($user->level, $levels);

                        if ($userIndex < $requiredIndex) {
                            $fail('L\'arbitro non ha il livello richiesto per questo torneo.');
                        }
                    }

                    // Check zone for non-national tournaments
                    if ($tournament && !$tournament->tournamentType->is_national) {
                        if ($user->zone_id !== $tournament->zone_id) {
                            $fail('L\'arbitro appartiene a una zona diversa.');
                        }
                    }
                },
            ],
            'role' => [
                'required',
                Rule::in(['Arbitro', 'Direttore di Torneo', 'Osservatore']),
            ],
            'notes' => 'nullable|string|max:500',
        ];
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
