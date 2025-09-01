<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefereeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $refereeId = $this->route('referee')?->id;

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($refereeId)
            ],
            'referee_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'referee_code')->ignore($refereeId)
            ],
            'level' => 'required|in:aspirante,primo_livello,regionale,nazionale,internazionale',
            'zone_id' => 'required|exists:zones,id',
            'phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Il nome è obbligatorio.',
            'email.required' => 'L\'email è obbligatoria.',
            'email.unique' => 'Questa email è già in uso.',
            'referee_code.required' => 'Il codice arbitro è obbligatorio.',
            'referee_code.unique' => 'Questo codice arbitro è già in uso.',
            'level.required' => 'Il livello è obbligatorio.',
            'zone_id.required' => 'La zona è obbligatoria.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'email' => 'email',
            'referee_code' => 'codice arbitro',
            'level' => 'livello',
            'zone_id' => 'zona',
            'phone' => 'telefono',
            'notes' => 'note',
            'is_active' => 'attivo',
        ];
    }
}
