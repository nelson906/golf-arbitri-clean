@extends('layouts.admin')

@section('content')
    <div class="container mx-auto">
        <h1>Statistiche Designazioni</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h5>Totale Designazioni</h5>
                        <h2>{{ number_format($stats['totale_assegnazioni']) }}</h2>
                    </div>
                </div>
            </div>

            <div class="md: px-4">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h5>Media Arbitri per Torneo</h5>
                        <h2>{{ $stats['media_arbitri_torneo'] }}</h2>
                    </div>
                </div>
            </div>

            <div class="md: px-4">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h5>Tornei Designati</h5>
                        <h2>{{ $stats['tornei_assegnati'] }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-6">
            {{-- Designazioni per ruolo --}}
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-6">
                    <h4 class="text-lg font-semibold mb-4">Totale Designazioni per ruolo</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Ruolo
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Totale
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($stats['by_role'] as $ruolo => $totale)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $ruolo }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ number_format($totale) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="px-6 py-4 text-center text-gray-500">
                                            Nessun dato disponibile
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Designazioni per zona --}}
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-6">
                    <h4 class="text-lg font-semibold mb-4">Designazioni per zona</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Zona
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Totale
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($stats['per_zona'] as $zona => $totale)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $zona }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ number_format($totale) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="px-6 py-4 text-center text-gray-500">
                                            Nessun dato disponibile
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            {{-- Workload Arbitri --}}
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-6">
                    <h4 class="text-lg font-semibold mb-4">Carico di Lavoro</h4>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Media Assegnazioni:</span>
                            <span
                                class="text-sm font-medium">{{ number_format($stats['workload']['avg_assignments'] ?? 0, 1) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Max Assegnazioni:</span>
                            <span class="text-sm font-medium">{{ $stats['workload']['max_assignments'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Min Assegnazioni:</span>
                            <span class="text-sm font-medium">{{ $stats['workload']['min_assignments'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Arbitri Sovraccarichi:</span>
                            <span
                                class="text-sm font-medium text-red-600">{{ $stats['workload']['overloaded_referees'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <canvas id="myChart"></canvas>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        new Chart(document.getElementById('myChart'), {
            type: 'bar',
            data: {
                labels: {!! json_encode($stats['per_zona']->keys()) !!},
                datasets: [{
                    label: 'per_zona',
                    data: {!! json_encode($stats['per_zona']->values()) !!}
                }]
            }
        });
    </script>
@endsection
