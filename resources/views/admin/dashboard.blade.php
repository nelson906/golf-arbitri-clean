{{-- File: resources/views/admin/dashboard.blade.php --}}
{{-- Dashboard Admin SENZA gestione anni/date --}}

@extends('layouts.admin')

@section('title', 'Dashboard Admin')

@section('content')
<div class="py-6">
    {{-- Header Dashboard --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">📊 Dashboard Amministrazione</h1>
        <p class="text-gray-600 mt-1">
            Benvenuto {{ auth()->user()->name ?? 'Admin' }}
        </p>
    </div>

    {{-- Alert Messaggi --}}
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    {{-- Statistiche Principali - SENZA riferimenti a date/anni --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

        {{-- Card Tornei Totali --}}
        <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-full">
                    <span class="text-2xl">🏆</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Tornei Totali</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_tournaments'] }}</p>
                </div>
            </div>
        </div>

        {{-- Card Arbitri --}}
        <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-full">
                    <span class="text-2xl">👥</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Arbitri Registrati</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_referees'] }}</p>
                </div>
            </div>
        </div>

        {{-- Card Assegnazioni --}}
        <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-yellow-100 rounded-full">
                    <span class="text-2xl">📋</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Assegnazioni Totali</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_assignments'] }}</p>
                </div>
            </div>
        </div>

        {{-- Card Circoli --}}
        <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-purple-100 rounded-full">
                    <span class="text-2xl">🏌️</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Circoli Golf</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_clubs'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Sezione Informazioni Sistema --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

        {{-- Riepilogo Dati --}}
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">📊 Riepilogo Dati</h2>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Tornei</span>
                        <span class="text-sm font-medium text-gray-900">{{ $stats['total_tournaments'] }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Arbitri</span>
                        <span class="text-sm font-medium text-gray-900">{{ $stats['total_referees'] }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Assegnazioni</span>
                        <span class="text-sm font-medium text-gray-900">{{ $stats['total_assignments'] }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Circoli</span>
                        <span class="text-sm font-medium text-gray-900">{{ $stats['total_clubs'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Info Sistema --}}
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">ℹ️ Informazioni Sistema</h2>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Versione Laravel</span>
                        <span class="text-sm font-medium text-gray-900">{{ app()->version() }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Versione PHP</span>
                        <span class="text-sm font-medium text-gray-900">{{ PHP_VERSION }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Database</span>
                        <span class="text-sm font-medium text-gray-900">{{ config('database.default') }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Ambiente</span>
                        <span class="text-sm font-medium text-gray-900">{{ app()->environment() }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-sm text-gray-600">Ultimo Accesso</span>
                        <span class="text-sm font-medium text-gray-900">{{ now()->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">⚡ Azioni Rapide</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ route('admin.tournaments.index') ?? '#' }}"
               class="flex flex-col items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                <span class="text-2xl mb-2">🏆</span>
                <span class="text-sm text-gray-700">Gestione Tornei</span>
            </a>

            <a href="{{ route('admin.assignments.index') ?? '#' }}"
               class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                <span class="text-2xl mb-2">📝</span>
                <span class="text-sm text-gray-700">Assegnazioni</span>
            </a>

            <a href="{{ route('admin.users.index') ?? '#' }}"
               class="flex flex-col items-center p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition-colors">
                <span class="text-2xl mb-2">👥</span>
                <span class="text-sm text-gray-700">Gestione Arbitri</span>
            </a>

            <a href="{{ route('admin.clubs.index') ?? '#' }}"
               class="flex flex-col items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                <span class="text-2xl mb-2">🏌️</span>
                <span class="text-sm text-gray-700">Gestione Circoli</span>
            </a>
        </div>
    </div>
</div>
@endsection
