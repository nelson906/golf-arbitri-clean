@extends('layouts.admin')

@section('header')
    <div class="flex justify-between items-center">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📊 Dashboard Statistiche
            </h2>
            <p class="text-gray-600 mt-1">Panoramica completa delle metriche di sistema</p>
        </div>
        <div class="flex space-x-2">
            <select id="period-selector" class="rounded-md border-gray-300 text-sm">
                <option value="7" {{ $period == 7 ? 'selected' : '' }}>Ultimi 7 giorni</option>
                <option value="30" {{ $period == 30 ? 'selected' : '' }}>Ultimi 30 giorni</option>
                <option value="90" {{ $period == 90 ? 'selected' : '' }}>Ultimi 90 giorni</option>
                <option value="365" {{ $period == 365 ? 'selected' : '' }}>Ultimo anno</option>
            </select>
            <a href="{{ route('admin.statistics.export', ['type' => 'general']) }}"
               class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                📥 Esporta CSV
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Statistiche Generali --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                            <span class="text-white text-lg">🏆</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Tornei Totali</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ number_format($generalStats['total_tournaments']) }}</dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="text-sm text-gray-600">
                        Attivi: <span class="font-medium text-green-600">{{ $generalStats['active_tournaments'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                            <span class="text-white text-lg">👨‍💼</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Arbitri Totali</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ number_format($generalStats['total_referees']) }}</dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="text-sm text-gray-600">
                        Attivi: <span class="font-medium text-green-600">{{ $generalStats['active_referees'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                            <span class="text-white text-lg">📝</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Assegnazioni</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ number_format($generalStats['total_assignments']) }}</dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="text-sm text-gray-600">
                        Pending: <span class="font-medium text-yellow-600">{{ $generalStats['pending_assignments'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                            <span class="text-white text-lg">📊</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Performance</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ number_format($performanceMetrics['assignment_rate'], 1) }}%</dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="text-sm text-gray-600">
                        Tasso Assegnazione
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Collegamenti Rapidi --}}
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">🔗 Collegamenti Rapidi</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="{{ route('admin.statistics.disponibilita') }}"
                   class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg border border-blue-200 transition-colors">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">📅</span>
                        <div>
                            <div class="font-medium text-blue-900">Disponibilità</div>
                            <div class="text-sm text-blue-600">Analisi dichiarazioni</div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('admin.statistics.assegnazioni') }}"
                   class="bg-green-50 hover:bg-green-100 p-4 rounded-lg border border-green-200 transition-colors">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">📝</span>
                        <div>
                            <div class="font-medium text-green-900">Assegnazioni</div>
                            <div class="text-sm text-green-600">Statistiche incarichi</div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('admin.statistics.tornei') }}"
                   class="bg-purple-50 hover:bg-purple-100 p-4 rounded-lg border border-purple-200 transition-colors">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">🏆</span>
                        <div>
                            <div class="font-medium text-purple-900">Tornei</div>
                            <div class="text-sm text-purple-600">Analisi eventi</div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('admin.statistics.arbitri') }}"
                   class="bg-yellow-50 hover:bg-yellow-100 p-4 rounded-lg border border-yellow-200 transition-colors">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">👨‍💼</span>
                        <div>
                            <div class="font-medium text-yellow-900">Arbitri</div>
                            <div class="text-sm text-yellow-600">Performance arbitrali</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    {{-- Statistiche per Zona (Solo National Admin) --}}
    @if($isNationalAdmin && !empty($zoneStats))
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">🗺️ Statistiche per Zone</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach($zoneStats as $zoneStat)
                <div class="border rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900">{{ $zoneStat['name'] }}</h4>
                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                            {{ $zoneStat['active_referees'] }}/{{ $zoneStat['referees'] }} attivi
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Tornei:</span>
                            <span class="font-medium">{{ $zoneStat['tournaments'] }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Arbitri:</span>
                            <span class="font-medium">{{ $zoneStat['referees'] }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Statistiche Periodo --}}
    @if($periodStats)
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">📈 Attività Recente ({{ $period }} giorni)</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ $periodStats['new_tournaments'] }}</div>
                    <div class="text-sm text-gray-500">Nuovi Tornei</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">{{ $periodStats['new_assignments'] }}</div>
                    <div class="text-sm text-gray-500">Nuove Assegnazioni</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">{{ $periodStats['new_availabilities'] }}</div>
                    <div class="text-sm text-gray-500">Nuove Disponibilità</div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Statistiche Arbitri --}}
    @if($refereeStats)
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">👨‍💼 Statistiche Arbitri</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-3">Distribuzione per Livello</h4>
                    <div class="space-y-2">
                        @forelse($refereeStats['by_level'] as $level => $count)
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">{{ ucfirst($level) }}</span>
                            <span class="font-medium">{{ $count }}</span>
                        </div>
                        @empty
                        <p class="text-gray-500 text-sm">Nessun dato disponibile</p>
                        @endforelse
                    </div>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-3">Tasso di Attivazione</h4>
                    <div class="flex items-center">
                        <div class="flex-1 bg-gray-200 rounded-full h-2 mr-3">
                            <div class="bg-green-500 h-2 rounded-full" style="width: {{ $refereeStats['active_percentage'] }}%"></div>
                        </div>
                        <span class="text-sm font-medium text-gray-900">{{ $refereeStats['active_percentage'] }}%</span>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Arbitri attivi su totale</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Performance Metrics --}}
    @if($performanceMetrics)
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">⚡ Metriche Performance</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">{{ $performanceMetrics['assignment_rate'] }}%</div>
                    <div class="text-sm text-gray-500">Tasso Assegnazione</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ $performanceMetrics['response_time'] }}s</div>
                    <div class="text-sm text-gray-500">Tempo Risposta</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">{{ $performanceMetrics['user_satisfaction'] }}%</div>
                    <div class="text-sm text-gray-500">Soddisfazione</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600">{{ $performanceMetrics['system_uptime'] }}%</div>
                    <div class="text-sm text-gray-500">Uptime Sistema</div>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
document.getElementById('period-selector').addEventListener('change', function(e) {
    const period = e.target.value;
    const url = new URL(window.location);
    url.searchParams.set('period', period);
    window.location = url;
});
</script>
@endpush
