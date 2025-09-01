<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Club;
use Carbon\Carbon;

class TournamentRequest extends FormRequest
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

        // For updates, check zone access
        if ($this->route('tournament')) {
            $tournament = $this->route('tournament');

            if ($user->user_type === 'admin' && $tournament->zone_id !== $user->zone_id) {
                return false;
            }
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
        $tournament = $this->route('tournament');
        $isUpdate = $tournament !== null;

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'tournament_type_id' => [
                'required',
                'exists:tournament_types,id',
                function ($attribute, $value, $fail) {
                    $category = TournamentType::find($value);
                    if ($category && !$category->is_active) {
                        $fail('La categoria selezionata non è attiva.');
                    }

                    // Check if category is available for user's zone
                    $user = $this->user();
                    if ($user->user_type === 'admin' && !$category->isAvailableForZone($user->zone_id)) {
                        $fail('Questa categoria non è disponibile per la tua zona.');
                    }
                },
            ],
            'club_id' => [
                'required',
                'exists:clubs,id',
                function ($attribute, $value, $fail) {
                    $club = club::find($value);
                    if ($club && !$club->is_active) {
                        $fail('Il circolo selezionato non è attivo.');
                    }

                    // Check zone access for club
                    $user = $this->user();
                    if ($user->user_type === 'admin' && $club->zone_id !== $user->zone_id) {
                        $fail('Non puoi selezionare un circolo di un\'altra zona.');
                    }
                },
            ],
            'start_date' => [
                'required',
                'date',
                $isUpdate ? 'after_or_equal:today' : 'after:today',
            ],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],
            'availability_deadline' => [
                'required',
                'date',
                'after_or_equal:today',
                'before:start_date',
            ],
            'notes' => 'nullable|string|max:1000',
            'status' => [
                'sometimes',
                Rule::in(array_keys(Tournament::STATUSES)),
                function ($attribute, $value, $fail) use ($isUpdate, $tournament) {
                    if (!$isUpdate) {
                        // For new tournaments, only draft and open are allowed
                        if (!in_array($value, ['draft', 'open'])) {
                            $fail('Stato non valido per un nuovo torneo.');
                        }
                    } else {
                        // For updates, check if tournament is editable
                        if (!$tournament->isEditable()) {
                            $fail('Questo torneo non può essere modificato nel suo stato attuale.');
                        }
                    }
                },
            ],
        ];

        // Zone ID is required for national admins
        if ($this->user()->user_type === 'national_admin') {
            $rules['zone_id'] = [
                'required',
                'exists:zones,id',
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Il nome del torneo è obbligatorio.',
            'name.max' => 'Il nome del torneo non può superare i 255 caratteri.',
            'tournament_type_id.required' => 'La categoria del torneo è obbligatoria.',
            'tournament_type_id.exists' => 'La categoria selezionata non è valida.',
            'club_id.required' => 'Il circolo è obbligatorio.',
            'club_id.exists' => 'Il circolo selezionato non è valido.',
            'zone_id.required' => 'La zona è obbligatoria.',
            'zone_id.exists' => 'La zona selezionata non è valida.',
            'start_date.required' => 'La data di inizio è obbligatoria.',
            'start_date.date' => 'La data di inizio non è valida.',
            'start_date.after' => 'La data di inizio deve essere futura.',
            'start_date.after_or_equal' => 'La data di inizio non può essere nel passato.',
            'end_date.required' => 'La data di fine è obbligatoria.',
            'end_date.date' => 'La data di fine non è valida.',
            'end_date.after_or_equal' => 'La data di fine deve essere uguale o successiva alla data di inizio.',
            'availability_deadline.required' => 'La scadenza per le disponibilità è obbligatoria.',
            'availability_deadline.date' => 'La scadenza per le disponibilità non è valida.',
            'availability_deadline.after_or_equal' => 'La scadenza per le disponibilità non può essere nel passato.',
            'availability_deadline.before' => 'La scadenza per le disponibilità deve essere prima dell\'inizio del torneo.',
            'notes.max' => 'Le note non possono superare i 1000 caratteri.',
            'status.in' => 'Lo stato selezionato non è valido.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // If not a national admin, set zone_id from the selected club
        if ($this->user()->user_type !== 'national_admin' && $this->has('club_id')) {
            $club = club::find($this->club_id);
            if ($club) {
                $this->merge([
                    'zone_id' => $club->zone_id
                ]);
            }
        }

        // Set default status if not provided
        if (!$this->has('status') && !$this->route('tournament')) {
            $this->merge([
                'status' => 'draft'
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome torneo',
            'tournament_type_id' => 'categoria',
            'club_id' => 'circolo',
            'zone_id' => 'zona',
            'start_date' => 'data inizio',
            'end_date' => 'data fine',
            'availability_deadline' => 'scadenza disponibilità',
            'notes' => 'note',
            'status' => 'stato',
        ];
    }
}
