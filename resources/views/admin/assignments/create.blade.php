@extends('layouts.admin')

@section('title', 'Assegna Arbitro')

@section('content')

<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
<x-table-header
    title="Gestione Assegnazioni"
    description="Gestisci le assegnazioni degli arbitri ai tornei"
    :create-route="route('admin.assignments.create')"
    create-text="👤 Assegna Singolo Arbitro"
    create-color="blue"
    :secondary-route="route('admin.tournaments.index')"
    secondary-text="🏌️ Assegna per Torneo"
    secondary-color="green"
/>
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

{{-- Arbitri già assegnati a questo torneo --}}
@if($tournament && $tournament->assignments()->count() > 0)
<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
    <h3 class="text-lg font-medium text-green-900 mb-3">
        👥 Comitato di Gara Assegnato ({{ $tournament->assignments()->count() }})
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        @foreach($tournament->assignments()->with('user.referee')->get() as $assignment)
        <div class="bg-white p-3 rounded border border-green-200 flex justify-between items-center">
            <div>
                <p class="font-medium text-gray-900">{{ $assignment->user->name }}</p>
                <p class="text-sm text-gray-600">
                    {{ $assignment->user->referee->referee_code ?? 'N/A' }} -
                    {{ $assignment->user->referee->level_label ?? 'N/A' }}
                </p>
                <p class="text-sm font-medium text-green-600">{{ $assignment->role }}</p>
            </div>
            <form method="POST" action="{{ route('admin.assignments.destroy', $assignment) }}" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit"
                        onclick="return confirm('Rimuovere {{ $assignment->user->name }} dal comitato?')"
                        class="text-red-600 hover:text-red-800 text-sm px-2 py-1 rounded border border-red-200">
                    Rimuovi
                </button>
            </form>
        </div>
        @endforeach
    </div>

    {{-- Pulsante per completare assegnazioni --}}
    <div class="mt-4 pt-3 border-t border-green-200">
        <a href="{{ route('admin.assignments.index') }}"
           class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 font-medium">
            ✅ Comitato Completo - Torna alla Lista
        </a>
    </div>
</div>
@endif

    {{-- Assignment Form --}}
    <div class="bg-white shadow rounded-lg p-6">
        <form action="{{ route('admin.assignments.store') }}" method="POST" class="space-y-6">
            @csrf

            {{-- Tournament Selection --}}
            <div>
                <label for="tournament_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Torneo *
                </label>
                @if($tournament)
                    <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">
                    <div class="p-3 bg-gray-50 rounded-md border">
                        <div class="text-sm font-medium text-gray-900">{{ $tournament->name }}</div>
                        <div class="text-sm text-gray-500">
                            {{ $tournament->club->name }} - {{ $tournament->date_range }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Arbitri: {{ $tournament->assignments()->count() }} / {{ $tournament->required_referees }}
                        </div>
                    </div>
                @else
<select name="tournament_id" id="tournament_id"
        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
        required>
    <option value="">Seleziona un torneo</option>
    @foreach($tournaments as $tournament)
        <option value="{{ $tournament->id }}">
            {{ $tournament->name }} - {{ $tournament->start_date->format('d/m/Y') }}
            @if($tournament->club)
                ({{ $tournament->club->code ?? $tournament->club->name }})
            @endif
        </option>
    @endforeach
</select>
                @endif
                @error('tournament_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Referee Selection --}}
<div>
    <label for="user_id" class="block text-sm font-medium text-gray-700">Arbitro *</label>
    <select name="user_id" id="user_id"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
            required>
        <option value="">Seleziona un arbitro</option>

        @if($availableReferees->count() > 0)
            <optgroup label="📅 HANNO DATO DISPONIBILITÀ ({{ $availableReferees->count() }})">
                @foreach($availableReferees as $referee)
                    <option value="{{ $referee->id }}" style="color: green; font-weight: bold;">
                        ✅ {{ $referee->name }}
                        @if($referee->referee)
                            ({{ $referee->referee->referee_code }}) - {{ $referee->referee->level_label }}
                        @endif
                    </option>
                @endforeach
            </optgroup>
        @endif

        @if($otherReferees->count() > 0)
            <optgroup label="👥 ALTRI ARBITRI DELLA ZONA ({{ $otherReferees->count() }})">
                @foreach($otherReferees as $referee)
                    <option value="{{ $referee->id }}" style="color: #666;">
                        {{ $referee->name }}
                        @if($referee->referee)
                            ({{ $referee->referee->referee_code }}) - {{ $referee->referee->level_label }}
                        @endif
                    </option>
                @endforeach
            </optgroup>
        @endif
    </select>
    @error('user_id')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

            {{-- Role --}}
            <div>
                <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                    Ruolo *
                </label>
                <select name="role" id="role"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required>
                    <option value="">Seleziona un ruolo</option>
                    <option value="Arbitro">Arbitro</option>
                    <option value="Direttore di Torneo">Direttore di Torneo</option>
                    <option value="Osservatore">Osservatore</option>
                </select>
                @error('role')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Notes --}}
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                    Note
                </label>
                <textarea name="notes" id="notes" rows="3"
                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Note aggiuntive per l'assegnazione...">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Submit Buttons --}}
            <div class="flex justify-end space-x-4">
                <a href="{{ route('admin.assignments.index') }}"
                   class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Annulla
                </a>
                <button type="submit"
                        class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Assegna Arbitro
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tournamentSelect = document.getElementById('tournament_id');
    const refereeSelect = document.getElementById('user_id');

    if (tournamentSelect && refereeSelect) {
        tournamentSelect.addEventListener('change', function() {
            if (this.value) {
                // Ricarica la pagina con il torneo selezionato per aggiornare arbitri
                const url = new URL(window.location);
                url.searchParams.set('tournament_id', this.value);
                window.location.href = url.toString();
            }
        });
    }
});
</script>
@endpush

@endsection
