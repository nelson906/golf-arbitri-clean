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

        <div class="grid grid-cols-1 md:grid-cols-2 pt-6">
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-6">
                    <h4>Totale Designazioni per ruolo</h4>
                    <table class="min-w-full divide-y divide-gray-200 gap-6 mt-4">
                        @foreach ($stats['per_ruolo'] as $ruolo => $totale)
                            <tr>
                                <td>{{ $ruolo }}</td>
                                <td>{{  $totale }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>

            <div class="md:w-6/12 px-4">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h4>Designazioni per zona</h4>
                        <table class="min-w-full divide-y divide-gray-200 gap-6 mt-4">
                            @foreach ($stats['per_zona'] as $zona => $totale)
                                <tr>
                                    <td>zona {{ $zona }}</td>
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
                labels: {!! json_encode($stats['per_zona']->keys()) !!},
                datasets: [{
                    label: 'per_zona',
                    data: {!! json_encode($stats['per_zona']->values()) !!}
                }]
            }
        });
    </script>
@endsection
