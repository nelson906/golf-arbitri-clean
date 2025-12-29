@extends('layouts.admin')

@section('header')
    <div class="flex justify-between items-center">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📈 Metriche Performance
            </h2>
            <p class="text-gray-600 mt-1">Analisi delle performance del sistema</p>
        </div>
        <div class="flex space-x-2">
            <select name="period" class="rounded-md border-gray-300 text-sm" onchange="window.location.href='{{ route('admin.statistics.performance') }}?period=' + this.value">
                <option value="7" {{ $period == 7 ? 'selected' : '' }}>Ultimi 7 giorni</option>
                <option value="30" {{ $period == 30 ? 'selected' : '' }}>Ultimi 30 giorni</option>
                <option value="90" {{ $period == 90 ? 'selected' : '' }}>Ultimi 90 giorni</option>
            </select>
        </div>
    </div>
@endsection

@section('content')
<div class="space-y-6">
    {{-- Metriche Principali --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-sm font-medium text-gray-500">Tempo di Risposta</h3>
                <p class="mt-2 text-3xl font-semibold text-gray-900">
                    {{ number_format(array_sum($metrics['response_time']) / max(count($metrics['response_time']), 1), 2) }}s
                </p>
                <p class="mt-1 text-sm text-green-600">↑ 5% miglioramento</p>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-sm font-medium text-gray-500">Efficienza Assegnazioni</h3>
                <p class="mt-2 text-3xl font-semibold text-gray-900">
                    {{ number_format(array_sum($metrics['assignment_efficiency']) / max(count($metrics['assignment_efficiency']), 1), 1) }}%
                </p>
                <p class="mt-1 text-sm text-green-600">↑ 2.3% rispetto al mese precedente</p>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-sm font-medium text-gray-500">Engagement Utenti</h3>
                <p class="mt-2 text-3xl font-semibold text-gray-900">
                    {{ number_format(array_sum($metrics['user_engagement']) / max(count($metrics['user_engagement']), 1), 1) }}%
                </p>
                <p class="mt-1 text-sm text-yellow-600">→ Stabile</p>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-sm font-medium text-gray-500">Salute Sistema</h3>
                <p class="mt-2 text-3xl font-semibold text-gray-900">
                    {{ number_format(array_sum($metrics['system_health']) / max(count($metrics['system_health']), 1), 1) }}%
                </p>
                <p class="mt-1 text-sm text-green-600">✓ Ottimo</p>
            </div>
        </div>
    </div>

    {{-- Grafici Trend --}}
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">📊 Trend Disponibilità</h3>
        </div>
        <div class="p-6">
            <canvas id="trendsChart" style="height: 300px;"></canvas>
        </div>
    </div>

    {{-- Dettagli Performance --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">⚡ Tempi di Risposta</h3>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <div class="flex justify-between text-sm">
                        <span>Dashboard</span>
                        <span class="font-medium">0.8s</span>
                    </div>
                    <div class="mt-1 bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: 80%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm">
                        <span>Assegnazioni</span>
                        <span class="font-medium">1.2s</span>
                    </div>
                    <div class="mt-1 bg-gray-200 rounded-full h-2">
                        <div class="bg-yellow-600 h-2 rounded-full" style="width: 60%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm">
                        <span>Report</span>
                        <span class="font-medium">2.1s</span>
                    </div>
                    <div class="mt-1 bg-gray-200 rounded-full h-2">
                        <div class="bg-yellow-600 h-2 rounded-full" style="width: 40%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">🎯 KPI Chiave</h3>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Tasso Conversione Disponibilità</span>
                    <span class="text-lg font-medium text-gray-900">72.5%</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Tempo Medio Assegnazione</span>
                    <span class="text-lg font-medium text-gray-900">48h</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Arbitri Attivi/Mese</span>
                    <span class="text-lg font-medium text-gray-900">85%</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Copertura Tornei</span>
                    <span class="text-lg font-medium text-gray-900">98.2%</span>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Grafico Trend
    const ctx = document.getElementById('trendsChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'],
            datasets: [{
                label: 'Disponibilità',
                data: [65, 68, 70, 72, 75, 78, 82, 80, 78, 75, 73, 70],
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4
            }, {
                label: 'Assegnazioni',
                data: [60, 62, 65, 68, 70, 73, 78, 76, 74, 72, 70, 68],
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
</script>
@endpush
@endsection
