{{-- resources/views/admin/statistics/disponibilita.blade.php --}}

@extends('layouts.admin')

@section('header')
    <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            📅 Statistiche Disponibilità
        </h2>
        <div class="flex space-x-2">
            <a href="{{ route('admin.statistics.dashboard') }}"
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                ← Dashboard Statistiche
            </a>
        </div>
    </div>
@endsection

@section('content')
    {{-- Filtri --}}
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">🔍 Filtri</h3>
        <form method="GET" action="{{ route('admin.statistics.disponibilita') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700">Mese</label>
                    <select name="month" id="month" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="">Tutti i mesi</option>
                        @for($i = 1; $i <= 12; $i++)
                            <option value="{{ $i }}" {{ $month == $i ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($i)->format('F') }}
                            </option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700">Data da</label>
                    <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700">Data a</label>
                    <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Applica Filtri
                </button>
            </div>
            {{-- Mantieni ordinamento nei filtri --}}
            <input type="hidden" name="sort" value="{{ $sortBy }}">
            <input type="hidden" name="direction" value="{{ $sortDirection }}">
        </form>
    </div>

    {{-- Statistiche riepilogo --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span class="text-2xl">📅</span>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Totale Disponibilità
                            </dt>
                            <dd class="text-lg font-medium text-gray-900">
                                {{ number_format($stats['total']) }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span class="text-2xl">👥</span>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Arbitri Attivi
                            </dt>
                            <dd class="text-lg font-medium text-gray-900">
                                {{ number_format($stats['arbitri_con_disponibilita']) }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span class="text-2xl">🏆</span>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Tornei con Disponibilità
                            </dt>
                            <dd class="text-lg font-medium text-gray-900">
                                {{ number_format($stats['tornei_con_disponibilita']) }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span class="text-2xl">📊</span>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Tasso Conversione
                            </dt>
                            <dd class="text-lg font-medium text-gray-900">
                                {{ $stats['conversion_rate'] ?? 0 }}%
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- CLASSIFICA NOMINATIVA UNIFICATA --}}
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">🏆 Classifica Arbitri</h3>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            #
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Arbitro
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="?sort=availabilities_count&direction={{ $sortBy === 'availabilities_count' && $sortDirection === 'desc' ? 'asc' : 'desc' }}&{{ http_build_query(request()->except(['sort', 'direction'])) }}"
                               class="flex items-center hover:text-gray-700">
                                📅 Disponibilità
                                @if($sortBy === 'availabilities_count')
                                    <span class="ml-1">{{ $sortDirection === 'desc' ? '↓' : '↑' }}</span>
                                @endif
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="?sort=assignments_count&direction={{ $sortBy === 'assignments_count' && $sortDirection === 'desc' ? 'asc' : 'desc' }}&{{ http_build_query(request()->except(['sort', 'direction'])) }}"
                               class="flex items-center hover:text-gray-700">
                                🎯 Assegnazioni
                                @if($sortBy === 'assignments_count')
                                    <span class="ml-1">{{ $sortDirection === 'desc' ? '↓' : '↑' }}</span>
                                @endif
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="?sort=level&direction={{ $sortBy === 'level' && $sortDirection === 'desc' ? 'asc' : 'desc' }}&{{ http_build_query(request()->except(['sort', 'direction'])) }}"
                               class="flex items-center hover:text-gray-700">
                                Livello
                                @if($sortBy === 'level')
                                    <span class="ml-1">{{ $sortDirection === 'desc' ? '↓' : '↑' }}</span>
                                @endif
                            </a>
                        </th>
                        @if($isNationalAdmin)
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Zona
                        </th>
                        @endif
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($stats['referees_ranking'] as $index => $referee)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $index + 1 }}.
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="text-sm font-medium text-gray-900">{{ $referee->name }}</div>
                                @if($referee->referee_code)
                                    <div class="text-xs text-gray-500 ml-2">({{ $referee->referee_code }})</div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                {{ $referee->availabilities_count }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $referee->assignments_count }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-900">{{ $referee->level }}</span>
                        </td>
                        @if($isNationalAdmin)
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $referee->zone->name ?? 'N/A' }}
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($stats['referees_ranking']->isEmpty())
            <div class="text-center py-8">
                <span class="text-2xl">📭</span>
                <p class="text-gray-500 mt-2">Nessun arbitro trovato</p>
            </div>
        @endif
    </div>

    {{-- Lista delle disponibilità --}}
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold">📋 Elenco Disponibilità</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Arbitro
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Torneo
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Data
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Circolo
                        </th>
                        @if($isNationalAdmin)
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Zona
                        </th>
                        @endif
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Data Disponibilità
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($availabilities as $availability)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $availability->referee->name }}</div>
                            <div class="text-sm text-gray-500">{{ $availability->referee->level }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">{{ $availability->tournament->name }}</div>
                            <div class="text-sm text-gray-500">{{ $availability->tournament->tournamentType->name ?? 'N/A' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ \Carbon\Carbon::parse($availability->tournament->start_date)->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">{{ $availability->tournament->club->name }}</div>
                        </td>
                        @if($isNationalAdmin)
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $availability->tournament->zone->name ?? 'N/A' }}
                        </td>
                        @endif
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ \Carbon\Carbon::parse($availability->submitted_at)->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Paginazione --}}
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $availabilities->appends(request()->query())->links() }}
        </div>
    </div>
@endsection
