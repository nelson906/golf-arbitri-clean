@extends('layouts.admin')

@section('title', 'Conflitti di Date')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <svg class="w-8 h-8 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Conflitti di Date nelle Assegnazioni
                </h1>
                <p class="mt-2 text-sm text-gray-600">Arbitri assegnati a tornei con date sovrapposte</p>
            </div>
            <div>
                <a href="{{ route('admin.assignment-validation.index') }}"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Torna alla Validazione
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        @if (session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
                <div class="flex">
                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd" />
                    </svg>
                    <p class="ml-3 text-sm text-green-700">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-md">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                            clip-rule="evenodd" />
                    </svg>
                    <p class="ml-3 text-sm text-red-700">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <!-- Statistiche Conflitti -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <svg class="h-8 w-8 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Totale Conflitti</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $conflictStats['total'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg border-l-4 border-red-500">
                <div class="p-5">
                    <div class="flex items-center">
                        <svg class="h-8 w-8 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Alta Gravità</p>
                            <p class="text-2xl font-semibold text-red-600">{{ $conflictStats['high_severity'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg border-l-4 border-yellow-500">
                <div class="p-5">
                    <div class="flex items-center">
                        <svg class="h-8 w-8 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Media Gravità</p>
                            <p class="text-2xl font-semibold text-yellow-600">{{ $conflictStats['medium_severity'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg border-l-4 border-blue-500">
                <div class="p-5">
                    <div class="flex items-center">
                        <svg class="h-8 w-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Bassa Gravità</p>
                            <p class="text-2xl font-semibold text-blue-600">{{ $conflictStats['low_severity'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($conflictsWithSuggestions->count() > 0)
            <!-- Azione Risoluzione Automatica -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="h-10 w-10 text-blue-600 mr-4" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                            </svg>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Risoluzione Automatica</h3>
                                <p class="text-sm text-gray-600">Il sistema può tentare di risolvere automaticamente alcuni
                                    conflitti sostituendo gli arbitri con alternative valide</p>
                            </div>
                        </div>
                        <form action="{{ route('admin.assignment-validation.fix-conflicts') }}" method="POST"
                            onsubmit="return confirm('Vuoi procedere con la risoluzione automatica? Alcune assegnazioni verranno modificate.')">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                Risolvi Automaticamente
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Lista Conflitti -->
            <div class="space-y-6">
                @foreach ($conflictsWithSuggestions as $index => $conflict)
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="p-6">
                            <!-- Header Conflitto -->
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-start space-x-3">
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                {{ $conflict['severity'] === 'high'
                                    ? 'bg-red-100 text-red-800'
                                    : ($conflict['severity'] === 'medium'
                                        ? 'bg-yellow-100 text-yellow-800'
                                        : 'bg-blue-100 text-blue-800') }}">
                                        {{ strtoupper($conflict['severity']) }}
                                    </span>
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900 flex items-center">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            {{ $conflict['referee']->name }}
                                        </h3>
                                        <div class="mt-1 flex items-center space-x-4 text-sm text-gray-500">
                                            <span
                                                class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100">
                                                {{ ucfirst($conflict['referee']->level) }}
                                            </span>
                                            <span>{{ $conflict['referee']->email }}</span>
                                            @if ($conflict['referee']->zone)
                                                <span class="flex items-center">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                    {{ $conflict['referee']->zone->name }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tornei in Conflitto -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                                    <h4 class="text-sm font-medium text-blue-900 mb-2 flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                        </svg>
                                        Torneo 1
                                    </h4>
                                    <p class="font-medium text-gray-900">{{ $conflict['assignment1']->tournament->name }}
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        {{ \Carbon\Carbon::parse($conflict['assignment1']->tournament->start_date)->format('d/m/Y') }}
                                        -
                                        {{ \Carbon\Carbon::parse($conflict['assignment1']->tournament->end_date)->format('d/m/Y') }}
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        {{ $conflict['assignment1']->tournament->club->name }}
                                    </p>
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800 mt-2">
                                        {{ $conflict['assignment1']->role }}
                                    </span>
                                </div>

                                <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded">
                                    <h4 class="text-sm font-medium text-red-900 mb-2 flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        Torneo 2 (Conflitto)
                                    </h4>
                                    <p class="font-medium text-gray-900">{{ $conflict['assignment2']->tournament->name }}
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        {{ \Carbon\Carbon::parse($conflict['assignment2']->tournament->start_date)->format('d/m/Y') }}
                                        -
                                        {{ \Carbon\Carbon::parse($conflict['assignment2']->tournament->end_date)->format('d/m/Y') }}
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        {{ $conflict['assignment2']->tournament->club->name }}
                                    </p>
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800 mt-2">
                                        {{ $conflict['assignment2']->role }}
                                    </span>
                                </div>
                            </div>

                            <!-- Suggerimenti -->
                            @if (!empty($conflict['suggestions']))
                                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                                    <h4 class="text-sm font-medium text-blue-900 mb-3 flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                        </svg>
                                        Suggerimenti per Risolvere
                                    </h4>
                                    @foreach ($conflict['suggestions'] as $suggestion)
                                        @if ($suggestion['action'] === 'replace_referee' && isset($suggestion['alternative_referees']))
                                            <p class="text-sm text-blue-800 mb-2">
                                                <strong>Sostituisci arbitro nel Torneo 2 con:</strong>
                                            </p>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($suggestion['alternative_referees'] as $altReferee)
                                                    <a href="{{ route('admin.assignments.edit', $conflict['assignment2']->id) }}?suggested_referee={{ $altReferee->id }}"
                                                        class="inline-flex items-center px-3 py-1 border border-blue-300 rounded-md text-sm font-medium text-blue-700 bg-white hover:bg-blue-50">
                                                        {{ $altReferee->name }}
                                                        <span
                                                            class="ml-2 px-2 py-0.5 rounded text-xs bg-blue-100">{{ ucfirst($altReferee->level) }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        @elseif($suggestion['action'] === 'verify_timing')
                                            <p class="text-sm text-blue-700">
                                                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                {{ $suggestion['message'] }}
                                            </p>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <!-- Nessun Conflitto -->
            <div class="bg-white shadow rounded-lg">
                <div class="p-12 text-center">
                    <svg class="mx-auto h-16 w-16 text-green-500 mb-4" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="text-xl font-medium text-gray-900 mb-2">Nessun Conflitto Rilevato</h3>
                    <p class="text-gray-600 mb-6">Tutte le assegnazioni sono compatibili dal punto di vista delle date.</p>
                    <a href="{{ route('admin.assignment-validation.index') }}"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Torna al Dashboard
                    </a>
                </div>
            </div>
        @endif
    </div>
@endsection
