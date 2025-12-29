@extends('layouts.admin')

@section('header')
    <div class="flex justify-between items-center">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🏥 Health Check Sistema
            </h2>
            <p class="text-gray-600 mt-1">Controllo stato componenti sistema</p>
        </div>
        <div class="flex space-x-2">
            <button onclick="window.location.reload()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                🔄 Aggiorna
            </button>
            <a href="{{ route('admin.monitoring.dashboard') }}"
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                ← Torna al Dashboard
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Stato Generale --}}
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    @if($overallHealth)
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <span class="text-white text-lg">✓</span>
                        </div>
                    @else
                        <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                            <span class="text-white text-lg">✗</span>
                        </div>
                    @endif
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium {{ $overallHealth ? 'text-green-800' : 'text-red-800' }}">
                        Sistema {{ $overallHealth ? 'Operativo' : 'con Problemi' }}
                    </h3>
                    <p class="text-sm text-gray-600">Controllo effettuato: {{ \Carbon\Carbon::parse($response['timestamp'])->format('d/m/Y H:i:s') }}</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900">{{ $response['uptime'] }}</div>
                    <div class="text-sm text-gray-500">Uptime Sistema</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900">{{ $response['version'] }}</div>
                    <div class="text-sm text-gray-500">Versione App</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold {{ $overallHealth ? 'text-green-600' : 'text-red-600' }}">
                        {{ $overallHealth ? 'OK' : 'ERROR' }}
                    </div>
                    <div class="text-sm text-gray-500">Stato Generale</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Dettagli Componenti --}}
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">🔧 Stato Componenti</h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">

                {{-- Database --}}
                <div class="flex items-center justify-between p-4 {{ $checks['database']['status'] === 'healthy' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }} rounded-lg border">
                    <div class="flex items-center">
                        <div class="w-6 h-6 {{ $checks['database']['status'] === 'healthy' ? 'bg-green-500' : 'bg-red-500' }} rounded-full flex items-center justify-center mr-3">
                            <span class="text-white text-sm">{{ $checks['database']['status'] === 'healthy' ? '✓' : '✗' }}</span>
                        </div>
                        <div>
                            <div class="font-medium {{ $checks['database']['status'] === 'healthy' ? 'text-green-800' : 'text-red-800' }}">Database</div>
                            <div class="text-sm {{ $checks['database']['status'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">
                                Tempo risposta: {{ $checks['database']['response_time'] ?? 'N/A' }}
                            </div>
                        </div>
                    </div>
                    <div class="text-sm {{ $checks['database']['status'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">
                        Connessioni: {{ $checks['database']['connections'] ?? 'N/A' }}
                    </div>
                </div>

                {{-- Cache --}}
                <div class="flex items-center justify-between p-4 {{ $checks['cache']['status'] === 'healthy' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }} rounded-lg border">
                    <div class="flex items-center">
                        <div class="w-6 h-6 {{ $checks['cache']['status'] === 'healthy' ? 'bg-green-500' : 'bg-red-500' }} rounded-full flex items-center justify-center mr-3">
                            <span class="text-white text-sm">{{ $checks['cache']['status'] === 'healthy' ? '✓' : '✗' }}</span>
                        </div>
                        <div>
                            <div class="font-medium {{ $checks['cache']['status'] === 'healthy' ? 'text-green-800' : 'text-red-800' }}">Sistema Cache</div>
                            <div class="text-sm {{ $checks['cache']['status'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">
                                Driver: {{ $checks['cache']['driver'] ?? 'N/A' }}
                            </div>
                        </div>
                    </div>
                    <div class="text-sm {{ $checks['cache']['status'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">
                        {{ $checks['cache']['status'] === 'healthy' ? 'Attivo' : 'Errore' }}
                    </div>
                </div>

                {{-- Storage --}}
                <div class="flex items-center justify-between p-4 {{ $checks['storage']['status'] === 'healthy' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }} rounded-lg border">
                    <div class="flex items-center">
                        <div class="w-6 h-6 {{ $checks['storage']['status'] === 'healthy' ? 'bg-green-500' : 'bg-red-500' }} rounded-full flex items-center justify-center mr-3">
                            <span class="text-white text-sm">{{ $checks['storage']['status'] === 'healthy' ? '✓' : '✗' }}</span>
                        </div>
                        <div>
                            <div class="font-medium {{ $checks['storage']['status'] === 'healthy' ? 'text-green-800' : 'text-red-800' }}">File System</div>
                            <div class="text-sm {{ $checks['storage']['status'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">
                                Permessi di scrittura
                            </div>
                        </div>
                    </div>
                    <div class="text-sm {{ $checks['storage']['status'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">
                        {{ $checks['storage']['writable'] ? 'Scrivibile' : 'Solo lettura' }}
                    </div>
                </div>

                {{-- Mail --}}
                <div class="flex items-center justify-between p-4 {{ $checks['mail']['status'] === 'healthy' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }} rounded-lg border">
                    <div class="flex items-center">
                        <div class="w-6 h-6 {{ $checks['mail']['status'] === 'healthy' ? 'bg-green-500' : 'bg-red-500' }} rounded-full flex items-center justify-center mr-3">
                            <span class="text-white text-sm">{{ $checks['mail']['status'] === 'healthy' ? '✓' : '✗' }}</span>
                        </div>
                        <div>
                            <div class="font-medium {{ $checks['mail']['status'] === 'healthy' ? 'text-green-800' : 'text-red-800' }}">Sistema Email</div>
                            <div class="text-sm {{ $checks['mail']['status'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">
                                Driver: {{ $checks['mail']['driver'] ?? 'N/A' }}
                            </div>
                        </div>
                    </div>
                    <div class="text-sm {{ $checks['mail']['status'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">
                        {{ $checks['mail']['status'] === 'healthy' ? 'Configurato' : 'Errore' }}
                    </div>
                </div>

                {{-- Queue --}}
                <div class="flex items-center justify-between p-4 {{ $checks['queue']['status'] === 'healthy' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }} rounded-lg border">
                    <div class="flex items-center">
                        <div class="w-6 h-6 {{ $checks['queue']['status'] === 'healthy' ? 'bg-green-500' : 'bg-red-500' }} rounded-full flex items-center justify-center mr-3">
                            <span class="text-white text-sm">{{ $checks['queue']['status'] === 'healthy' ? '✓' : '✗' }}</span>
                        </div>
                        <div>
                            <div class="font-medium {{ $checks['queue']['status'] === 'healthy' ? 'text-green-800' : 'text-red-800' }}">Sistema Code</div>
                            <div class="text-sm {{ $checks['queue']['status'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">
                                Driver: {{ $checks['queue']['driver'] ?? 'N/A' }}
                            </div>
                        </div>
                    </div>
                    <div class="text-sm {{ $checks['queue']['status'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">
                        Code: {{ $checks['queue']['size'] ?? 0 }}
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Azioni Rapide --}}
    <div class="bg-white shadow-sm rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">⚡ Azioni Rapide</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button onclick="window.location.reload()"
                        class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg border border-blue-200 transition-colors text-left">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">🔄</span>
                        <div>
                            <div class="font-medium text-blue-900">Ricontrolla</div>
                            <div class="text-sm text-blue-600">Aggiorna health check</div>
                        </div>
                    </div>
                </button>

                <a href="{{ route('admin.monitoring.logs') }}"
                   class="bg-purple-50 hover:bg-purple-100 p-4 rounded-lg border border-purple-200 transition-colors">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">📋</span>
                        <div>
                            <div class="font-medium text-purple-900">Log Sistema</div>
                            <div class="text-sm text-purple-600">Visualizza log errori</div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('admin.monitoring.dashboard') }}"
                   class="bg-green-50 hover:bg-green-100 p-4 rounded-lg border border-green-200 transition-colors">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">📊</span>
                        <div>
                            <div class="font-medium text-green-900">Dashboard</div>
                            <div class="text-sm text-green-600">Torna al monitoraggio</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
// Auto-refresh ogni 60 secondi per health check
setInterval(function() {
    window.location.reload();
}, 60000);
</script>
@endpush
