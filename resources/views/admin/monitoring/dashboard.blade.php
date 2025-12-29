@extends('layouts.admin')

@section('header')
    <div class="flex justify-between items-center">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🖥️ Monitoraggio Sistema
            </h2>
            <p class="text-gray-600 mt-1">Stato in tempo reale del sistema Golf Referee Management</p>
        </div>
        <div class="flex space-x-2">
            <select id="refresh-interval" class="rounded-md border-gray-300 text-sm">
                <option value="10">Aggiorna ogni 10s</option>
                <option value="30" selected>Aggiorna ogni 30s</option>
                <option value="60">Aggiorna ogni 60s</option>
                <option value="0">Disabilita auto-refresh</option>
            </select>
            <button id="refresh-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                🔄 Aggiorna
            </button>
            <a href="{{ route('admin.monitoring.health') }}"
               class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                🏥 Health Check
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Status Generale Sistema --}}
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">🚦 Stato Generale</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg border border-green-200">
                    <div>
                        <div class="text-sm font-medium text-green-800">Sistema</div>
                        <div class="text-xs text-green-600">{{ $healthStatus['overall'] === 'healthy' ? 'Operativo' : 'Problemi' }}</div>
                    </div>
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                </div>

                <div class="flex items-center justify-between p-3 {{ $healthStatus['database'] === 'healthy' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }} rounded-lg border">
                    <div>
                        <div class="text-sm font-medium {{ $healthStatus['database'] === 'healthy' ? 'text-green-800' : 'text-red-800' }}">Database</div>
                        <div class="text-xs {{ $healthStatus['database'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">{{ $healthStatus['database'] === 'healthy' ? 'Connesso' : 'Errore' }}</div>
                    </div>
                    <div class="w-3 h-3 {{ $healthStatus['database'] === 'healthy' ? 'bg-green-500' : 'bg-red-500' }} rounded-full"></div>
                </div>

                <div class="flex items-center justify-between p-3 {{ $healthStatus['cache'] === 'healthy' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }} rounded-lg border">
                    <div>
                        <div class="text-sm font-medium {{ $healthStatus['cache'] === 'healthy' ? 'text-green-800' : 'text-red-800' }}">Cache</div>
                        <div class="text-xs {{ $healthStatus['cache'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">{{ $healthStatus['cache'] === 'healthy' ? 'Attiva' : 'Errore' }}</div>
                    </div>
                    <div class="w-3 h-3 {{ $healthStatus['cache'] === 'healthy' ? 'bg-green-500' : 'bg-red-500' }} rounded-full"></div>
                </div>

                <div class="flex items-center justify-between p-3 {{ $healthStatus['storage'] === 'healthy' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }} rounded-lg border">
                    <div>
                        <div class="text-sm font-medium {{ $healthStatus['storage'] === 'healthy' ? 'text-green-800' : 'text-red-800' }}">Storage</div>
                        <div class="text-xs {{ $healthStatus['storage'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">{{ $healthStatus['storage'] === 'healthy' ? 'Disponibile' : 'Errore' }}</div>
                    </div>
                    <div class="w-3 h-3 {{ $healthStatus['storage'] === 'healthy' ? 'bg-green-500' : 'bg-red-500' }} rounded-full"></div>
                </div>

                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg border border-green-200">
                    <div>
                        <div class="text-sm font-medium text-green-800">Servizi</div>
                        <div class="text-xs text-green-600">Attivi</div>
                    </div>
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Metriche Real-time --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                            <span class="text-white text-lg">⏱️</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Uptime</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $metrics['uptime'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                            <span class="text-white text-lg">🧠</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Memoria</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $metrics['memory_usage']['percentage'] }}%</dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="text-sm text-gray-600">
                        {{ $metrics['memory_usage']['used'] }} / {{ $metrics['memory_usage']['limit'] }}
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                            <span class="text-white text-lg">💾</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Disco</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $metrics['disk_usage']['percentage'] }}%</dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="text-sm text-gray-600">
                        {{ $metrics['disk_usage']['used'] }} / {{ $metrics['disk_usage']['total'] }}
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                            <span class="text-white text-lg">⚡</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">CPU Load</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $metrics['cpu_load'] }}%</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Statistiche Real-time --}}
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">📊 Statistiche in Tempo Reale</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ $realtimeStats['requests_per_minute'] }}</div>
                    <div class="text-sm text-gray-500">Richieste/minuto</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">{{ $realtimeStats['active_sessions'] }}</div>
                    <div class="text-sm text-gray-500">Sessioni Attive</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">{{ $realtimeStats['queue_size'] }}</div>
                    <div class="text-sm text-gray-500">Code di Lavoro</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold {{ $realtimeStats['error_rate'] > 5 ? 'text-red-600' : 'text-green-600' }}">{{ $realtimeStats['error_rate'] }}%</div>
                    <div class="text-sm text-gray-500">Tasso Errori</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Avvisi Sistema --}}
    @if(!empty($alerts))
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">⚠️ Avvisi Sistema</h3>
        </div>
        <div class="p-6">
            <div class="space-y-3">
                @foreach($alerts as $alert)
                <div class="flex items-center p-3 rounded-lg border {{ $alert['type'] === 'critical' ? 'bg-red-50 border-red-200' : ($alert['type'] === 'warning' ? 'bg-yellow-50 border-yellow-200' : 'bg-blue-50 border-blue-200') }}">
                    <div class="flex-shrink-0">
                        @if($alert['type'] === 'critical')
                            <span class="text-red-500 text-xl">🚨</span>
                        @elseif($alert['type'] === 'warning')
                            <span class="text-yellow-500 text-xl">⚠️</span>
                        @else
                            <span class="text-blue-500 text-xl">ℹ️</span>
                        @endif
                    </div>
                    <div class="ml-3 flex-1">
                        <div class="text-sm font-medium {{ $alert['type'] === 'critical' ? 'text-red-800' : ($alert['type'] === 'warning' ? 'text-yellow-800' : 'text-blue-800') }}">
                            {{ $alert['message'] }}
                        </div>
                        <div class="text-xs {{ $alert['type'] === 'critical' ? 'text-red-600' : ($alert['type'] === 'warning' ? 'text-yellow-600' : 'text-blue-600') }}">
                            {{ $alert['timestamp']->diffForHumans() }}
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Azioni Rapide --}}
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">🔧 Azioni Sistema</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <button onclick="clearCache()"
                        class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg border border-blue-200 transition-colors text-left">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">🗑️</span>
                        <div>
                            <div class="font-medium text-blue-900">Pulisci Cache</div>
                            <div class="text-sm text-blue-600">Svuota cache sistema</div>
                        </div>
                    </div>
                </button>

                <button onclick="optimizeSystem()"
                        class="bg-green-50 hover:bg-green-100 p-4 rounded-lg border border-green-200 transition-colors text-left">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">⚡</span>
                        <div>
                            <div class="font-medium text-green-900">Ottimizza</div>
                            <div class="text-sm text-green-600">Ottimizza sistema</div>
                        </div>
                    </div>
                </button>

                <a href="{{ route('admin.monitoring.logs') }}"
                   class="bg-purple-50 hover:bg-purple-100 p-4 rounded-lg border border-purple-200 transition-colors">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">📋</span>
                        <div>
                            <div class="font-medium text-purple-900">Log Sistema</div>
                            <div class="text-sm text-purple-600">Visualizza log</div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('admin.monitoring.performance') }}"
                   class="bg-yellow-50 hover:bg-yellow-100 p-4 rounded-lg border border-yellow-200 transition-colors">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">📈</span>
                        <div>
                            <div class="font-medium text-yellow-900">Performance</div>
                            <div class="text-sm text-yellow-600">Metriche dettagliate</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    {{-- Performance Overview --}}
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">📈 Performance Overview</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">{{ $performance['response_time_avg'] }}ms</div>
                    <div class="text-sm text-gray-500">Tempo Risposta Medio</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ $performance['throughput'] }}</div>
                    <div class="text-sm text-gray-500">Throughput (req/sec)</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold {{ $performance['error_rate'] > 5 ? 'text-red-600' : 'text-green-600' }}">{{ $performance['error_rate'] }}%</div>
                    <div class="text-sm text-gray-500">Tasso Errori</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">{{ $performance['uptime_percentage'] }}%</div>
                    <div class="text-sm text-gray-500">Uptime</div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
let refreshTimer;

// Salva e ripristina l'intervallo scelto
function saveRefreshSetting(value) {
    sessionStorage.setItem('golf_refresh_interval', value);
}

function loadRefreshSetting() {
    return sessionStorage.getItem('golf_refresh_interval') || '30';
}

// Imposta l'intervallo
function setRefreshInterval(seconds) {
    if (refreshTimer) {
        clearInterval(refreshTimer);
        refreshTimer = null;
    }

    if (seconds > 0) {
        refreshTimer = setInterval(() => location.reload(), seconds * 1000);
        console.log('✓ Refresh attivo:', seconds, 'secondi');
    } else {
        console.log('✓ Refresh disabilitato');
    }
}

// Inizializza quando tutto è caricato
window.onload = function() {
    const select = document.getElementById('refresh-interval');
    if (!select) return;

    // Ripristina il valore salvato
    const savedValue = loadRefreshSetting();
    select.value = savedValue;

    // Avvia con il valore ripristinato
    setRefreshInterval(parseInt(savedValue));

    // Listener per i cambi
    select.onchange = function() {
        const newValue = this.value;
        saveRefreshSetting(newValue);
        setRefreshInterval(parseInt(newValue));
    };

    // Refresh manuale
    const btn = document.getElementById('refresh-btn');
    if (btn) btn.onclick = () => location.reload();
};

function clearCache() {
    if (confirm('Pulire la cache?')) {
        fetch('{{ route("admin.monitoring.clear-cache") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({types: ['application', 'config', 'route', 'view']})
        })
        .then(response => response.json())
        .then(data => {
            alert(data.status === 'success' ? 'Cache pulita!' : 'Errore: ' + data.message);
            if (data.status === 'success') location.reload();
        });
    }
}

function optimizeSystem() {
    if (confirm('Ottimizzare il sistema?')) {
        fetch('{{ route("admin.monitoring.optimize") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({operations: ['config', 'route', 'view']})
        })
        .then(response => response.json())
        .then(data => {
            alert(data.status === 'success' ? 'Sistema ottimizzato!' : 'Errore: ' + data.message);
            if (data.status === 'success') location.reload();
        });
    }
}
</script>
@endpush

