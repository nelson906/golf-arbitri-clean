<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TournamentTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->user_type === 'super_admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $tournamentTypeId = $this->route('tournament_type')?->id ?? $this->route('tournamentType')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tournament_types', 'name')->ignore($tournamentTypeId),
            ],
            'short_name' => [
                'required',
                'string',
                'max:20',
                'alpha_num:ascii',
                Rule::unique('tournament_types', 'short_name')->ignore($tournamentTypeId),
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'is_national' => [
                'boolean',
            ],
            'level' => [
                'required',
                'string',
                'in:zonale,nazionale',
            ],
            'required_referee_level' => [
                'required',
                'string',
                'in:aspirante,primo_livello,regionale,nazionale,internazionale',
            ],
            'min_referees' => [
                'required',
                'integer',
                'min:1',
                'max:10',
            ],
            'max_referees' => [
                'required',
                'integer',
                'min:1',
                'max:20',
                'gte:min_referees',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
            ],
            'is_active' => [
                'boolean',
            ],
            'visibility_zones' => [
                'nullable',
                'array',
                'exists:zones,id',
            ],
            'special_requirements' => [
                'nullable',
                'string',
                'max:500',
            ],
            'notification_templates' => [
                'nullable',
                'array',
            ],
            'settings' => [
                'nullable',
                'array',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Il nome del tipo torneo è obbligatorio.',
            'name.unique' => 'Esiste già un tipo torneo con questo nome.',
            'name.max' => 'Il nome non può superare i 255 caratteri.',

            'short_name.required' => 'Il codice è obbligatorio.',
            'short_name.unique' => 'Esiste già un tipo torneo con questo codice.',
            'short_name.max' => 'Il codice non può superare i 20 caratteri.',
            'short_name.alpha_num' => 'Il codice può contenere solo lettere e numeri.',

            'description.max' => 'La descrizione non può superare i 1000 caratteri.',

            'is_national.boolean' => 'Il campo nazionale deve essere vero o falso.',

            'level.required' => 'Il livello è obbligatorio.',
            'level.in' => 'Il livello deve essere zonale o nazionale.',

            'required_referee_level.required' => 'Il livello arbitro richiesto è obbligatorio.',
            'required_referee_level.in' => 'Il livello arbitro richiesto non è valido.',

            'min_referees.required' => 'Il numero minimo di arbitri è obbligatorio.',
            'min_referees.integer' => 'Il numero minimo di arbitri deve essere un numero intero.',
            'min_referees.min' => 'Il numero minimo di arbitri deve essere almeno 1.',
            'min_referees.max' => 'Il numero minimo di arbitri non può superare 10.',

            'max_referees.required' => 'Il numero massimo di arbitri è obbligatorio.',
            'max_referees.integer' => 'Il numero massimo di arbitri deve essere un numero intero.',
            'max_referees.min' => 'Il numero massimo di arbitri deve essere almeno 1.',
            'max_referees.max' => 'Il numero massimo di arbitri non può superare 20.',
            'max_referees.gte' => 'Il numero massimo di arbitri deve essere maggiore o uguale al minimo.',

            'sort_order.integer' => 'L\'ordine di visualizzazione deve essere un numero intero.',
            'sort_order.min' => 'L\'ordine di visualizzazione non può essere negativo.',
            'sort_order.max' => 'L\'ordine di visualizzazione non può superare 9999.',

            'is_active.boolean' => 'Lo stato attivo deve essere vero o falso.',

            'visibility_zones.array' => 'Le zone di visibilità devono essere un array.',
            'visibility_zones.exists' => 'Una o più zone selezionate non sono valide.',

            'special_requirements.max' => 'I requisiti speciali non possono superare i 500 caratteri.',

            'notification_templates.array' => 'I template di notifica devono essere un array.',

            'settings.array' => 'Le impostazioni devono essere un array.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'short_name' => 'codice',
            'description' => 'descrizione',
            'is_national' => 'nazionale',
            'level' => 'livello',
            'required_referee_level' => 'livello arbitro richiesto',
            'min_referees' => 'arbitri minimi',
            'max_referees' => 'arbitri massimi',
            'sort_order' => 'ordine',
            'is_active' => 'attivo',
            'visibility_zones' => 'zone visibilità',
            'special_requirements' => 'requisiti speciali',
            'notification_templates' => 'template notifiche',
            'settings' => 'impostazioni',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert short_name to uppercase
        if ($this->has('short_name')) {
            $this->merge([
                'short_name' => strtoupper($this->short_name)
            ]);
        }

        // Set default values
        $this->merge([
            'is_national' => $this->boolean('is_national'),
            'is_active' => $this->boolean('is_active', true),
        ]);

        // If level is not set but is_national is true, set to nazionale
        if ($this->boolean('is_national') && !$this->has('level')) {
            $this->merge(['level' => 'nazionale']);
        } elseif (!$this->boolean('is_national') && !$this->has('level')) {
            $this->merge(['level' => 'zonale']);
        }

        // Set default sort_order if not provided
        if (!$this->has('sort_order') || $this->sort_order === null) {
            $this->merge([
                'sort_order' => \App\Models\TournamentType::max('sort_order') + 10
            ]);
        }

        // Ensure max_referees is at least equal to min_referees
        if ($this->has('min_referees') && !$this->has('max_referees')) {
            $this->merge([
                'max_referees' => $this->min_referees
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Custom validation for national tournaments
            if ($this->boolean('is_national')) {
                // National tournaments should require higher referee levels
                if (in_array($this->required_referee_level, ['aspirante', 'primo_livello'])) {
                    $validator->errors()->add(
                        'required_referee_level',
                        'I tornei nazionali richiedono arbitri di livello almeno regionale.'
                    );
                }

                // National tournaments should have level = 'nazionale'
                if ($this->level !== 'nazionale') {
                    $validator->errors()->add(
                        'level',
                        'I tornei nazionali devono avere livello "nazionale".'
                    );
                }
            }

            // Custom validation for zonal tournaments
            if (!$this->boolean('is_national')) {
                // Zonal tournaments should have level = 'zonale'
                if ($this->level !== 'zonale') {
                    $validator->errors()->add(
                        'level',
                        'I tornei zonali devono avere livello "zonale".'
                    );
                }

                // Zonal tournaments should specify visibility zones
                if (empty($this->visibility_zones) && !$this->boolean('is_national')) {
                    $validator->errors()->add(
                        'visibility_zones',
                        'I tornei zonali devono specificare le zone di visibilità.'
                    );
                }
            }

            // Validate referee count logic
            if ($this->has('min_referees') && $this->has('max_referees')) {
                if ($this->max_referees < $this->min_referees) {
                    $validator->errors()->add(
                        'max_referees',
                        'Il numero massimo di arbitri deve essere maggiore o uguale al minimo.'
                    );
                }
            }

            // Validate short_name format
            if ($this->has('short_name')) {
                // short_name should be meaningful and not just numbers
                if (is_numeric($this->short_name)) {
                    $validator->errors()->add(
                        'short_name',
                        'Il codice non può essere solo numerico.'
                    );
                }

                // short_name should not contain common words that might cause conflicts
                $reservedWords = ['ADMIN', 'API', 'SYSTEM', 'DEFAULT', 'NULL', 'UNDEFINED'];
                if (in_array(strtoupper($this->short_name), $reservedWords)) {
                    $validator->errors()->add(
                        'short_name',
                        'Il codice non può utilizzare parole riservate del sistema.'
                    );
                }
            }
        });
    }

    /**
     * Get the error messages for the defined validation rules (additional).
     */
    public function getValidatedSettings(): array
    {
        $validated = $this->validated();

        // Build settings array
        $settings = $validated['settings'] ?? [];

        // Add core settings
        $settings['required_referee_level'] = $validated['required_referee_level'];
        $settings['min_referees'] = $validated['min_referees'];
        $settings['max_referees'] = $validated['max_referees'];
        $settings['special_requirements'] = $validated['special_requirements'] ?? null;
        $settings['notification_templates'] = $validated['notification_templates'] ?? [];

        // Visibility zones
        if ($validated['is_national'] ?? false) {
            $settings['visibility_zones'] = 'all';
        } else {
            $settings['visibility_zones'] = $validated['visibility_zones'] ?? [];
        }

        return $settings;
    }
}
