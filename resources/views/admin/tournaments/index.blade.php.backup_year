@extends('layouts.admin')

@section('title', 'Gestione Tornei')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
<x-table-header
    title="Gestione Tornei"
    description="Gestisci i tornei della tua zona"
    :create-route="route('admin.tournaments.create')"
    create-text="Nuovo Torneo">

    {{-- Azioni aggiuntive opzionali --}}
    <x-slot name="additionalActions">
<a href="{{ route('tournaments.calendar') }}" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
    Calendario
</a>
    </x-slot>
</x-table-header>

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

    {{-- Filters --}}
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <form method="GET" action="{{ route('admin.tournaments.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            {{-- Search --}}
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Cerca</label>
                <input type="text"
                       name="search"
                       id="search"
                       value="{{ request('search') }}"
                       placeholder="Nome torneo o circolo..."
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            {{-- Status Filter --}}
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                <select name="status" id="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tutti gli stati</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Zone Filter (only for national admins) --}}
            @if($isNationalAdmin)
            <div>
                <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                <select name="zone_id" id="zone_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tutte le zone</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                            {{ $zone->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            {{-- Category Filter --}}
            <div>
                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                <select name="type_id" id="type_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tutte le categorie</option>
                    @foreach($tournamentTypes as $type)
                        <option value="{{ $type->id }}" {{ request('category_id') == $type->id ? 'selected' : '' }}>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Month Filter --}}
            <div>
                <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Mese</label>
                <input type="month"
                       name="month"
                       id="month"
                       value="{{ request('month') }}"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            {{-- Submit Button --}}
            <div class="flex items-end space-x-2">
                <button type="submit" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition duration-200">
                    Filtra
                </button>
                <a href="{{ route('admin.tournaments.index') }}"
                   class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 transition duration-200">
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
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Torneo
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Date
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Circolo
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Categoria
                    </th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Arbitri
                    </th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Stato
                    </th>
                    <th scope="col" class="relative px-6 py-3">
                        <span class="sr-only">Azioni</span>
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
                            {{-- Scadenza: {{ $tournament->availability_deadline->format('d/m/Y') ??}} --}}
                            @if($tournament->days_until_deadline >= 0)
                                <span class="text-xs {{ $tournament->days_until_deadline <= 3 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                    ({{ $tournament->days_until_deadline }} giorni)
                                </span>
                            @else
                                <span class="text-xs text-gray-500">(scaduta)</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">
                            {{ $tournament->start_date->format('d/m') }} - {{ $tournament->end_date->format('d/m/Y') }}
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $tournament->start_date->diffInDays($tournament->end_date) + 1 }} giorni
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900">{{ $tournament->club->name }}</div>
                        @if($isNationalAdmin)
                            <div class="text-xs text-gray-500">{{ $tournament->zone->name }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full mr-2"
                                 style="background-color: {{ $tournament->tournamentType->calendar_color }}"></div>
                            <span class="text-sm text-gray-900">
                                {{ $tournament->tournamentType->short_name }}
                            </span>
                        </div>
                        @if($tournament->tournamentType->is_national)
                            <span class="text-xs text-blue-600">Nazionale</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="text-sm text-gray-900">
                            {{ $tournament->assignments()->count() }} / {{ $tournament->required_referees }}
                        </div>
                        <div class="text-xs text-gray-500">
                            Disp: {{ $tournament->availabilities()->count() }}
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                            bg-{{ $tournament->status_color }}-100 text-{{ $tournament->status_color }}-800">
                            {{ $tournament->status_label }}
                        </span>
                    </td>
<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
    <div class="flex space-x-2">
        <a href="{{ route('admin.tournaments.show', $tournament) }}"
           class="text-indigo-600 hover:text-indigo-900">Visualizza</a>

        <a href="{{ route('admin.assignments.assign-referees', $tournament) }}"
           class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">
            👥 Assegna Comitato
        </a>

        @if($tournament->assignments()->count() > 0)
            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">
                {{ $tournament->assignments()->count() }} assegnati
            </span>
        @endif
    </div>
</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
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

    {{-- Summary Stats --}}
    <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-3xl font-bold text-indigo-600">
                {{ $tournaments->total() }}
            </div>
            <div class="text-sm text-gray-600 mt-1">Tornei Totali</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-3xl font-bold text-green-600">
                {{ $tournaments->where('status', 'open')->count() }}
            </div>
            <div class="text-sm text-gray-600 mt-1">Aperti</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-3xl font-bold text-yellow-600">
                {{ $tournaments->whereIn('status', ['closed', 'assigned'])->count() }}
            </div>
            <div class="text-sm text-gray-600 mt-1">In Corso</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-3xl font-bold text-gray-600">
                {{ $tournaments->where('status', 'completed')->count() }}
            </div>
            <div class="text-sm text-gray-600 mt-1">Completati</div>
        </div>
    </div>
</div>
@endsection
