{{-- File: resources/views/admin/tournaments/show.blade.php --}}
@extends('layouts.admin')

@section('title', 'Dettaglio Torneo - ' . $tournament->name)

@section('content')
<div class="py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">🏆 {{ $tournament->name }}</h1>
                <p class="text-gray-600 mt-1">Dettaglio completo del torneo</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.tournaments.index') }}"
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                    ← Torna alla Lista
                </a>
                <a href="{{ route('admin.tournaments.edit', $tournament) }}"
                   class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg transition-colors">
                    ✏️ Modifica
                </a>
            </div>
        </div>
    </div>

    {{-- Messaggi Flash --}}
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    {{-- Informazioni Torneo --}}
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">📊 Informazioni Torneo</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Colonna Sinistra --}}
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Nome Torneo</label>
                        <p class="text-gray-900 font-semibold">{{ $tournament->name }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-500">Circolo</label>
                        <p class="text-gray-900">
                            @if($tournament->club)
                                {{ $tournament->club->name }}
                                @if($tournament->club->city)
                                    <span class="text-gray-500 text-sm">({{ $tournament->club->city }})</span>
                                @endif
                            @else
                                <span class="text-gray-400">N/A</span>
                            @endif
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-500">Zona</label>
                        <p class="text-gray-900">
                            @if($tournament->club && $tournament->club->zone)
                                {{ $tournament->club->zone->name }}
                            @else
                                <span class="text-gray-400">N/A</span>
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Colonna Destra --}}
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Tipo Torneo</label>
                        <p class="text-gray-900">
                            @if($tournament->tournamentType)
                                {{ $tournament->tournamentType->name }}
                                @if($tournament->tournamentType->is_national)
                                    <span class="ml-2 px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded">Nazionale</span>
                                @endif
                            @else
                                <span class="text-gray-400">Standard</span>
                            @endif
                        </p>
                    </div>

                    @if(\Schema::hasColumn('tournaments', 'date') && $tournament->date)
                    <div>
                        <label class="text-sm font-medium text-gray-500">Data</label>
                        <p class="text-gray-900">
                            {{ \Carbon\Carbon::parse($tournament->date)->format('d/m/Y') }}
                        </p>
                    </div>
                    @endif

                    @if(\Schema::hasColumn('tournaments', 'status'))
                    <div>
                        <label class="text-sm font-medium text-gray-500">Stato</label>
                        <p>
                            @php
                                $statusColors = [
                                    'active' => 'bg-green-100 text-green-800',
                                    'completed' => 'bg-gray-100 text-gray-800',
                                    'cancelled' => 'bg-red-100 text-red-800',
                                    'draft' => 'bg-yellow-100 text-yellow-800',
                                ];
                                $statusLabels = [
                                    'active' => 'Attivo',
                                    'completed' => 'Completato',
                                    'cancelled' => 'Cancellato',
                                    'draft' => 'Bozza',
                                ];
                            @endphp
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $statusColors[$tournament->status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ $statusLabels[$tournament->status] ?? $tournament->status ?? 'N/A' }}
                            </span>
                        </p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Note --}}
            @if($tournament->notes)
            <div class="mt-6 pt-6 border-t border-gray-200">
                <label class="text-sm font-medium text-gray-500">Note</label>
                <p class="text-gray-900 mt-1">{{ $tournament->notes }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Azioni Rapide --}}
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">⚡ Azioni Rapide</h2>
        </div>
        <div class="p-6">
            <div class="flex flex-wrap gap-3">
                {{-- Assegna Arbitri --}}
                <a href="{{ route('admin.assignments.assign-referees', $tournament) }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                        </path>
                    </svg>
                    Assegna Arbitri
                </a>

                {{-- Visualizza Assegnazioni --}}
                <a href="{{ route('admin.assignments.index', ['tournament_id' => $tournament->id]) }}"
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                        </path>
                    </svg>
                    Vedi Assegnazioni
                </a>

                {{-- Modifica Torneo --}}
                <a href="{{ route('admin.tournaments.edit', $tournament) }}"
                   class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition-colors inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                        </path>
                    </svg>
                    Modifica Torneo
                </a>

                {{-- Invia Notifiche (se ci sono arbitri assegnati) --}}
                @if($tournament->assignments->count() > 0)
                <a href="{{ route('admin.tournaments.show-assignment-form', $tournament) }}"
                   class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-colors inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                        </path>
                    </svg>
                    Invia Notifiche
                </a>
                @endif

                {{-- Duplica Torneo (opzionale) --}}
                @if(isset($canDuplicate) && $canDuplicate)
                <a href="{{ route('admin.tournaments.duplicate', $tournament) }}"
                   class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z">
                        </path>
                    </svg>
                    Duplica Torneo
                </a>
                @endif
            </div>
        </div>
    </div>
@php

@endphp
    {{-- Arbitri Assegnati --}}
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-900">
                👥 Arbitri Assegnati
                @if(isset($tournament->assignments))
                    ({{ $tournament->assignments->count() }})
                @endif
            </h2>
            <a href="{{ route('admin.assignments.assign-referees', $tournament) }}"
               class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                + Aggiungi Arbitri
            </a>
        </div>
        <div class="p-6">
            @if(isset($tournament->assignments) && $tournament->assignments->count() > 0)
                <div class="space-y-3">
                    @foreach($tournament->assignments as $assignment)
                    <div class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded hover:bg-gray-100">
                        <div class="flex items-center space-x-3">
                            <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold">
                                {{ strtoupper(substr($assignment->user->name ?? 'A', 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">
                                    {{ $assignment->user->name ?? 'N/A' }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    @if($assignment->role)
                                        {{ $assignment->role }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            @if(\Schema::hasColumn('assignments', 'status') && $assignment->status)
                                @php
                                    $statusColors = [
                                        'confirmed' => 'bg-green-100 text-green-800',
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                    ];
                                    $statusLabels = [
                                        'confirmed' => 'Confermato',
                                        'pending' => 'In Attesa',
                                        'cancelled' => 'Cancellato',
                                    ];
                                @endphp
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $statusColors[$assignment->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $statusLabels[$assignment->status] ?? $assignment->status }}
                                </span>
                            @endif

                            <a href="{{ route('admin.assignments.edit', $assignment) }}"
                               class="text-yellow-600 hover:text-yellow-800"
                               title="Modifica">
                                ✏️
                            </a>

                            <form action="{{ route('admin.assignments.destroy', $assignment) }}"
                                  method="POST"
                                  class="inline"
                                  onsubmit="return confirm('Rimuovere questo arbitro dal torneo?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="text-red-600 hover:text-red-800"
                                        title="Rimuovi">
                                    🗑️
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <span class="text-4xl">👥</span>
                    <p class="mt-2 text-gray-500">Nessun arbitro assegnato</p>
                    <a href="{{ route('admin.assignments.assign-referees', $tournament) }}"
                       class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors">
                        Assegna Primo Arbitro
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- Statistiche --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <span class="text-2xl mr-3">📊</span>
                <div>
                    <p class="text-sm text-gray-500">Arbitri Assegnati</p>
                    <p class="text-xl font-semibold text-gray-900">
                        {{ isset($tournament->assignments) ? $tournament->assignments->count() : 0 }}
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <span class="text-2xl mr-3">✅</span>
                <div>
                    <p class="text-sm text-gray-500">Confermati</p>
                    <p class="text-xl font-semibold text-gray-900">
                        @if(isset($assignments))
                            {{ $assignments->where('status', 'confirmed')->count() }}
                        @else
                            0
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <span class="text-2xl mr-3">⏳</span>
                <div>
                    <p class="text-sm text-gray-500">In Attesa</p>
                    <p class="text-xl font-semibold text-gray-900">
                        @if(isset($assignments))
                            {{ $assignments->where('status', 'pending')->count() }}
                        @else
                            0
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Pulsante Elimina (con conferma) --}}
    <div class="mt-8 pt-6 border-t border-gray-200">
        <form action="{{ route('admin.tournaments.destroy', $tournament) }}"
              method="POST"
              onsubmit="return confirm('Sei sicuro di voler eliminare questo torneo? Questa azione non può essere annullata.');"
              class="inline">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors">
                🗑️ Elimina Torneo
            </button>
        </form>
    </div>
</div>
@endsection
