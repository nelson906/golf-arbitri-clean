@extends('layouts.app')

@section('title', 'Lista Tornei')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Lista Tornei</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Visualizza tutti i tornei disponibili
                    </p>
                </div>
                <div>
                    <a href="{{ route('tournaments.calendar') }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        📅 Vista Calendario
                    </a>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="mb-6 bg-white rounded-lg shadow p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Cerca</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}"
                           placeholder="Nome torneo o circolo..."
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>

                <div>
                    <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                    <select name="zone_id" id="zone_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Tutte le zone</option>
                        @foreach(\App\Models\Zone::orderBy('name')->get() as $zone)
                            <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Mese</label>
                    <input type="month" name="month" id="month" value="{{ request('month') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Filtra
                    </button>
                    <a href="{{ route('tournaments.index') }}"
                       class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- Tournaments Table --}}
        @if($tournaments->isEmpty())
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Nessun torneo trovato</h3>
                <p class="text-gray-600">Non ci sono tornei corrispondenti ai filtri selezionati.</p>
            </div>
        @else
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Torneo
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tipo
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Circolo / Zona
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Stato
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($tournaments as $tournament)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $tournament->name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ $tournament->tournamentType->name ?? 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $tournament->club->name ?? 'N/A' }}</div>
                                    <div class="text-sm text-gray-500">{{ $tournament->zone->name ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ $tournament->start_date->format('d/m/Y') }}
                                        @if($tournament->end_date && $tournament->start_date->format('Y-m-d') !== $tournament->end_date->format('Y-m-d'))
                                            - {{ $tournament->end_date->format('d/m/Y') }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @php
                                        $statusColors = [
                                            'open' => 'bg-green-100 text-green-800',
                                            'closed' => 'bg-red-100 text-red-800',
                                            'assigned' => 'bg-blue-100 text-blue-800',
                                            'completed' => 'bg-gray-100 text-gray-800',
                                        ];
                                        $statusLabels = [
                                            'open' => 'Aperto',
                                            'closed' => 'Chiuso',
                                            'assigned' => 'Assegnato',
                                            'completed' => 'Completato',
                                        ];
                                    @endphp
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColors[$tournament->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ $statusLabels[$tournament->status] ?? ucfirst($tournament->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $tournaments->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
