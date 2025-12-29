@extends('layouts.admin')

@section('header')
    <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            📈 Metriche Performance
        </h2>
        <a href="{{ route('admin.monitoring.dashboard') }}"
            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
            ← Torna al Dashboard
        </a>
    </div>
@endsection

@section('content')
    <div class="space-y-6">

        {{-- Metriche Principali --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="text-2xl font-bold text-blue-600">245ms</div>
                <div class="text-sm text-gray-500">Tempo Risposta Medio</div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="text-2xl font-bold text-green-600">1.2s</div>
                <div class="text-sm text-gray-500">Query Database Medie</div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="text-2xl font-bold text-purple-600">2.1%</div>
                <div class="text-sm text-gray-500">Tasso Errori</div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="text-2xl font-bold text-yellow-600">85</div>
                <div class="text-sm text-gray-500">Richieste/min</div>
            </div>
        </div>

        {{-- Dettagli Performance --}}
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">📊 Dettagli Performance</h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">

                    <div class="border rounded p-4">
                        <h4 class="font-medium mb-2">Tempi di Risposta</h4>
                        <div class="grid grid-cols-4 gap-4 text-sm">
                            <div>Min: <span class="font-mono">95ms</span></div>
                            <div>Media: <span class="font-mono">245ms</span></div>
                            <div>Max: <span class="font-mono">1.2s</span></div>
                            <div>P95: <span class="font-mono">485ms</span></div>
                        </div>
                    </div>

                    <div class="border rounded p-4">
                        <h4 class="font-medium mb-2">Performance Database</h4>
                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div>Query/sec: <span class="font-mono">12.5</span></div>
                            <div>Slow queries: <span class="font-mono">2</span></div>
                            <div>Connessioni: <span class="font-mono">3/100</span></div>
                        </div>
                    </div>

                    <div class="border rounded p-4">
                        <h4 class="font-medium mb-2">Cache Performance</h4>
                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div>Hit rate: <span class="font-mono text-green-600">89.2%</span></div>
                            <div>Miss rate: <span class="font-mono text-red-600">10.8%</span></div>
                            <div>Evictions: <span class="font-mono">45</span></div>
                        </div>
                    </div>

                    <div class="border rounded p-4">
                        <h4 class="font-medium mb-2">Risorse Sistema</h4>
                        <div class="grid grid-cols-4 gap-4 text-sm">
                            <div>CPU: <span class="font-mono">15.2%</span></div>
                            <div>Memoria: <span class="font-mono">75.8%</span></div>
                            <div>Disco: <span class="font-mono">45.1%</span></div>
                            <div>Network: <span class="font-mono">2.1MB/s</span></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- Query Lente --}}
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">🐌 Query Lente (> 500ms)</h3>
            </div>
            <div class="p-6">
                <div class="bg-gray-50 p-4 rounded font-mono text-sm">
                    <div class="space-y-2">
                        <div class="text-red-600">[1.2s] SELECT * FROM tournaments WHERE start_date >= '2025-01-01'</div>
                        <div class="text-orange-600">[650ms] SELECT u.*, z.name FROM users u LEFT JOIN zones z ON u.zone_id
                            = z.id</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
