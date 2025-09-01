<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,txt,csv'
            ],
            'category' => 'required|string|in:general,tournament,regulation,form,template',
            'description' => 'nullable|string|max:1000',
            'tournament_id' => 'nullable|exists:tournaments,id',
            'is_public' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'È necessario selezionare un file.',
            'file.max' => 'Il file non può superare i 10MB.',
            'file.mimes' => 'Formato file non supportato.',
            'category.required' => 'La categoria è obbligatoria.',
            'category.in' => 'Categoria non valida.',
        ];
    }
}
