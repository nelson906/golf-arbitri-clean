@extends('layouts.admin')

@section('title', 'Requisiti Mancanti')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <svg class="w-8 h-8 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Tornei con Requisiti Mancanti
            </h1>
            <p class="mt-2 text-sm text-gray-600">Verifica tornei che non rispettano i requisiti minimi</p>
        </div>
        <div>
            <a href="{{ route('admin.assignment-validation.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Torna alla Validazione
            </a>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <svg class="h-8 w-8 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Tornei con Problemi</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_tournaments'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg border-l-4 border-red-500">
            <div class="p-5">
                <div class="flex items-center">
                    <svg class="h-8 w-8 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Alta Severità</p>
                        <p class="text-2xl font-semibold text-red-600">{{ $stats['high_severity'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <p class="text-sm font-medium text-gray-500 mb-2">Tipi di Problemi</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($stats['issue_types'] as $type => $count)
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800">
                            {{ str_replace('_', ' ', ucfirst($type)) }}: {{ $count }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if($tournaments->count() > 0)
        <!-- Lista Tornei -->
        <div class="space-y-6">
            @foreach($tournaments as $index => $item)
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="p-6">
                    <!-- Header Torneo -->
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-grow">
                            <h3 class="text-xl font-semibold text-gray-900 flex items-center mb-2">
                                <svg class="w-6 h-6 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                </svg>
                                {{ $item['tournament']->name }}
                            </h3>
                            <div class="flex flex-wrap gap-3 text-sm text-gray-600">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    {{ \Carbon\Carbon::parse($item['tournament']->start_date)->format('d/m/Y') }}
                                </span>
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                    {{ $item['tournament']->club->name }}
                                </span>
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    </svg>
                                    {{ $item['tournament']->zone->name }}
                                </span>
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ $item['tournament']->tournamentType->name }}
                                </span>
                            </div>
                        </div>
                        <div class="text-right ml-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 mb-2">
                                {{ count($item['issues']) }} problemi
                            </span>
                            <div>
                                <a href="{{ route('admin.tournaments.show', $item['tournament']->id) }}"
                                   class="inline-flex items-center px-3 py-1 border border-gray-300 rounded-md text-sm text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Visualizza
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Lista Problemi -->
                    <div class="space-y-3">
                        @foreach($item['issues'] as $issue)
                        <div class="border-l-4 {{ $issue['severity'] === 'high' ? 'border-red-400 bg-red-50' : 'border-yellow-400 bg-yellow-50' }} p-4 rounded-r">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mr-3">
                                    @if($issue['type'] === 'min_referees')
                                        <svg class="h-6 w-6 {{ $issue['severity'] === 'high' ? 'text-red-600' : 'text-yellow-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                        </svg>
                                    @elseif($issue['type'] === 'referee_level')
                                        <svg class="h-6 w-6 {{ $issue['severity'] === 'high' ? 'text-red-600' : 'text-yellow-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                        </svg>
                                    @elseif($issue['type'] === 'wrong_zone')
                                        <svg class="h-6 w-6 {{ $issue['severity'] === 'high' ? 'text-red-600' : 'text-yellow-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        </svg>
                                    @elseif($issue['type'] === 'missing_role')
                                        <svg class="h-6 w-6 {{ $issue['severity'] === 'high' ? 'text-red-600' : 'text-yellow-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    @else
                                        <svg class="h-6 w-6 {{ $issue['severity'] === 'high' ? 'text-red-600' : 'text-yellow-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                    @endif
                                </div>
                                <div class="flex-grow">
                                    <h4 class="font-medium {{ $issue['severity'] === 'high' ? 'text-red-900' : 'text-yellow-900' }} mb-1">
                                        @if($issue['type'] === 'min_referees')
                                            Numero Minimo Arbitri Non Raggiunto
                                        @elseif($issue['type'] === 'referee_level')
                                            Livello Arbitri Inadeguato
                                        @elseif($issue['type'] === 'wrong_zone')
                                            Zona Incompatibile
                                        @elseif($issue['type'] === 'missing_role')
                                            Ruolo Mancante
                                        @else
                                            {{ ucfirst(str_replace('_', ' ', $issue['type'])) }}
                                        @endif
                                    </h4>
                                    <p class="text-sm {{ $issue['severity'] === 'high' ? 'text-red-800' : 'text-yellow-800' }}">{{ $issue['message'] }}</p>

                                    @if(isset($issue['referees']) && $issue['referees']->count() > 0)
                                        <div class="mt-2">
                                            <p class="text-xs {{ $issue['severity'] === 'high' ? 'text-red-700' : 'text-yellow-700' }} mb-1">Arbitri coinvolti:</p>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($issue['referees'] as $refName)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-800">
                                                        {{ $refName }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-shrink-0 ml-3">
                                    <a href="{{ route('admin.assignments.assign-referees', $item['tournament']->id) }}"
                                       class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Correggi
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <!-- Info Assegnazioni Attuali -->
                    <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Arbitri Assegnati</p>
                                <p class="text-lg font-semibold text-gray-900">
                                    {{ $item['tournament']->assignments->count() }} /
                                    {{ $item['tournament']->tournamentType->min_referees }}-{{ $item['tournament']->tournamentType->max_referees }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Livello Richiesto</p>
                                <p class="text-lg font-semibold text-gray-900">
                                    {{ ucfirst($item['tournament']->tournamentType->required_referee_level) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Ruoli Presenti</p>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($item['tournament']->assignments->pluck('role')->unique() as $role)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $role }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @else
        <!-- Nessun Problema -->
        <div class="bg-white shadow rounded-lg">
            <div class="p-12 text-center">
                <svg class="mx-auto h-16 w-16 text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-xl font-medium text-gray-900 mb-2">Nessun Requisito Mancante</h3>
                <p class="text-gray-600 mb-6">Tutti i tornei rispettano i requisiti minimi di assegnazione.</p>
                <a href="{{ route('admin.assignment-validation.index') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Torna al Dashboard
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
