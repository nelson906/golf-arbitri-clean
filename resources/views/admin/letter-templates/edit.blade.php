@extends('layouts.admin')

@section('title', 'Modifica Template')

@section('content')
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.letter-templates.show', $template) }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Modifica Template</h1>
        </div>
        <p class="text-gray-600">Modifica le informazioni del template email</p>
    </div>

    {{-- Form --}}
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('admin.letter-templates.update', $template) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Nome Template --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nome Template *</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $template->name) }}"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                           required>
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Tipo Template --}}
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Tipo Template *</label>
                    <select name="type" id="type"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('type') border-red-500 @enderror"
                            required>
                        <option value="">Seleziona tipo...</option>
                        <option value="assignment" {{ old('type', $template->type) === 'assignment' ? 'selected' : '' }}>
                            Assegnazione
                        </option>
                        <option value="convocation" {{ old('type', $template->type) === 'convocation' ? 'selected' : '' }}>
                            Convocazione
                        </option>
                        <option value="club" {{ old('type', $template->type) === 'club' ? 'selected' : '' }}>
                            Circolo
                        </option>
                        <option value="institutional" {{ old('type', $template->type) === 'institutional' ? 'selected' : '' }}>
                            Istituzionale
                        </option>
                    </select>
                    @error('type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Zona --}}
                <div>
                    <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                    <select name="zone_id" id="zone_id"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('zone_id') border-red-500 @enderror">
                        <option value="">Tutte le zone</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}" {{ old('zone_id', $template->zone_id) == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('zone_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Tipo Torneo --}}
                <div>
                    <label for="tournament_type_id" class="block text-sm font-medium text-gray-700 mb-1">Tipo Torneo</label>
                    <select name="tournament_type_id" id="tournament_type_id"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('tournament_type_id') border-red-500 @enderror">
                        <option value="">Tutti i tipi</option>
                        @foreach($tournamentTypes as $type)
                            <option value="{{ $type->id }}" {{ old('tournament_type_id', $template->tournament_type_id) == $type->id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('tournament_type_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Oggetto Email --}}
            <div>
                <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Oggetto Email *</label>
                <input type="text" name="subject" id="subject" value="{{ old('subject', $template->subject) }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('subject') border-red-500 @enderror"
                       placeholder="Es: Assegnazione {{'tournament_name'}} - {{'assigned_date'}}"
                       required>
                @error('subject')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Contenuto Template --}}
            <div>
                <label for="body" class="block text-sm font-medium text-gray-700 mb-1">Contenuto Template *</label>
                <textarea name="body" id="body" rows="15"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('body') border-red-500 @enderror"
                          placeholder="Scrivi il contenuto del template qui... Usa {{'variabile'}} per i dati dinamici."
                          required>{{ old('body', $template->body) }}</textarea>
                @error('body')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Opzioni --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex items-center">
                    <input type="checkbox" name="is_active" id="is_active" value="1"
                           {{ old('is_active', $template->is_active) ? 'checked' : '' }}
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="is_active" class="ml-2 block text-sm text-gray-700">
                        Template attivo
                    </label>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="is_default" id="is_default" value="1"
                           {{ old('is_default', $template->is_default) ? 'checked' : '' }}
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="is_default" class="ml-2 block text-sm text-gray-700">
                        Template predefinito
                        <span class="text-xs text-gray-500">(sostituirà l'attuale predefinito)</span>
                    </label>
                </div>
            </div>

            {{-- Variabili Disponibili --}}
            <div class="bg-blue-50 rounded-lg p-4">
                <h4 class="text-sm font-medium text-blue-900 mb-2">🔧 Variabili Disponibili:</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                    @php
                        $variables = [
                            'tournament_name' => 'Nome torneo',
                            'tournament_dates' => 'Date torneo',
                            'club_name' => 'Nome circolo',
                            'club_address' => 'Indirizzo circolo',
                            'referee_name' => 'Nome arbitro',
                            'assignment_role' => 'Ruolo',
                            'zone_name' => 'Nome zona',
                            'assigned_date' => 'Data assegnazione',
                            'tournament_category' => 'Categoria torneo',
                        ];
                    @endphp

                    @foreach($variables as $var => $desc)
                        <div>
                            <code class="bg-white px-1 py-0.5 rounded text-blue-800">{{$var}}</code>
                            <div class="text-blue-700">{{ $desc }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-between">
                <div class="flex space-x-3">
                    <a href="{{ route('admin.letter-templates.show', $template) }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Annulla
                    </a>

                    <a href="{{ route('admin.letter-templates.preview', $template) }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                       target="_blank">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Anteprima
                    </a>
                </div>

                <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Salva Modifiche
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
