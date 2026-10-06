<?php

namespace App\Http\Requests;

use App\Enums\AssignmentRole;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignmentRequest extends FormRequest
{
    use \App\Http\Concerns\InteractsWithAuthUser;

    /**
     * Determina se l'utente è autorizzato a fare questa richiesta.
     * Usa i metodi tipizzati del model User invece di confronti stringa.
     */
    public function authorize(): bool
    {
        $user = $this->authUser();

        // Solo gli admin possono creare assegnazioni
        if (! $user->isAdmin()) {
            return false;
        }

        $tournament = Tournament::find($this->integer('tournament_id'));
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
            // P6 (2026-10-03): arbitri minimi/massimi e livello richiesto del tipo
            // torneo sono solo INDICAZIONI: nessun blocco in assegnazione.
            'tournament_id' => [
                'required',
                'exists:tournaments,id',
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

                    $tournament = Tournament::with('tournamentType')->find($this->integer('tournament_id'));

                    if ($tournament && Assignment::where('tournament_id', $tournament->id)
                        ->where('user_id', $user->id)
                        ->exists()
                    ) {
                        $fail('Questo arbitro è già stato assegnato a questo torneo.');
                    }

                    // Per tornei zonali: stesso zona
                    if ($tournament && ! ($tournament->tournamentType->is_national ?? false)) {
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
