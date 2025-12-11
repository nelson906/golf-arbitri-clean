@extends('layouts.admin')

@section('title', 'Validazione Assegnazioni')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Controllo Qualità Assegnazioni</h1>
                <p class="mt-2 text-sm text-gray-600">Verifica e risolvi problemi nelle assegnazioni degli arbitri</p>
            </div>
            <div>
                <a href="{{ route('admin.assignments.index') }}"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Torna alle Assegnazioni
                </a>
            </div>
        </div>

        <!-- Alert se ci sono problemi -->
        @if ($summary['total_issues'] > 0)
            <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Attenzione!</h3>
                        <p class="mt-1 text-sm text-yellow-700">
                            Sono stati rilevati <strong>{{ $summary['total_issues'] }}</strong> problemi nelle assegnazioni
                            ({{ $issuePercentage }}% del totale).
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Statistiche Generali -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-md bg-blue-500 bg-opacity-10 p-3">
                                <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Assegnazioni Totali</dt>
                                <dd class="text-2xl font-semibold text-gray-900">{{ $stats['total_assignments'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-md bg-green-500 bg-opacity-10 p-3">
                                <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Tornei Attivi</dt>
                                <dd class="text-2xl font-semibold text-gray-900">{{ $stats['active_tournaments'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-md bg-indigo-500 bg-opacity-10 p-3">
                                <svg class="h-8 w-8 text-indigo-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Arbitri Attivi</dt>
                                <dd class="text-2xl font-semibold text-gray-900">{{ $stats['active_referees'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div
                                class="rounded-md {{ $summary['total_issues'] > 0 ? 'bg-red-500' : 'bg-green-500' }} bg-opacity-10 p-3">
                                <svg class="h-8 w-8 {{ $summary['total_issues'] > 0 ? 'text-red-600' : 'text-green-600' }}"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    @if ($summary['total_issues'] > 0)
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    @endif
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Problemi Rilevati</dt>
                                <dd class="text-2xl font-semibold text-gray-900">{{ $summary['total_issues'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sezioni dei Problemi -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Conflitti di Date -->
            <div class="bg-white shadow rounded-lg overflow-hidden hover:shadow-lg transition-shadow duration-200">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="rounded-md bg-red-500 bg-opacity-10 p-2 mr-3">
                                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Conflitti di Date</h3>
                                <p class="text-sm text-gray-500">Arbitri assegnati a più tornei simultanei</p>
                            </div>
                        </div>
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                            {{ $summary['conflicts'] }}
                        </span>
                    </div>

                    @if ($summary['conflicts'] > 0)
                        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4 rounded">
                            <p class="text-sm text-red-700">
                                <strong>{{ $summary['conflicts'] }}</strong> conflitti rilevati
                            </p>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            Alcuni arbitri sono stati assegnati a tornei con date sovrapposte.
                            Questo può causare problemi logistici e organizzativi.
                        </p>
                        <a href="{{ route('admin.assignment-validation.conflicts') }}"
                            class="inline-flex items-center justify-center w-full px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Visualizza Conflitti
                        </a>
                    @else
                        <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded">
                            <p class="text-sm text-green-700">
                                <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                Nessun conflitto di date rilevato
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Requisiti Mancanti -->
            <div class="bg-white shadow rounded-lg overflow-hidden hover:shadow-lg transition-shadow duration-200">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="rounded-md bg-yellow-500 bg-opacity-10 p-2 mr-3">
                                <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Requisiti Mancanti</h3>
                                <p class="text-sm text-gray-500">Tornei con assegnazioni incomplete</p>
                            </div>
                        </div>
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                            {{ $summary['missing_requirements'] }}
                        </span>
                    </div>

                    @if ($summary['missing_requirements'] > 0)
                        <div class="mb-4 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                            <p class="text-sm text-yellow-700">
                                <strong>{{ $summary['missing_requirements'] }}</strong> tornei con problemi
                            </p>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            Alcuni tornei non rispettano i requisiti minimi: numero di arbitri,
                            livello richiesto o zone di competenza.
                        </p>
                        <a href="{{ route('admin.assignment-validation.missing-requirements') }}"
                            class="inline-flex items-center justify-center w-full px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Visualizza Problemi
                        </a>
                    @else
                        <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded">
                            <p class="text-sm text-green-700">
                                <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                Tutti i tornei rispettano i requisiti
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Arbitri Sovrassegnati -->
            <div class="bg-white shadow rounded-lg overflow-hidden hover:shadow-lg transition-shadow duration-200">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="rounded-md bg-blue-500 bg-opacity-10 p-2 mr-3">
                                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Arbitri Sovrassegnati</h3>
                                <p class="text-sm text-gray-500">Carico di lavoro eccessivo</p>
                            </div>
                        </div>
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            {{ $summary['overassigned'] }}
                        </span>
                    </div>

                    @if ($summary['overassigned'] > 0)
                        <div class="mb-4 bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                            <p class="text-sm text-blue-700">
                                <strong>{{ $summary['overassigned'] }}</strong> arbitri con troppe assegnazioni
                            </p>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            Alcuni arbitri hanno un numero di assegnazioni superiore alla media.
                            Considera di redistribuire il carico di lavoro.
                        </p>
                        <a href="{{ route('admin.assignment-validation.overassigned') }}"
                            class="inline-flex items-center justify-center w-full px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Visualizza Arbitri
                        </a>
                    @else
                        <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded">
                            <p class="text-sm text-green-700">
                                <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                Carico di lavoro distribuito equamente
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Arbitri Sottoutilizzati -->
            <div class="bg-white shadow rounded-lg overflow-hidden hover:shadow-lg transition-shadow duration-200">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="rounded-md bg-gray-500 bg-opacity-10 p-2 mr-3">
                                <svg class="h-6 w-6 text-gray-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Arbitri Sottoutilizzati</h3>
                                <p class="text-sm text-gray-500">Poche assegnazioni</p>
                            </div>
                        </div>
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                            {{ $summary['underassigned'] }}
                        </span>
                    </div>

                    @if ($summary['underassigned'] > 0)
                        <div class="mb-4 bg-gray-50 border-l-4 border-gray-400 p-4 rounded">
                            <p class="text-sm text-gray-700">
                                <strong>{{ $summary['underassigned'] }}</strong> arbitri con poche assegnazioni
                            </p>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            Alcuni arbitri attivi hanno ricevuto poche assegnazioni.
                            Verifica la loro disponibilità per distribuzione equa.
                        </p>
                        <a href="{{ route('admin.assignment-validation.underassigned') }}"
                            class="inline-flex items-center justify-center w-full px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Visualizza Arbitri
                        </a>
                    @else
                        <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded">
                            <p class="text-sm text-green-700">
                                <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                Tutti gli arbitri sono utilizzati adeguatamente
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
@endsection
