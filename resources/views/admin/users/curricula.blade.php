@extends('layouts.admin')

@section('content')
<style>
    .table-curricula {
    font-size: 14px;
}

.table-curricula td {
    vertical-align: middle;
}

.badge {
    font-size: 12px;
    padding: 4px 8px;
}

/* Fix per anno selector */
.year-selector {
    display: inline-block;
    width: auto;
    margin-left: 10px;
}
</style>
<div class="container mx-auto px-4">
    <div class="grid grid-cols-1 md:grid-cols-1 gap-6 mb-4">
        <div class="md:">
            <h2>Curricula Arbitri</h2>
        </div>
        <div class="md:w-6/12 px-4 text-right">
            {{-- Selettore Anno --}}
            <form method="GET" action="{{ route('admin.referees.curricula') }}" class="inline">
                <select name="year" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent inline w-auto" onchange="this.form.submit()">
                    @foreach($availableYears as $year)
                        <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>
                            Anno {{ $year }}
                        </option>
                    @endforeach
                </select>
                @if(request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif
            </form>
        </div>
    </div>

    {{-- Form di ricerca --}}
    <form method="GET" action="{{ route('admin.referees.curricula') }}" class="mb-4">
        <input type="hidden" name="year" value="{{ $selectedYear }}">
        <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
            <div class="md:">
                <input type="text"
                       name="search"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Cerca per nome, email"
                       value="{{ request('search') }}">
            </div>

            @if(in_array(auth()->user()->user_type, ['national_admin', 'super_admin']))
            <div class="md:w-3/12 px-4">
                <select name="zone_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Tutte le zone</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                            {{ $zone->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="md:w-2/12 px-4">
                <button type="submit" class="px-4 py-2 rounded-lg font-medium transition-colors duration-200 bg-blue-600 hover:bg-blue-700 text-white">Cerca</button>
            </div>
        </div>
    </form>

    {{-- Tabella --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Codice</th>
                    <th>Livello Attuale</th>
                    <th>Zona</th>
                    <th>Tornei Totali</th>
                    <th>Ultimo Torneo</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($referees as $referee)
                <tr>
                    <td>{{ $referee->name }}</td>
                    <td>{{ $referee->fiscal_code }}</td>
                    <td>
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full badge-{{ $referee->level == 'Nazionale' ? 'primary' : ($referee->level == 'Internazionale' ? 'success' : 'secondary') }}">
                            {{ $referee->level ?? 'N/D' }}
                        </span>
                    </td>
                    <td>{{ $referee->zone->code ?? 'N/D' }}</td>
                    <td>
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">{{ $referee->tournaments_count }}</span>
                    </td>
                    <td>
                        @if($referee->last_tournament)
                            <div>{{ $referee->last_tournament->name }}</div>
                            <small class="text-gray-500">
                                {{ \Carbon\Carbon::parse($referee->last_tournament->start_date)->format('d/m/Y') }}
                            </small>
                        @else
                            <span class="text-gray-500">-</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex" role="group">
                            <a href="{{ route('admin.referee.curriculum', $referee->id) }}?year={{ $selectedYear }}"
                               class="px-4 py-2 rounded-lg font-medium transition-colors duration-200 px-3 py-1 text-sm bg-blue-600 hover:bg-blue-700 text-white">
                                Visualizza
                            </a>
                            {{-- <a href="{{ route(name: 'admin.referee.curriculum.print', $referee->id) }}?year={{ $selectedYear }}"
                               class="px-4 py-2 rounded-lg font-medium transition-colors duration-200 px-3 py-1 text-sm bg-gray-500 hover:bg-gray-600 text-white"
                               target="_blank">
                                Stampa
                            </a> --}}
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center">Nessun arbitro trovato</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginazione --}}
    <div class="flex justify-center">
        {{ $referees->appends(request()->query())->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script>
// Mantieni i parametri di ricerca quando cambi anno
document.addEventListener('DOMContentLoaded', function() {
    const yearSelect = document.querySelector('select[name="year"]');
    if (yearSelect) {
        yearSelect.addEventListener('change', function() {
            const form = this.closest('form');
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && searchInput.value) {
                const hiddenSearch = document.createElement('input');
                hiddenSearch.type = 'hidden';
                hiddenSearch.name = 'search';
                hiddenSearch.value = searchInput.value;
                form.appendChild(hiddenSearch);
            }
        });
    }
});
</script>
@endpush
