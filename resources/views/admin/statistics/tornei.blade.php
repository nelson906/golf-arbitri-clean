@extends('layouts.admin')

@section('content')
    <div class="container mx-auto">
        <h1>Statistiche Tornei</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h5>Totale Tornei</h5>
                        <h2>{{ number_format($stats['totale_tornei']) }}</h2>
                    </div>
                </div>
            </div>

            <div class="md: px-4">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h5>Tornei già notificati</h5>
                        <h2>{{ $stats['con_notifiche'] }}</h2>
                    </div>
                </div>
            </div>

            <div class="md: px-4">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h5>Tornei nei prossimi 30 giorni</h5>
                        <h2>{{ $stats['prossimi_30_giorni'] }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 pt-6">
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-6">
                    <h4>Tornei per Zona</h4>
                    <table class="min-w-full divide-y divide-gray-200 gap-6 mt-4">
                        @foreach ($stats['per_zona'] as $name => $totale)
                            <tr>
                                <td>{{ $name }}</td>
                                <td>{{ $totale }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>

            <div class="md:w-6/12 px-4">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6">
                        <h4>Tornei per Tipo</h4>
                        <table class="min-w-full divide-y divide-gray-200">
                            @foreach ($stats['by_category'] as $name => $totale)
                                <tr>
                                    <td>{{ $name }}</td>
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
                    label: 'Tornei per zone',
                    data: {!! json_encode($stats['per_zona']->values()) !!}
                }]
            }
        });
    </script>
@endsection
