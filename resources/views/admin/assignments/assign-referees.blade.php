{{-- File: resources/views/admin/assignments/assign-referees.blade.php --}}
@extends('layouts.admin')

@section('title', 'Assegna Arbitri - ' . $tournament->name)

@section('content')
<div class="py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">📋 Assegna Arbitri</h1>
                <p class="text-gray-600 mt-1">
                    Torneo: <strong>{{ $tournament->name }}</strong>
                    @if($tournament->club)
                        - {{ $tournament->club->name }}
                    @endif
                </p>
            </div>
            <a href="{{ route('admin.assignments.index') }}"
               class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                ← Torna alle Assegnazioni
            </a>
        </div>
    </div>

    {{-- Info Torneo --}}
    <div class="bg-white rounded-lg shadow mb-6 p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <span class="text-sm text-gray-500">Circolo:</span>
                <p class="font-medium">{{ $tournament->club->name ?? 'N/A' }}</p>
            </div>
            <div>
                <span class="text-sm text-gray-500">Zona:</span>
                <p class="font-medium">
                    @if($tournament->club && $tournament->club->zone)
                        {{ $tournament->club->zone->name }}
                    @else
                        N/A
                    @endif
                </p>
            </div>
            <div>
                <span class="text-sm text-gray-500">Tipo:</span>
                <p class="font-medium">
                    @if($tournament->tournamentType)
                        {{ $tournament->tournamentType->name }}
                        @if($tournament->tournamentType->is_national)
                            <span class="ml-2 px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded">Nazionale</span>
                        @endif
                    @else
                        Standard
                    @endif
                </p>
            </div>
        </div>
    </div>

    {{-- Arbitri Già Assegnati --}}
    @if($assignedReferees && $assignedReferees->count() > 0)
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">
                ✅ Arbitri Già Assegnati ({{ $assignedReferees->count() }})
            </h2>
        </div>
        <div class="p-6">
            <div class="space-y-2">
                @foreach($assignedReferees as $assignment)
                <div class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded">
                    <div class="flex items-center space-x-3">
                        <span class="text-green-600">✓</span>
                        <div>
                            <span class="font-medium">{{ $assignment->name ?? 'N/A' }}</span>
                            @if(isset($assignment->referee_code))
                                <span class="text-sm text-gray-500 ml-2">({{ $assignment->referee_code }})</span>
                            @endif
                            @if($assignment->role)
                                <span class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                                    {{ $assignment->role }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <form action="{{ route('admin.assignments.removeFromTournament', [$tournament->id, $assignment->user_id]) }}"
                          method="POST"
                          class="inline"
                          onsubmit="return confirm('Rimuovere questo arbitro?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:text-red-800">
                            ✕ Rimuovi
                        </button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Form Assegnazione --}}
    <form action="{{ route('admin.assignments.storeMultiple', $tournament) }}" method="POST">
        @csrf

        {{-- Arbitri Disponibili --}}
        @if($availableReferees && $availableReferees->count() > 0)
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-green-50">
                <h2 class="text-lg font-semibold text-gray-900">
                    👍 Arbitri Disponibili ({{ $availableReferees->count() }})
                </h2>
                <p class="text-sm text-gray-600 mt-1">Hanno dichiarato disponibilità per questo torneo</p>
            </div>
            <div class="p-6">
                <div class="space-y-2">
                    @foreach($availableReferees as $referee)
                    <div class="flex items-center space-x-4 py-2 px-3 hover:bg-gray-50 rounded">
                        <input type="checkbox"
                               name="referee_ids[]"
                               value="{{ $referee->id }}"
                               id="referee_{{ $referee->id }}"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <label for="referee_{{ $referee->id }}" class="flex-1 cursor-pointer">
                            <span class="font-medium">{{ $referee->name }}</span>
                            @if($referee->referee_code)
                                <span class="text-sm text-gray-500 ml-2">({{ $referee->referee_code }})</span>
                            @endif
                            @if($referee->zone)
                                <span class="text-sm text-gray-500 ml-2">- {{ $referee->zone->name }}</span>
                            @endif
                        </label>
                        <input type="text"
                               name="roles[{{ $referee->id }}]"
                               placeholder="Ruolo (opzionale)"
                               class="w-40 px-2 py-1 text-sm border border-gray-300 rounded">
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Arbitri Possibili --}}
        @if($possibleReferees && $possibleReferees->count() > 0)
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-yellow-50">
                <h2 class="text-lg font-semibold text-gray-900">
                    🤔 Arbitri Possibili ({{ $possibleReferees->count() }})
                </h2>
                <p class="text-sm text-gray-600 mt-1">Stessa zona, non hanno dichiarato disponibilità</p>
            </div>
            <div class="p-6">
                <div class="space-y-2">
                    @foreach($possibleReferees as $referee)
                    <div class="flex items-center space-x-4 py-2 px-3 hover:bg-gray-50 rounded">
                        <input type="checkbox"
                               name="referee_ids[]"
                               value="{{ $referee->id }}"
                               id="referee_{{ $referee->id }}"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <label for="referee_{{ $referee->id }}" class="flex-1 cursor-pointer">
                            <span class="font-medium">{{ $referee->name }}</span>
                            @if($referee->referee_code)
                                <span class="text-sm text-gray-500 ml-2">({{ $referee->referee_code }})</span>
                            @endif
                            @if($referee->zone)
                                <span class="text-sm text-gray-500 ml-2">- {{ $referee->zone->name }}</span>
                            @endif
                        </label>
                        <input type="text"
                               name="roles[{{ $referee->id }}]"
                               placeholder="Ruolo (opzionale)"
                               class="w-40 px-2 py-1 text-sm border border-gray-300 rounded">
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Arbitri Nazionali --}}
        @if($nationalReferees && $nationalReferees->count() > 0)
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-purple-50">
                <h2 class="text-lg font-semibold text-gray-900">
                    🌟 Arbitri Nazionali/Internazionali ({{ $nationalReferees->count() }})
                </h2>
                <p class="text-sm text-gray-600 mt-1">Per tornei nazionali</p>
            </div>
            <div class="p-6">
                <div class="space-y-2">
                    @foreach($nationalReferees as $referee)
                    <div class="flex items-center space-x-4 py-2 px-3 hover:bg-gray-50 rounded">
                        <input type="checkbox"
                               name="referee_ids[]"
                               value="{{ $referee->id }}"
                               id="referee_{{ $referee->id }}"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <label for="referee_{{ $referee->id }}" class="flex-1 cursor-pointer">
                            <span class="font-medium">{{ $referee->name }}</span>
                            @if($referee->referee_code)
                                <span class="text-sm text-gray-500 ml-2">({{ $referee->referee_code }})</span>
                            @endif
                            @if($referee->level)
                                <span class="ml-2 px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded">
                                    Livello {{ $referee->level }}
                                </span>
                            @endif
                            @if($referee->zone)
                                <span class="text-sm text-gray-500 ml-2">- {{ $referee->zone->name }}</span>
                            @endif
                        </label>
                        <input type="text"
                               name="roles[{{ $referee->id }}]"
                               placeholder="Ruolo (opzionale)"
                               class="w-40 px-2 py-1 text-sm border border-gray-300 rounded">
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Se non ci sono arbitri disponibili --}}
        @if(
            (!$availableReferees || $availableReferees->count() == 0) &&
            (!$possibleReferees || $possibleReferees->count() == 0) &&
            (!$nationalReferees || $nationalReferees->count() == 0)
        )
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <span class="text-2xl">⚠️</span>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Nessun arbitro disponibile</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>Non ci sono arbitri disponibili da assegnare a questo torneo.</p>
                        <p class="mt-1">Possibili motivi:</p>
                        <ul class="list-disc list-inside mt-1">
                            <li>Tutti gli arbitri sono già stati assegnati</li>
                            <li>Nessun arbitro ha dichiarato disponibilità</li>
                            <li>Non ci sono arbitri attivi nella zona</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Bottoni Azione --}}
        <div class="flex justify-between items-center mt-6">
            <div class="text-sm text-gray-600">
                Seleziona gli arbitri da assegnare e specifica opzionalmente il ruolo
            </div>
            <div class="space-x-3">
                <a href="{{ route('admin.assignments.index') }}"
                   class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors">
                    Annulla
                </a>
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                    💾 Salva Assegnazioni
                </button>
            </div>
        </div>
    </form>
</div>

{{-- Script per selezione multipla --}}
@push('scripts')
<script>
    // Seleziona/Deseleziona tutti
    function toggleAll(section) {
        const checkboxes = document.querySelectorAll(`#${section} input[type="checkbox"]`);
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
    }
</script>
@endpush
@endsection
