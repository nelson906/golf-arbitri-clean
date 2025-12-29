@extends('layouts.admin')

@section('title', 'Modifica Assegnazione')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Modifica Assegnazione</h1>
        <p class="text-gray-600 mt-1">Modifica i dettagli dell'assegnazione per il torneo</p>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Successo!</p>
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Errore!</p>
            <p>{{ session('error') }}</p>
        </div>
    @endif

    {{-- Tournament Info --}}
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <h3 class="text-lg font-medium text-blue-900 mb-2">
            🏌️ {{ $tournament->name }}
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-blue-800">
            <p><strong>Circolo:</strong> {{ $tournament->club->name ?? 'N/A' }}</p>
            <p><strong>Data:</strong> {{ $tournament->start_date?->format('d/m/Y') }} - {{ $tournament->end_date?->format('d/m/Y') }}</p>
            <p><strong>Tipo:</strong> {{ $tournament->tournamentType->name ?? 'N/A' }}</p>
            <p><strong>Zona:</strong> {{ $tournament->club->zone->name ?? 'N/A' }}</p>
        </div>
    </div>

    {{-- Suggested Referee Alert --}}
    @if($suggestedReferee)
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <h4 class="font-medium text-yellow-900 mb-2">
                💡 Suggerimento dalla risoluzione conflitti
            </h4>
            <p class="text-yellow-800">
                Arbitro suggerito: <strong>{{ $suggestedReferee->name }}</strong>
                ({{ $suggestedReferee->referee->level_label ?? 'N/A' }})
            </p>
        </div>
    @endif

    {{-- Edit Form --}}
    <div class="bg-white shadow rounded-lg p-6">
        <form action="{{ route('admin.assignments.update', $assignment) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Current Assignment Info --}}
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <h4 class="font-medium text-gray-700 mb-2">Assegnazione corrente</h4>
                <p class="text-gray-600">
                    <strong>{{ $assignment->user->name }}</strong> - {{ $assignment->role }}
                </p>
            </div>

            {{-- Referee Selection --}}
            <div>
                <label for="{{ \App\Models\Assignment::getUserField() }}" class="block text-sm font-medium text-gray-700 mb-1">
                    Arbitro <span class="text-red-500">*</span>
                </label>
                <select name="{{ \App\Models\Assignment::getUserField() }}"
                        id="{{ \App\Models\Assignment::getUserField() }}"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        required>
                    @foreach($referees as $referee)
                        <option value="{{ $referee->id }}"
                            {{ old(\App\Models\Assignment::getUserField(), $assignment->{\App\Models\Assignment::getUserField()}) == $referee->id ? 'selected' : '' }}
                            {{ $suggestedReferee && $suggestedReferee->id == $referee->id ? 'class=bg-yellow-100' : '' }}>
                            {{ $referee->last_name }} {{ $referee->first_name }}
                            ({{ $referee->referee->referee_code ?? 'N/A' }} - {{ $referee->referee->level_label ?? 'N/A' }})
                            @if($suggestedReferee && $suggestedReferee->id == $referee->id)
                                ⭐ SUGGERITO
                            @endif
                        </option>
                    @endforeach
                </select>
                @error(\App\Models\Assignment::getUserField())
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Role Selection --}}
            <div>
                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">
                    Ruolo <span class="text-red-500">*</span>
                </label>
                <select name="role"
                        id="role"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        required>
                    @foreach($roles as $role)
                        <option value="{{ $role }}" {{ old('role', $assignment->role) == $role ? 'selected' : '' }}>
                            {{ $role }}
                        </option>
                    @endforeach
                </select>
                @error('role')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Notes --}}
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                    Note
                </label>
                <textarea name="notes"
                          id="notes"
                          rows="3"
                          class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Note aggiuntive sull'assegnazione...">{{ old('notes', $assignment->notes) }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Buttons --}}
            <div class="flex justify-between items-center pt-4 border-t">
                <a href="{{ route('admin.assignments.show', $assignment) }}"
                   class="text-gray-600 hover:text-gray-800">
                    ← Annulla
                </a>
                <button type="submit"
                        class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 font-medium">
                    💾 Salva Modifiche
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
