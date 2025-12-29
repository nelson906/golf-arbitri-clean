@extends('layouts.admin')

@section('content')
    <div class="container mx-auto">
        <h1>Statistiche Arbitri</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h5>Totale Arbitri</h5>
                        <h2>{{ number_format($stats['totale_arbitri']) }}</h2>
                    </div>
                </div>
            </div>

            <div class="md: px-4">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h5>Media Arbitri già designati</h5>
                        <h2>{{ $stats['con_assegnazioni'] }}</h2>
                    </div>
                </div>
            </div>

            <div class="md: px-4">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h5>attivi_ultimo_mese</h5>
                        <h2>{{ $stats['attivi_ultimo_mese'] }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 pt-6">
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-6">
                    <h4>Totale Arbitri per zona</h4>
                    <table class="min-w-full divide-y divide-gray-200 gap-6 mt-4">
                        @foreach ($stats['per_zona'] as $zona => $totale)
                            <tr>
                                <td>{{ $zona }}</td>
                                <td>{{  $totale }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>

            <div class="md:w-6/12 px-4">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h4>Totale Arbitri per livello</h4>
                        <table class="min-w-full divide-y divide-gray-200">
                            @foreach ($stats['per_livello'] as $livello => $totale)
                                <tr>
                                    <td>Livello {{ $livello }}</td>
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
                labels: {!! json_encode($stats['per_livello']->keys()) !!},
                datasets: [{
                    label: 'per_livello',
                    data: {!! json_encode($stats['per_livello']->values()) !!}
                }]
            }
        });
    </script>
@endsection
