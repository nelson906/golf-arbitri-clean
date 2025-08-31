@extends('layouts.admin')

@section('content')
    <div class="container mx-auto">
        <h1>Statistiche Disponibilità</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h5>Totale Disponibilità</h5>
                        <h2>{{ number_format($stats['totale_disponibilita']) }}</h2>
                    </div>
                </div>
            </div>

            <div class="md: px-4">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h5>Arbitri Attivi</h5>
                        <h2>{{ $stats['arbitri_con_disponibilita'] }}</h2>
                    </div>
                </div>
            </div>

            <div class="md: px-4">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h5>Tornei Coperti</h5>
                        <h2>{{ $stats['tornei_con_disponibilita'] }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 pt-6">
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-6">
                    <h4>Top 10 Arbitri per Disponibilità</h4>
                    <table class="min-w-full divide-y divide-gray-200 gap-6 mt-4">
                        @foreach ($stats['top_arbitri'] as $arbitro)
                            <tr>
                                <td>{{ $arbitro->name }}</td>
                                <td>{{ $arbitro->availabilities_count }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>

            <div class="md:w-6/12 px-4">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h4>Disponibilità per Mese</h4>
                        <table class="min-w-full divide-y divide-gray-200">
                            @foreach ($stats['disponibilita_per_mese'] as $mese => $totale)
                                <tr>
                                    <td>Mese {{ $mese }}</td>
                                    <td>{{ $totale }}</td>
                                </tr>
                            @endforeach
                        </table>
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
                labels: {!! json_encode($stats['disponibilita_per_mese']->keys()) !!},
                datasets: [{
                    label: 'Disponibilità',
                    data: {!! json_encode($stats['disponibilita_per_mese']->values()) !!}
                }]
            }
        });
    </script>
@endsection
