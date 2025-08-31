@extends('layouts.admin')

@section('title', 'Tornei')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Tornei</h1>
            <p class="mt-2 text-gray-600">Visualizza i tornei disponibili</p>
        </div>
        <div class="flex space-x-4">
            <a href="{{ route('tournaments.calendar') }}"
               class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Calendario
            </a>
        </div>
    </div>

    {{-- Simple Search --}}
    <div class="bg-white shadow rounded-lg p-4 mb-6">
        <form method="GET" action="{{ route('tournaments.index') }}" class="flex gap-4">
            <div class="flex-1">
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Cerca tornei..."
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700">
                Cerca
            </button>
            @if(request('search'))
            <a href="{{ route('tournaments.index') }}" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
                Reset
            </a>
            @endif
        </form>
    </div>

    {{-- Tournaments Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($tournaments as $tournament)
        <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow duration-200">
            <div class="p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-semibold text-gray-900 truncate">
                        {{ $tournament->name }}
                    </h3>
                    <span class="px-2 py-1 text-xs font-medium rounded-full
                        {{ $tournament->status === 'open' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                        {{ $tournament->status === 'open' ? 'Aperto' : ucfirst($tournament->status) }}
                    </span>
                </div>

                <div class="space-y-2 text-sm text-gray-600 mb-4">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        {{ $tournament->club->name }}
                    </div>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        {{ $tournament->start_date->format('d/m/Y') }} - {{ $tournament->end_date->format('d/m/Y') }}
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full mr-2" style="background-color: {{ $tournament->tournamentCategory->calendar_color }}"></div>
                        {{ $tournament->tournamentCategory->name }}
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    {{-- Link Visualizza dettagli (esistente) --}}
                    <a href="{{ route('tournaments.show', $tournament) }}"
                       class="text-indigo-600 hover:text-indigo-800 font-medium">
                        Visualizza dettagli →
                    </a>

                    {{-- Pulsanti azione per Admin/SuperAdmin/NationalAdmin --}}
                    @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('national_admin') || auth()->user()->hasRole('super_admin'))
                        <div class="flex space-x-2">
                            {{-- Pulsante Invia Notifica (se ci sono arbitri assegnati) --}}
                            @if($tournament->assignedReferees && $tournament->assignedReferees->count() > 0)
                                <a href="{{ route('admin.tournaments.show-assignment-form', $tournament) }}"
                                   class="inline-flex items-center px-3 py-1 text-xs font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 transition-colors">
                                    📧 Notifica
                                </a>
                            @endif

                            {{-- Pulsante Assegna Arbitri (se non ci sono arbitri assegnati) --}}
                            @if(!$tournament->assignedReferees || $tournament->assignedReferees->count() == 0)
                                <a href="{{ route('admin.tournaments.show', $tournament) }}"
                                   class="inline-flex items-center px-3 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                                    👥 Assegna
                                </a>
                            @endif

                            {{-- Pulsante Modifica --}}
                            <a href="{{ route('admin.tournaments.edit', $tournament) }}"
                               class="inline-flex items-center px-3 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors">
                                ✏️ Modifica
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-full text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            <p class="text-gray-500">Nessun torneo trovato</p>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-8">
        {{ $tournaments->withQueryString()->links() }}
    </div>
</div>
@endsection
