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
        @foreach($tournament->assignments()->with('user')->get() as $assignment)
        <div class="bg-white p-3 rounded border border-green-200 flex justify-between items-center">
            <div>
                <p class="font-medium text-gray-900">{{ $assignment->user->name }}</p>
                <p class="text-sm text-gray-600">
                    {{ $assignment->user->referee_code ?? 'N/A' }} -
                    {{ $assignment->user->level_label ?? 'N/A' }}
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
<div class="relative">
    <input type="hidden" name="tournament_id" id="tournament_id_hidden" value="{{ old('tournament_id') }}">
    <input type="text"
           id="tournament_search"
           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
           placeholder="Cerca torneo per nome..."
           autocomplete="off">
    <div id="tournament_results"
         class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto hidden">
    </div>
    <div id="tournament_selected" class="hidden mt-2 p-3 bg-green-50 rounded-md border border-green-200">
        <div class="flex justify-between items-start">
            <div>
                <div class="text-sm font-medium text-gray-900" id="selected_tournament_name"></div>
                <div class="text-sm text-gray-500" id="selected_tournament_info"></div>
            </div>
            <button type="button" id="clear_tournament" class="text-red-500 hover:text-red-700 text-sm">
                ✕ Rimuovi
            </button>
        </div>
    </div>
</div>
@php
    $tournamentsForSearch = $tournaments->map(fn($t) => [
        'id' => $t->id,
        'name' => $t->name,
        'date' => $t->start_date->format('d/m/Y'),
        'club' => $t->club ? ($t->club->code ?? $t->club->name) : '',
        'searchText' => strtolower($t->name . ' ' . ($t->club ? $t->club->name : ''))
    ])->values();
@endphp
<script>
    const tournamentsData = @json($tournamentsForSearch);
</script>
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
                        @if($referee->referee_code)
                            ({{ $referee->referee_code }}) - {{ $referee->level_label ?? 'N/A' }}
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
                        @if($referee->referee_code)
                            ({{ $referee->referee_code }}) - {{ $referee->level_label ?? 'N/A' }}
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
    const searchInput = document.getElementById('tournament_search');
    const resultsDiv = document.getElementById('tournament_results');
    const hiddenInput = document.getElementById('tournament_id_hidden');
    const selectedDiv = document.getElementById('tournament_selected');
    const selectedName = document.getElementById('selected_tournament_name');
    const selectedInfo = document.getElementById('selected_tournament_info');
    const clearBtn = document.getElementById('clear_tournament');

    if (!searchInput) return; // Torneo già selezionato

    let debounceTimer;

    // Funzione per cercare tornei
    function searchTournaments(query) {
        if (!query || query.length < 2) {
            resultsDiv.classList.add('hidden');
            return;
        }

        const searchTerms = query.toLowerCase().split(' ').filter(t => t.length > 0);
        const matches = tournamentsData.filter(t => {
            return searchTerms.every(term => t.searchText.includes(term));
        }).slice(0, 10); // Max 10 risultati

        if (matches.length === 0) {
            resultsDiv.innerHTML = '<div class="p-3 text-gray-500 text-sm">Nessun torneo trovato</div>';
        } else {
            resultsDiv.innerHTML = matches.map(t => `
                <div class="tournament-option p-3 hover:bg-indigo-50 cursor-pointer border-b border-gray-100 last:border-0"
                     data-id="${t.id}"
                     data-name="${t.name}"
                     data-date="${t.date}"
                     data-club="${t.club}">
                    <div class="text-sm font-medium text-gray-900">${highlightMatch(t.name, searchTerms)}</div>
                    <div class="text-xs text-gray-500">${t.date} ${t.club ? '- ' + t.club : ''}</div>
                </div>
            `).join('');

            // Aggiungi event listeners ai risultati
            resultsDiv.querySelectorAll('.tournament-option').forEach(opt => {
                opt.addEventListener('click', function() {
                    selectTournament(this.dataset);
                });
            });
        }
        resultsDiv.classList.remove('hidden');
    }

    // Evidenzia i termini di ricerca
    function highlightMatch(text, terms) {
        let result = text;
        terms.forEach(term => {
            const regex = new RegExp(`(${term})`, 'gi');
            result = result.replace(regex, '<span class="bg-yellow-200">$1</span>');
        });
        return result;
    }

    // Seleziona un torneo
    function selectTournament(data) {
        hiddenInput.value = data.id;
        selectedName.textContent = data.name;
        selectedInfo.textContent = `${data.date} ${data.club ? '- ' + data.club : ''}`;
        selectedDiv.classList.remove('hidden');
        searchInput.value = '';
        searchInput.classList.add('hidden');
        resultsDiv.classList.add('hidden');

        // Ricarica la pagina per aggiornare gli arbitri disponibili
        const url = new URL(window.location);
        url.searchParams.set('tournament_id', data.id);
        window.location.href = url.toString();
    }

    // Pulisci selezione
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            hiddenInput.value = '';
            selectedDiv.classList.add('hidden');
            searchInput.classList.remove('hidden');
            searchInput.focus();
        });
    }

    // Event listener per la ricerca con debounce
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            searchTournaments(this.value);
        }, 200);
    });

    // Chiudi risultati quando si clicca fuori
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.classList.add('hidden');
        }
    });

    // Navigazione con tastiera
    searchInput.addEventListener('keydown', function(e) {
        const options = resultsDiv.querySelectorAll('.tournament-option');
        const current = resultsDiv.querySelector('.tournament-option.bg-indigo-100');
        let index = Array.from(options).indexOf(current);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (current) current.classList.remove('bg-indigo-100');
            index = (index + 1) % options.length;
            if (options[index]) options[index].classList.add('bg-indigo-100');
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (current) current.classList.remove('bg-indigo-100');
            index = index <= 0 ? options.length - 1 : index - 1;
            if (options[index]) options[index].classList.add('bg-indigo-100');
        } else if (e.key === 'Enter' && current) {
            e.preventDefault();
            selectTournament(current.dataset);
        } else if (e.key === 'Escape') {
            resultsDiv.classList.add('hidden');
        }
    });

    // Focus mostra risultati se c'è già testo
    searchInput.addEventListener('focus', function() {
        if (this.value.length >= 2) {
            searchTournaments(this.value);
        }
    });
});
</script>
@endpush

@endsection
