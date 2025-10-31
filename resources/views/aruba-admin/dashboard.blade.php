@extends('aruba-admin.layout')

@section('title', 'Dashboard - Aruba Admin')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-3xl font-bold text-gray-800">
            <i class="fas fa-tachometer-alt text-blue-600"></i>
            Dashboard Sistema
        </h1>
        <p class="text-gray-600 mt-2">Panoramica dello stato del sistema Laravel su Aruba Virtual Host</p>
    </div>

    <!-- System Info Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm">Laravel</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $system_info['laravel_version'] }}</p>
                </div>
                <i class="fas fa-code text-3xl text-red-500"></i>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm">PHP</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $system_info['php_version'] }}</p>
                </div>
                <i class="fab fa-php text-3xl text-purple-500"></i>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm">Ambiente</p>
                    <p class="text-2xl font-bold text-gray-800">{{ ucfirst($system_info['environment']) }}</p>
                </div>
                <i class="fas fa-server text-3xl text-green-500"></i>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm">Debug</p>
                    <p class="text-2xl font-bold {{ $system_info['debug_mode'] === 'ON' ? 'text-red-500' : 'text-green-500' }}">
                        {{ $system_info['debug_mode'] }}
                    </p>
                </div>
                <i class="fas fa-bug text-3xl text-yellow-500"></i>
            </div>
        </div>
    </div>

    <!-- Database Status -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">
            <i class="fas fa-database text-blue-600"></i> Stato Database
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="text-gray-600 text-sm">Connessione</p>
                <p class="text-lg font-semibold {{ $database_stats['connected'] ? 'text-green-600' : 'text-red-600' }}">
                    {{ $database_stats['connected'] ? '✅ Connesso' : '❌ Non connesso' }}
                </p>
            </div>
            @if($database_stats['connected'])
                <div>
                    <p class="text-gray-600 text-sm">Database</p>
                    <p class="text-lg font-semibold text-gray-800">{{ $database_stats['database'] }}</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Tabelle</p>
                    <p class="text-lg font-semibold text-gray-800">{{ $database_stats['tables_count'] }}</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Permissions Status -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">
            <i class="fas fa-lock text-blue-600"></i> Stato Permessi
        </h2>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="px-4 py-2 text-left">Cartella</th>
                        <th class="px-4 py-2 text-center">Esiste</th>
                        <th class="px-4 py-2 text-center">Scrivibile</th>
                        <th class="px-4 py-2 text-center">Permessi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($permissions as $name => $info)
                        <tr class="border-b">
                            <td class="px-4 py-2 text-sm">{{ $name }}</td>
                            <td class="px-4 py-2 text-center">
                                {!! $info['exists'] ? '<span class="text-green-600">✅</span>' : '<span class="text-red-600">❌</span>' !!}
                            </td>
                            <td class="px-4 py-2 text-center">
                                {!! $info['writable'] ? '<span class="text-green-600">✅</span>' : '<span class="text-red-600">❌</span>' !!}
                            </td>
                            <td class="px-4 py-2 text-center text-sm font-mono">{{ $info['permissions'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            <a href="{{ route('aruba.admin.permissions') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                <i class="fas fa-wrench"></i> Gestisci Permessi
            </a>
        </div>
    </div>

    <!-- System Configuration -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">
            <i class="fas fa-cog text-blue-600"></i> Configurazione PHP
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <p class="text-gray-600 text-sm">Memory Limit</p>
                <p class="text-lg font-semibold text-gray-800">{{ $system_info['memory_limit'] }}</p>
            </div>
            <div>
                <p class="text-gray-600 text-sm">Max Execution Time</p>
                <p class="text-lg font-semibold text-gray-800">{{ $system_info['max_execution_time'] }}s</p>
            </div>
            <div>
                <p class="text-gray-600 text-sm">Upload Max Filesize</p>
                <p class="text-lg font-semibold text-gray-800">{{ $system_info['upload_max_filesize'] }}</p>
            </div>
            <div>
                <p class="text-gray-600 text-sm">Timezone</p>
                <p class="text-lg font-semibold text-gray-800">{{ $system_info['timezone'] }}</p>
            </div>
            <div>
                <p class="text-gray-600 text-sm">Spazio Libero</p>
                <p class="text-lg font-semibold text-gray-800">{{ $system_info['disk_free_space'] }}</p>
            </div>
            <div>
                <p class="text-gray-600 text-sm">Spazio Totale</p>
                <p class="text-lg font-semibold text-gray-800">{{ $system_info['disk_total_space'] }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
