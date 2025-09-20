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
                            @foreach (\App\Models\Zone::orderBy('name')->get() as $zone)
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
                        <button type="submit"
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
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
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Torneo
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Circolo
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Categoria
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Arbitri
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Stato
                            </th>
                            {{-- ✅ CORREZIONE: Header colonna azioni visibile --}}
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Azioni
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($tournaments as $tournament)
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $tournament->name }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Scadenza:
                                        {{ Carbon\Carbon::parse($tournament->availability_deadline)->format('d/m/Y') }}
                                        @if ($tournament->days_until_deadline >= 0)
                                            <span
                                                class="text-xs {{ $tournament->days_until_deadline <= 3 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                                ({{ $tournament->days_until_deadline }} giorni)
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-500">(scaduta)</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ $tournament->start_date->format('d/m') }} -
                                        {{ $tournament->end_date->format('d/m/Y') }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $tournament->start_date->diffInDays($tournament->end_date) + 1 }} giorni
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">{{ $tournament->club->name }}</div>
                                    @if ($isNationalAdmin && $tournament->club->zone)
                                        <div class="text-xs text-gray-500">{{ $tournament->club->zone->name }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 rounded-full mr-2"
                                            style="background-color: {{ $tournament->tournamentType->calendar_color }}">
                                        </div>
                                        <span class="text-sm text-gray-900">
                                            {{ $tournament->tournamentType->short_name }}
                                        </span>
                                    </div>
                                    @if ($tournament->tournamentType->is_national)
                                        <span class="text-xs text-blue-600">Nazionale</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-sm text-gray-900">
                                        {{ $tournament->assignments()->count() }} /
                                        {{ $tournament->tournamentType->min_referees }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Disp: {{ $tournament->availabilities()->count() }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                    bg-{{ $tournament->status_color }}-100 text-{{ $tournament->status_color }}-800">
                                        {{ $tournament->status_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    {{-- ✅ MIGLIORAMENTO: Layout azioni più compatto --}}
                                    <div class="flex items-center justify-end space-x-2">
                                        {{-- Link Visualizza --}}
                                        <a href="{{ route('tournaments.show', $tournament) }}"
                                            class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                            Visualizza
                                        </a>

                                        {{-- Bottone Assegna Comitato --}}
                                        {{-- <a href="{{ route('admin.assignments.assign-referees', $tournament) }}"
                                            class="bg-green-600 text-white px-3 py-1.5 rounded text-xs font-medium hover:bg-green-700 transition-colors">
                                            👥 Assegna
                                        </a> --}}

                                        {{-- Stato Assegnazioni + Notifica --}}
                                        @if ($tournament->assignments()->count() > 0)
                                            <div class="flex items-center space-x-2">
                                                {{-- Badge assegnazioni --}}
                                                <span
                                                    class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">
                                                    {{ $tournament->assignments()->count() }} assegnati
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                        </path>
                                    </svg>
                                    <p class="text-gray-500">Nessun torneo trovato</p>
                                    <p class="text-sm text-gray-400 mt-1">Prova a modificare i filtri di ricerca</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $tournaments->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection
