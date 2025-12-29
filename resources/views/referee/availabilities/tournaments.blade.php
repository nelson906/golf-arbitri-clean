@extends('layouts.app')

@section('title', 'Dichiara Disponibilità')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dichiara Disponibilità</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Seleziona i tornei per cui sei disponibile
                    </p>
                </div>
                <div>
                    <a href="{{ route('user.availability.index') }}"
                       class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Le Mie Disponibilità
                    </a>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="mb-6 bg-white rounded-lg shadow p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                    <select name="zone_id" id="zone_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Tutte le zone accessibili</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="tournament_type_id" class="block text-sm font-medium text-gray-700 mb-1">Tipo Torneo</label>
                    <select name="tournament_type_id" id="tournament_type_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Tutti i tipi</option>
                        @foreach($tournamentTypes as $type)
                            <option value="{{ $type->id }}" {{ request('tournament_type_id') == $type->id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Mese</label>
                    <input type="month" name="month" id="month" value="{{ request('month') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Filtra
                    </button>
                </div>

                <div class="flex items-end">
                    <a href="{{ route('user.availability.tournaments') }}"
                       class="w-full bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium text-center">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- Tournaments List --}}
        @if($tournaments->isEmpty())
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Nessun torneo trovato</h3>
                <p class="text-gray-600">Non ci sono tornei corrispondenti ai filtri selezionati.</p>
            </div>
        @else
            <form method="POST" action="{{ route('user.availability.saveBatch') }}">
                @csrf

                {{-- Save Button --}}
                <div class="mb-4 flex justify-end">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                        Salva Disponibilità Selezionate
                    </button>
                </div>

                {{-- Tournaments Grid --}}
                <div class="grid grid-cols-1 gap-4">
                    @foreach($tournaments as $tournament)
                        @php
                            $isAvailable = in_array($tournament->id, $userAvailabilities);
                        @endphp

                        <div class="bg-white rounded-lg shadow p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 pt-0.5">
                                            <input type="checkbox"
                                                   name="availabilities[]"
                                                   value="{{ $tournament->id }}"
                                                   {{ $isAvailable ? 'checked' : '' }}
                                                   class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                                        </div>
                                        <div class="ml-3 flex-1">
                                            <div class="block text-sm font-medium text-gray-900">
                                                {{ $tournament->name }}
                                            </div>
                                            <p class="mt-1 text-sm text-gray-500">
                                                {{ $tournament->club->name ?? 'N/A' }} - {{ $tournament->club->zone->name ?? 'N/A' }}
                                            </p>
                                            <div class="mt-2 flex items-center gap-4 text-xs text-gray-500">
                                                <span class="flex items-center">
                                                    <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    {{ $tournament->start_date->format('d/m/Y') }}
                                                    @if($tournament->end_date && $tournament->start_date->format('Y-m-d') !== $tournament->end_date->format('Y-m-d'))
                                                        - {{ $tournament->end_date->format('d/m/Y') }}
                                                    @endif
                                                </span>
                                                <span>{{ $tournament->tournamentType->name ?? 'N/A' }}</span>
                                                @if($tournament->tournamentType->is_national ?? false)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                        Nazionale
                                                    </span>
                                                @endif
                                            </div>
                                            @if($tournament->availability_deadline)
                                                <p class="mt-1 text-xs {{ $tournament->availability_deadline < now()->addDays(7) ? 'text-red-600' : 'text-gray-500' }}">
                                                    Scadenza disponibilità: {{ Carbon\Carbon::parse($tournament->availability_deadline)->format('d/m/Y H:i') }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Quick Action --}}
                                <div class="ml-4">
                                    @if($isAvailable)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Disponibile
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                <div class="mt-6">
                    {{ $tournaments->appends(request()->query())->links() }}
                </div>

                {{-- Save Button Bottom --}}
                <div class="mt-6 flex justify-end">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                        Salva Disponibilità Selezionate
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection

