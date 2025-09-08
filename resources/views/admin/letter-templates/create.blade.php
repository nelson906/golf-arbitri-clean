@extends('layouts.admin')

@section('title', 'Nuovo Template')

@section('content')
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.letter-templates.index') }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Nuovo Template</h1>
        </div>
        <p class="text-gray-600">Crea un nuovo template per le email automatiche</p>
    </div>

    {{-- Form --}}
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('admin.letter-templates.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Nome Template --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nome Template *</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                           placeholder="Es: Template Assegnazione SZR1"
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
                        <option value="assignment" {{ old('type') === 'assignment' ? 'selected' : '' }}>
                            Assegnazione
                        </option>
                        <option value="convocation" {{ old('type') === 'convocation' ? 'selected' : '' }}>
                            Convocazione
                        </option>
                        <option value="club" {{ old('type') === 'club' ? 'selected' : '' }}>
                            Circolo
                        </option>
                        <option value="institutional" {{ old('type') === 'institutional' ? 'selected' : '' }}>
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
                            <option value="{{ $zone->id }}" {{ old('zone_id') == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('zone_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">
                        Lascia vuoto per usare il template per tutte le zone
                    </p>
                </div>

                {{-- Tipo Torneo --}}
                <div>
                    <label for="tournament_type_id" class="block text-sm font-medium text-gray-700 mb-1">Tipo Torneo</label>
                    <select name="tournament_type_id" id="tournament_type_id"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('tournament_type_id') border-red-500 @enderror">
                        <option value="">Tutti i tipi</option>
                        @foreach($tournamentTypes as $type)
                            <option value="{{ $type->id }}" {{ old('tournament_type_id') == $type->id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('tournament_type_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">
                        Lascia vuoto per usare il template per tutti i tipi di torneo
                    </p>
                </div>
            </div>

            {{-- Oggetto Email --}}
            <div>
                <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Oggetto Email *</label>
                <input type="text" name="subject" id="subject" value="{{ old('subject') }}"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('subject') border-red-500 @enderror"
                       placeholder="Es: Assegnazione {{tournament_name}} - {{assigned_date}}"
                       required>
                @error('subject')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">
                    Usa le variabili (es: {{tournament_name}}) per inserire dati dinamici
                </p>
            </div>

            {{-- Contenuto Template --}}
            <div>
                <label for="body" class="block text-sm font-medium text-gray-700 mb-1">Contenuto Template *</label>
                <textarea name="body" id="body" rows="15"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('body') border-red-500 @enderror"
                          placeholder="Scrivi il contenuto del template qui...

Esempio:
Gentile {{referee_name}},

La informiamo che è stato assegnato al torneo {{tournament_name}} che si svolgerà il {{tournament_dates}} presso il {{club_name}}.

Il suo ruolo sarà: {{assignment_role}}

Cordiali saluti,
Federazione Italiana Golf"
                          required>{{ old('body') }}</textarea>
                @error('body')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Opzioni --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex items-center">
                    <input type="checkbox" name="is_active" id="is_active" value="1"
                           {{ old('is_active', true) ? 'checked' : '' }}
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="is_active" class="ml-2 block text-sm text-gray-700">
                        Template attivo
                        <span class="text-xs text-gray-500">(disponibile per l'uso)</span>
                    </label>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="is_default" id="is_default" value="1"
                           {{ old('is_default') ? 'checked' : '' }}
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="is_default" class="ml-2 block text-sm text-gray-700">
                        Template predefinito
                        <span class="text-xs text-gray-500">(utilizzato automaticamente)</span>
                    </label>
                </div>
            </div>

            {{-- Variabili Disponibili --}}
            <div class="bg-blue-50 rounded-lg p-4">
                <h4 class="text-sm font-medium text-blue-900 mb-2">🔧 Variabili Disponibili:</h4>
                <p class="text-xs text-blue-700 mb-3">
                    Usa queste variabili nel tuo template. Verranno sostituite automaticamente con i dati reali durante l'invio.
                </p>
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
                        <div class="cursor-pointer hover:bg-blue-100 p-1 rounded"
                             onclick="insertVariable('{{$var}}')">
                            <code class="bg-white px-1 py-0.5 rounded text-blue-800">{{{{$var}}}}</code>
                            <div class="text-blue-700">{{ $desc }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-between">
                <a href="{{ route('adminletter-templates.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Annulla
                </a>

                <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Crea Template
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function insertVariable(variable) {
    const textarea = document.getElementById('body');
    const cursorPos = textarea.selectionStart;
    const textBefore = textarea.value.substring(0, cursorPos);
    const textAfter = textarea.value.substring(cursorPos);

    textarea.value = textBefore + '{{' + variable + '}}' + textAfter;
    textarea.focus();
    textarea.setSelectionRange(cursorPos + variable.length + 4, cursorPos + variable.length + 4);
}
</script>
@endsection
