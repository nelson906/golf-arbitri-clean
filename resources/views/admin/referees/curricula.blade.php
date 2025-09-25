@extends('layouts.admin')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-900">
                        📊 Curriculum Arbitri
                    </h2>
                    {{-- Filtri --}}
                    <div class="flex flex-wrap gap-4">
                        <form method="GET" class="flex flex-wrap gap-4" action="{{ route('admin.referees.curricula') }}">
                            <input type="text"
                                   name="search"
                                   placeholder="Cerca arbitro..."
                                   value="{{ request('search') }}"
                                   class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">

                            <select name="year"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($years as $y)
                                    <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>
                                        {{ $y }}
                                    </option>
                                @endforeach
                            </select>

                            <select name="zone"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Tutte le zone</option>
                                @foreach(\App\Models\Zone::orderBy('name')->get() as $z)
                                    <option value="{{ $z->id }}" {{ request('zone') == $z->id ? 'selected' : '' }}>
                                        {{ $z->name }}
                                    </option>
                                @endforeach
                            </select>

                            <select name="level"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Tutti i livelli</option>
                                <option value="Nazionale" {{ request('level') === 'Nazionale' ? 'selected' : '' }}>Nazionale</option>
                                <option value="Zonale" {{ request('level') === 'Zonale' ? 'selected' : '' }}>Zonale</option>
                            </select>

                            <select name="sort"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="last_name" {{ request('sort', 'last_name') === 'last_name' ? 'selected' : '' }}>Cognome</option>
                                <option value="first_name" {{ request('sort') === 'first_name' ? 'selected' : '' }}>Nome</option>
                                <option value="referee_code" {{ request('sort') === 'referee_code' ? 'selected' : '' }}>Codice</option>
                            </select>

                            <select name="direction"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="asc" {{ request('direction', 'asc') === 'asc' ? 'selected' : '' }}>Crescente</option>
                                <option value="desc" {{ request('direction') === 'desc' ? 'selected' : '' }}>Decrescente</option>
                            </select>

                            @if(request()->anyFilled(['search', 'zone', 'level', 'sort', 'direction']))
                                <a href="{{ route('admin.referees.curricula', ['year' => $year]) }}"
                                   class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                    Reset
                                </a>
                            @endif
                        </form>
                    </div>
                </div>

                {{-- Contatore risultati --}}
                <div class="mb-4 text-sm text-gray-600">
                    Trovati {{ count($stats) }} arbitri
                </div>

                {{-- Tabella --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Arbitro
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Zona
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Livello {{ $year }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Tornei {{ $year }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Come DT {{ $year }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Totali Carriera
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Azioni
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($stats as $stat)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $stat['referee']->name }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    {{ $stat['referee']->referee_code }}
                                                    <small>({{ $stat['referee']->level }})</small>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            {{ $stat['referee']->zone->name ?? 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if(isset($stat['year_data']['level']) && $stat['year_data']['level'])
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                {{ in_array($stat['year_data']['level'], ['Nazionale', 'Internazionale']) ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                                {{ $stat['year_data']['level'] }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            {{ $stat['year_data']['total_tournaments'] ?? 0 }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            {{ $stat['year_data']['roles']['Direttore di Torneo'] ?? 0 }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $stat['stats']['total_assignments'] ?? 0 }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            @if(isset($stat['stats']['first_year']) && $stat['stats']['first_year'])
                                                dal {{ $stat['stats']['first_year'] }}
                                            @elseif(isset($stat['stats']['total_assignments']) && $stat['stats']['total_assignments'] > 0)
                                                dal {{ $year }}
                                            @else
                                                -
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('admin.referees.curriculum', $stat['referee']) }}"
                                           class="text-indigo-600 hover:text-indigo-900">
                                            Dettaglio
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                        Nessun arbitro trovato con i criteri selezionati
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Autosubmit dei filtri select
        document.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', () => {
                select.form.submit();
            });
        });

        // Submit al press di Enter nel campo search
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.form.submit();
            }
        });
    });
</script>
@endsection
