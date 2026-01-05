@extends('layouts.admin')

@section('title', ' ')

@section('content')

    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📝 Prepara Notifica Assegnazione - {{ $tournament->name }}
            </h2>
            <a href="{{ route('admin.tournaments.index') }}"
                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                ← Torna ai Tornei
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Flash Messages --}}
            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-400 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Info flow banner - differenziato per tipo gara --}}
            @if($tournament->tournamentType?->is_national ?? false)
                {{-- Banner per gare nazionali --}}
                <div class="mb-4 p-4 bg-purple-50 border-l-4 border-purple-400 rounded">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-purple-400 mt-0.5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM9 7a1 1 0 112 0v4a1 1 0 01-2 0V7zm1 8a1 1 0 100-2 1 1 0 000 2z"
                                clip-rule="evenodd" />
                        </svg>
                        <div class="text-sm text-purple-800">
                            <p class="font-medium">Notifica Gara Nazionale</p>
                            <ul class="list-disc ml-5 mt-1 space-y-1">
                                @if(auth()->user()->isNationalAdmin())
                                    <li>Comunica i nominativi degli <strong>arbitri designati</strong> al Comitato Campionati.</li>
                                    <li>La zona di competenza e gli arbitri riceveranno copia della notifica.</li>
                                @else
                                    <li>Comunica i nominativi degli <strong>osservatori</strong> al Comitato Campionati.</li>
                                    <li>Il CRC e gli osservatori riceveranno copia della notifica.</li>
                                @endif
                                <li><strong>Nessun allegato:</strong> Le notifiche nazionali non includono documenti DOCX.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            @else
                {{-- Banner per gare zonali --}}
                <div class="mb-4 p-4 bg-blue-50 border-l-4 border-blue-400 rounded">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-400 mt-0.5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM9 7a1 1 0 112 0v4a1 1 0 01-2 0V7zm1 8a1 1 0 100-2 1 1 0 000 2z"
                                clip-rule="evenodd" />
                        </svg>
                        <div class="text-sm text-blue-800">
                            <p class="font-medium">Prepara e invia la notifica di assegnazione.</p>
                            <ul class="list-disc ml-5 mt-1 space-y-1">
                                <li><strong>Gestisci Documenti:</strong> Scarica, modifica e ricarica i DOCX allegati.</li>
                                <li><strong>Anteprima:</strong> Visualizza l'email prima dell'invio.</li>
                                <li><strong>Salva Bozza:</strong> Salva senza inviare (potrai inviare dopo).</li>
                                <li><strong>Invia Ora:</strong> Salva e invia immediatamente a tutti i destinatari.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                {{-- Sidebar: Info Torneo --}}
                <div class="lg:col-span-1">

                    {{-- Tournament Details --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">🏌️ Dettagli Torneo</h3>
                            <dl class="space-y-3 text-sm">
                                <div>
                                    <dt class="text-gray-600">Nome:</dt>
                                    <dd class="font-medium text-gray-900">{{ $tournament->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-600">Date:</dt>
                                    <dd class="font-medium text-gray-900">
                                        {{ $tournament->start_date->format('d/m/Y') }}
                                        @if (!$tournament->start_date->isSameDay($tournament->end_date))
                                            - {{ $tournament->end_date->format('d/m/Y') }}
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-gray-600">Circolo:</dt>
                                    <dd class="font-medium text-gray-900">{{ $tournament->club->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-600">Zona:</dt>
                                    <dd class="font-medium text-gray-900">{{ $tournament->club->zone->name }}</dd>
                                </div>
                                @if ($tournament->tournamentType)
                                    <div>
                                        <dt class="text-gray-600">Categoria:</dt>
                                        <dd class="font-medium text-gray-900">{{ $tournament->tournamentType->name }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    {{-- Document Status - Solo per gare zonali --}}
                    @if(!($tournament->tournamentType?->is_national ?? false))
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <h4 class="text-md font-medium text-gray-900 mb-4">📎 Documenti Disponibili</h4>

                            {{-- Mostra sempre lo stato dei documenti --}}
                            <div class="space-y-3 mb-4">
                                {{-- Convocazione --}}
                                <div
                                    class="flex items-center p-3 {{ $documentStatus['hasConvocation'] ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200' }} rounded-lg">
                                    <div class="flex-shrink-0">
                                        <svg class="w-5 h-5 {{ $documentStatus['hasConvocation'] ? 'text-green-400' : 'text-gray-400' }}"
                                            viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <p
                                            class="text-sm font-medium {{ $documentStatus['hasConvocation'] ? 'text-green-800' : 'text-gray-600' }}">
                                            Convocazione SZR
                                        </p>
                                        <p
                                            class="text-xs {{ $documentStatus['hasConvocation'] ? 'text-green-600' : 'text-gray-500' }}">
                                            {{ $documentStatus['hasConvocation'] ? 'Disponibile per arbitri' : 'Non ancora generata' }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Lettera Circolo --}}
                                <div
                                    class="flex items-center p-3 {{ $documentStatus['hasClubLetter'] ? 'bg-blue-50 border border-blue-200' : 'bg-gray-50 border border-gray-200' }} rounded-lg">
                                    <div class="flex-shrink-0">
                                        <svg class="w-5 h-5 {{ $documentStatus['hasClubLetter'] ? 'text-blue-400' : 'text-gray-400' }}"
                                            viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <p
                                            class="text-sm font-medium {{ $documentStatus['hasClubLetter'] ? 'text-blue-800' : 'text-gray-600' }}">
                                            Lettera Circolo
                                        </p>
                                        <p
                                            class="text-xs {{ $documentStatus['hasClubLetter'] ? 'text-blue-600' : 'text-gray-500' }}">
                                            {{ $documentStatus['hasClubLetter'] ? 'Disponibile per circolo' : 'Non ancora generata' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- Bottone sempre presente per gestire documenti --}}
                            <button type="button" onclick="openDocumentManagerModal({{ $tournament->id }})"
                                class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Gestisci Documenti
                            </button>
                        </div>
                    </div>
                    @else
                    {{-- Info per gare nazionali --}}
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-purple-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            <div class="text-sm text-purple-800">
                                <p class="font-medium">Gara Nazionale</p>
                                <p class="mt-1">Le notifiche per gare nazionali non includono allegati (convocazione/lettera circolo).</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Assignments Summary --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h4 class="text-md font-medium text-gray-900 mb-4">
                                👥 Arbitri Assegnati ({{ $assignedReferees->count() }})
                            </h4>
                            @if ($assignedReferees->count() > 0)
                                <div class="space-y-3">
                                    @foreach (['Direttore di Torneo', 'Arbitro', 'Osservatore'] as $role)
                                        @php
                                            $roleAssignments = $assignedReferees->where('pivot.role', $role);
                                        @endphp
                                        @if ($roleAssignments->count() > 0)
                                            <div>
                                                <h5 class="text-sm font-medium text-gray-800 mb-2">
                                                    {{ $role }} ({{ $roleAssignments->count() }})
                                                </h5>
                                                <div class="space-y-1">
                                                    @foreach ($roleAssignments as $referee)
                                                        <div class="text-xs text-gray-600 ml-2">
                                                            • {{ $referee->name }}
                                                            <div class="text-xs text-gray-500">{{ $referee->email }}</div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <div class="text-gray-400 text-sm">⚠️ Nessun arbitro assegnato al torneo</div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Assegna prima gli arbitri per poter inviare le notifiche
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Main Content: Form --}}
                <div class="lg:col-span-2">
                    @php
                        $isNationalTournament = $tournament->tournamentType?->is_national ?? false;
                        $isNationalAdmin = auth()->user()->isNationalAdmin();
                    @endphp

                    {{-- Switch tra form nazionale e zonale --}}
                    @if($isNationalTournament)
                        {{-- GARA NAZIONALE: form semplificato senza allegati --}}
                        @if($isNationalAdmin)
                            {{-- CRC_ADMIN: notifica arbitri designati --}}
                            @include('admin.notifications._national_crc_form')
                        @else
                            {{-- ADMIN ZONA: notifica osservatori --}}
                            @include('admin.notifications._national_zone_form')
                        @endif
                    @else
                        {{-- GARA ZONALE: form completo con allegati (esistente) --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">

                            <form method="POST"
                                action="{{ route('admin.tournaments.send-assignment-with-convocation', $tournament) }}"
                                class="space-y-6">
                                @csrf

                                {{-- Subject --}}
                                <div>
                                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                                        📧 Oggetto Email <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="subject" id="subject"
                                        value="{{ old('subject', 'Assegnazione Arbitri - ' . $tournament->name) }}"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                                </div>

                                {{-- Message --}}
                                <div>
                                    <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                                        📝 Messaggio <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="message" id="message" rows="8" required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">{{ old(
                                            'message',
                                            'Si comunica l\'assegnazione degli arbitri per il torneo ' .
                                                $tournament->name .
                                                ' che si terrà ' .
                                                $tournament->start_date->format('d/m/Y') .
                                                ($tournament->start_date->format('d/m/Y') != $tournament->end_date->format('d/m/Y')
                                                    ? ' - ' . $tournament->end_date->format('d/m/Y')
                                                    : '') .
                                                ' presso ' .
                                                $tournament->club->name .
                                                '.

                                                                                                                                                                                                                                                                                        Si prega di prendere nota degli arbitri assegnati e di procedere con le comunicazioni necessarie.

                                                                                                                                                                                                                                                                                        Cordiali saluti',
                                        ) }}</textarea>
                                </div>

                                {{-- ACCORDION: Clausole Aggiuntive --}}
                                <div class="bg-white rounded-lg shadow-md mb-6 overflow-hidden">
                                    <button type="button"
                                        class="w-full px-6 py-4 text-left flex justify-between items-center bg-blue-50 hover:bg-blue-100"
                                        onclick="toggleSection('clausole')">
                                        <div>
                                            <h5 class="text-lg font-semibold text-gray-800">📝 Clausole Aggiuntive</h5>
                                            <small class="text-gray-600">Seleziona le clausole da includere</small>
                                        </div>
                                        <svg id="clausole-icon" class="w-6 h-6 transition-transform duration-200"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>

                                    <div id="clausole-content" class="p-6" style="display: none;">
                                        @php
                                            $clubClauses = collect();
                                            if (isset($availableClauses['club'])) {
                                                $clubClauses = $clubClauses->merge(collect($availableClauses['club']));
                                            }
                                            if (isset($availableClauses['all'])) {
                                                $clubClauses = $clubClauses->merge(collect($availableClauses['all']));
                                            }
                                            $clubClausesByCategory = $clubClauses->groupBy('category');
                                        @endphp

                                        @if ($clubClauses->isNotEmpty())
                                            <div class="mb-6 pb-6 border-b border-gray-200">
                                                <h6 class="text-base font-semibold text-blue-700 mb-4">📄 Clausole Lettera
                                                    Circolo</h6>
                                                <p class="text-xs text-gray-500 mb-4">Queste clausole verranno inserite
                                                    nella lettera inviata al circolo</p>

                                                @foreach (['spese', 'logistica', 'responsabilita'] as $category)
                                                    @php $categoryClauses = $clubClausesByCategory->get($category, collect()); @endphp
                                                    @if ($categoryClauses->isNotEmpty())
                                                        <div class="mb-5">
                                                            <label class="block text-sm font-semibold text-gray-700 mb-3">
                                                                {{ \App\Models\NotificationClause::CATEGORIES[$category] ?? ucfirst($category) }}
                                                            </label>
                                                            @foreach ($categoryClauses as $clause)
                                                                <div
                                                                    class="flex items-start mb-3 p-3 bg-blue-50 rounded-lg border border-blue-100">
                                                                    <input type="radio"
                                                                        name="clauses[CLAUSOLA_CLUB_{{ strtoupper($category) }}]"
                                                                        value="{{ $clause['id'] }}"
                                                                        id="clause_club_{{ $clause['id'] }}"
                                                                        class="mt-1 w-4 h-4 text-blue-600">
                                                                    <label for="clause_club_{{ $clause['id'] }}"
                                                                        class="ml-3 flex-1 cursor-pointer">
                                                                        <span
                                                                            class="block font-medium text-gray-900">{{ $clause['title'] }}</span>
                                                                        <span
                                                                            class="block text-sm text-gray-600 mt-1">{{ Str::limit($clause['content'], 150) }}</span>
                                                                    </label>
                                                                </div>
                                                            @endforeach
                                                            <div class="flex items-start p-3">
                                                                <input type="radio"
                                                                    name="clauses[CLAUSOLA_CLUB_{{ strtoupper($category) }}]"
                                                                    value=""
                                                                    id="clause_club_none_{{ $category }}" checked
                                                                    class="mt-1 w-4 h-4">
                                                                <label for="clause_club_none_{{ $category }}"
                                                                    class="ml-3 text-sm text-gray-500 italic cursor-pointer">
                                                                    Nessuna clausola
                                                                </label>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif

                                        @php
                                            $refereeClauses = collect();
                                            if (isset($availableClauses['referee'])) {
                                                $refereeClauses = $refereeClauses->merge(
                                                    collect($availableClauses['referee']),
                                                );
                                            }
                                            if (isset($availableClauses['all'])) {
                                                $refereeClauses = $refereeClauses->merge(
                                                    collect($availableClauses['all']),
                                                );
                                            }
                                            $refereeClausesByCategory = $refereeClauses->groupBy('category');
                                        @endphp

                                        @if ($refereeClauses->isNotEmpty())
                                            <div>
                                                <h6 class="text-base font-semibold text-green-700 mb-4">📋 Clausole
                                                    Convocazione Arbitri</h6>
                                                <p class="text-xs text-gray-500 mb-4">Queste clausole verranno inserite
                                                    nella convocazione inviata agli arbitri</p>

                                                @foreach (['responsabilita', 'comunicazioni', 'altro'] as $category)
                                                    @php $categoryClauses = $refereeClausesByCategory->get($category, collect()); @endphp
                                                    @if ($categoryClauses->isNotEmpty())
                                                        <div class="mb-5">
                                                            <label class="block text-sm font-semibold text-gray-700 mb-3">
                                                                {{ \App\Models\NotificationClause::CATEGORIES[$category] ?? ucfirst($category) }}
                                                            </label>
                                                            @foreach ($categoryClauses as $clause)
                                                                <div <div
                                                                    class="flex items-start mb-3 p-3 bg-green-50 rounded-lg border border-green-100">
                                                                    <input type="radio"
                                                                        name="clauses[CLAUSOLA_ARBITRO_{{ strtoupper($category) }}]"
                                                                        value="{{ $clause['id'] }}"
                                                                        id="clause_ref_{{ $clause['id'] }}"
                                                                        class="mt-1 w-4 h-4 text-green-600">
                                                                    <label for="clause_ref_{{ $clause['id'] }}"
                                                                        class="ml-3 flex-1 cursor-pointer">
                                                                        <span
                                                                            class="block font-medium text-gray-900">{{ $clause['title'] }}</span>
                                                                        <span
                                                                            class="block text-sm text-gray-600 mt-1">{{ Str::limit($clause['content'], 150) }}</span>
                                                                    </label>
                                                                </div>
                                                            @endforeach
                                                            <div class="flex items-start p-3">
                                                                <input type="radio"
                                                                    name="clauses[CLAUSOLA_ARBITRO_{{ strtoupper($category) }}]"
                                                                    value=""
                                                                    id="clause_ref_none_{{ $category }}" checked
                                                                    class="mt-1 w-4 h-4">
                                                                <label for="clause_ref_none_{{ $category }}"
                                                                    class="ml-3 text-sm text-gray-500 italic cursor-pointer">
                                                                    Nessuna clausola
                                                                </label>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif

                                        @if ($refereeClauses->isNotEmpty() || $clubClauses->isNotEmpty())
                                            {{-- Bottone Rigenera Documenti --}}
                                            <div class="mt-6 pt-6 border-t border-gray-200">
                                                <button type="button" onclick="regenerateDocuments()"
                                                    class="inline-flex items-center px-4 py-2 bg-indigo-100 text-indigo-700 hover:bg-indigo-200 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                                    id="regenerateButton">
                                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                    </svg>
                                                    <span>Rigenera documenti con clausole selezionate</span>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- ACCORDION: Destinatari Arbitri --}}
                                <div class="bg-white rounded-lg shadow-md mb-6 overflow-hidden">
                                    <button type="button"
                                        class="w-full px-6 py-4 text-left flex justify-between items-center bg-green-50 hover:bg-green-100"
                                        onclick="toggleSection('arbitri')">
                                        <div>
                                            <h5 class="text-lg font-semibold text-gray-800">👥 Destinatari Arbitri</h5>
                                            <small class="text-gray-600">{{ $assignedReferees->count() }} arbitri</small>
                                        </div>
                                        <svg id="arbitri-icon" class="w-6 h-6 transition-transform duration-200"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>

                                    <div id="arbitri-content" class="p-6 hidden">
                                        @if ($assignedReferees->count() > 0)
                                            <div class="space-y-3 max-h-64 overflow-y-auto">
                                                @foreach ($assignedReferees as $referee)
                                                    <div class="flex items-center">
                                                        <input type="checkbox" name="recipients[]"
                                                            value="{{ $referee->id }}" id="referee_{{ $referee->id }}"
                                                            checked
                                                            class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                                        <label for="referee_{{ $referee->id }}"
                                                            class="ml-3 text-sm flex-1">
                                                            <span class="font-medium">{{ $referee->name }}</span>
                                                            <span class="text-gray-600">({{ $referee->role }})</span>
                                                            <div class="text-xs text-gray-500">{{ $referee->email }}</div>
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-sm text-yellow-800">⚠️ Nessun arbitro assegnato</p>
                                        @endif
                                    </div>
                                </div>

                                {{-- ACCORDION: Indirizzi Preimpostati --}}
                                @if (isset($groupedEmails) && $groupedEmails->count() > 0)
                                    <div class="bg-white rounded-lg shadow-md mb-6 overflow-hidden">
                                        <button type="button"
                                            class="w-full px-6 py-4 text-left flex justify-between items-center bg-purple-50 hover:bg-purple-100"
                                            onclick="toggleSection('preimpostati')">
                                            <div>
                                                <h5 class="text-lg font-semibold text-gray-800">📋 Indirizzi Preimpostati
                                                </h5>
                                                <small class="text-gray-600">Indirizzi standard per le notifiche</small>
                                            </div>
                                            <svg id="preimpostati-icon" class="w-6 h-6 transition-transform duration-200"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>

                                        <div id="preimpostati-content" class="p-6" style="display: none;">
                                            <div class="bg-gray-50 p-4 rounded-lg">
                                                @foreach ($groupedEmails as $category => $emails)
                                                    <div class="mb-4 last:mb-0">
                                                        <h4 class="font-medium text-gray-900 mb-2">{{ $category }}
                                                        </h4>
                                                        <div class="space-y-2">
                                                            @foreach ($emails as $email)
                                                                <div class="flex items-center">
                                                                    <input type="checkbox" id="fixed_{{ $email->id }}"
                                                                        name="fixed_addresses[]"
                                                                        value="{{ $email->id }}"
                                                                        {{ $email->is_default ? 'checked' : '' }}
                                                                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                                    <label for="fixed_{{ $email->id }}"
                                                                        class="ml-2 text-sm text-gray-700">
                                                                        <span
                                                                            class="font-medium">{{ $email->name }}</span>
                                                                        <span
                                                                            class="text-gray-500">({{ $email->email }})</span>
                                                                    </label>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                {{-- ACCORDION: Email Istituzionali --}}
                                @if ($groupedEmails && $groupedEmails->count() > 0)
                                    <div class="bg-white rounded-lg shadow-md mb-6 overflow-hidden">
                                        <button type="button"
                                            class="w-full px-6 py-4 text-left flex justify-between items-center bg-blue-50 hover:bg-blue-100"
                                            onclick="toggleSection('istituzionali')">
                                            <div>
                                                <h5 class="text-lg font-semibold text-gray-800">📮 Email Istituzionali</h5>
                                                <small class="text-gray-600">Email degli organi federali</small>
                                            </div>
                                            <svg id="istituzionali-icon" class="w-6 h-6 transition-transform duration-200"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>

                                        <div id="istituzionali-content" class="p-6" style="display: none;">
                                            <div class="space-y-4">
                                                @foreach ($groupedEmails as $category => $emails)
                                                    <div class="border border-gray-200 rounded-lg">
                                                        <div class="px-4 py-2 bg-gray-50 border-b border-gray-200">
                                                            <h4 class="text-sm font-medium text-gray-900 capitalize">
                                                                {{ ucfirst($category) }}
                                                            </h4>
                                                        </div>
                                                        <div class="p-4 space-y-2">
                                                            @foreach ($emails as $email)
                                                                @if (is_object($email) && isset($email->id))
                                                                    <div class="flex items-center">
                                                                        <input type="checkbox" name="fixed_addresses[]"
                                                                            value="{{ $email->id }}"
                                                                            id="institutional_{{ $email->id }}"
                                                                            {{ $category === 'convocazioni' ? 'checked' : '' }}
                                                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">

                                                                        <label for="institutional_{{ $email->id }}"
                                                                            class="ml-2 text-sm text-gray-700">
                                                                            <span
                                                                                class="font-medium">{{ $email->name }}</span>
                                                                            <span
                                                                                class="text-gray-500">({{ $email->email }})</span>
                                                                            @if ($email->receive_all_notifications)
                                                                                <span class="text-xs text-blue-600">• Tutte
                                                                                    le notifiche</span>
                                                                            @endif
                                                                        </label>
                                                                    </div>
                                                                @else
                                                                    <!-- Email non valida: {{ gettype($email) }} - {{ json_encode($email) }} -->
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        🏌️ Circolo Organizzatore
                                    </label>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                        <div class="flex items-center">
                                            <input type="checkbox" name="send_to_club" id="send_to_club" value="1"
                                                checked
                                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                            <label for="send_to_club" class="ml-2 text-sm text-gray-700">
                                                <span class="font-medium">Invia notifica al circolo</span>
                                            </label>
                                        </div>
                                        <div class="mt-2 ml-6 text-sm text-gray-600">
                                            <p>Circolo: <strong>{{ $tournament->club->name }}</strong></p>
                                            @if ($tournament->club->email)
                                                <p class="text-xs text-gray-500 mt-1">
                                                    Email: {{ $tournament->club->email }}
                                                </p>
                                            @else
                                                <p class="text-xs text-red-500 mt-1">
                                                    ⚠️ Email del circolo non configurata
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Sezione Mittente --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        📮 Sezione Mittente
                                    </label>
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                        <div class="flex items-center">
                                            <input type="checkbox" name="send_to_section" id="send_to_section"
                                                value="1" {{ old('send_to_section', true) ? 'checked' : '' }}
                                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                            <label for="send_to_section" class="ml-2 text-sm text-gray-700">
                                                <span class="font-medium">Invia copia alla sezione</span>
                                            </label>
                                        </div>
                                        <div class="mt-2 ml-6 text-sm text-gray-600">
                                            <p>La notifica verrà inviata anche alla sezione di zona:
                                                <strong>{{ $tournament->club->zone->name ?? 'N/A' }}</strong>
                                            </p>
                                            @if ($tournament->club->zone->email ?? null)
                                                <p class="text-xs text-gray-500 mt-1">
                                                    Email: {{ $tournament->club->zone->email }}
                                                </p>
                                            @else
                                                <p class="text-xs text-amber-600 mt-1">
                                                    ℹ️ Email della sezione non configurata - contattare l'amministratore
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Email Aggiuntive -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        ➕ Email Aggiuntive
                                    </label>
                                    <div id="additional-emails-container" class="space-y-3">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <input type="email" name="additional_emails[]"
                                                placeholder="email@esempio.com"
                                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                                            <div class="flex">
                                                <input type="text" name="additional_names[]"
                                                    placeholder="Nome (opzionale)"
                                                    class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                                                <button type="button" id="add-email-btn"
                                                    class="ml-2 px-3 py-2 bg-indigo-100 text-indigo-700 rounded-md hover:bg-indigo-200">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Hidden action field --}}
                                <input type="hidden" name="action" id="form-action" value="save">

                                {{-- Submit Buttons --}}
                                <div
                                    class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-6 border-t border-gray-200">
                                    {{-- Left: Cancel --}}
                                    <a href="{{ route('admin.tournaments.show', $tournament) }}"
                                        class="w-full sm:w-auto px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 text-center">
                                        Annulla
                                    </a>

                                    {{-- Right: Actions --}}
                                    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                                        {{-- Preview Button --}}
                                        <button type="button" onclick="showPreview()"
                                            class="w-full sm:w-auto px-5 py-2 bg-gray-100 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 flex items-center justify-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                </path>
                                            </svg>
                                            Anteprima
                                        </button>

                                        {{-- Save Draft Button --}}
                                        <button type="submit"
                                            onclick="document.getElementById('form-action').value='save'"
                                            class="w-full sm:w-auto px-5 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 flex items-center justify-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4">
                                                </path>
                                            </svg>
                                            Salva Bozza
                                        </button>

                                        {{-- Send Now Button --}}
                                        <button type="submit" onclick="return confirmSend()"
                                            class="w-full sm:w-auto px-5 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 flex items-center justify-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                            </svg>
                                            Invia Ora
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endif
                    {{-- Fine switch gara nazionale/zonale --}}
                </div>
            </div>
        </div>
    </div>

    @include('admin.tournament-notifications._document_manager_modal')
    {{-- Preview Modal --}}
    <div id="preview-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-3xl shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center border-b pb-3">
                <h3 class="text-lg font-medium text-gray-900">Anteprima Email</h3>
                <button onclick="closePreview()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <div id="preview-content" class="mt-4 max-h-96 overflow-y-auto">
                <!-- Content will be loaded here -->
            </div>
            <div class="mt-4 pt-4 border-t flex justify-end">
                <button onclick="closePreview()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                    Chiudi
                </button>
            </div>
        </div>
    </div>


    <script>
        // Toast semplice per feedback
        function showToast(message, isError = false) {
            const toast = document.createElement('div');
            toast.className =
                `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${isError ? 'bg-red-500' : 'bg-green-500'} text-white`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
        // Conferma invio
        function confirmSend() {
            const recipientCount = document.querySelectorAll('input[name="recipients[]"]:checked').length;
            const sendToClub = document.getElementById('send_to_club')?.checked;
            const totalRecipients = recipientCount + (sendToClub ? 1 : 0);

            if (totalRecipients === 0) {
                alert('Seleziona almeno un destinatario.');
                return false;
            }

            const confirmed = confirm(
                `Stai per inviare la notifica a ${totalRecipients} destinatari.\n\nVuoi procedere con l'invio?`);
            if (confirmed) {
                document.getElementById('form-action').value = 'send';
                return true;
            }
            return false;
        }

        // Mostra anteprima
        function showPreview() {
            const subject = document.getElementById('subject').value || 'Nessun oggetto';
            const message = document.getElementById('message').value || 'Nessun messaggio';

            // Raccogli destinatari selezionati
            const referees = [];
            document.querySelectorAll('input[name="recipients[]"]:checked').forEach(cb => {
                const label = cb.closest('.flex')?.querySelector('label');
                if (label) {
                    referees.push(label.textContent.trim());
                }
            });

            const sendToClub = document.getElementById('send_to_club')?.checked;
            const clubName = '{{ $tournament->club->name ?? 'N/A' }}';
            const clubEmail = '{{ $tournament->club->email ?? 'N/A' }}';

            // Costruisci HTML preview
            let html = `
        <div class="space-y-4">
            <div class="bg-gray-50 p-4 rounded-lg">
                <h4 class="font-medium text-gray-700 mb-2">Oggetto:</h4>
                <p class="text-gray-900">${escapeHtml(subject)}</p>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg">
                <h4 class="font-medium text-gray-700 mb-2">Messaggio:</h4>
                <p class="text-gray-900 whitespace-pre-wrap">${escapeHtml(message)}</p>
            </div>

            <div class="bg-blue-50 p-4 rounded-lg">
                <h4 class="font-medium text-blue-700 mb-2">Destinatari (${referees.length + (sendToClub ? 1 : 0)}):</h4>
                <ul class="text-sm text-gray-700 space-y-1">
    `;

            if (sendToClub) {
                html +=
                    `<li class="flex items-center"><span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>Circolo: ${escapeHtml(clubName)} (${escapeHtml(clubEmail)})</li>`;
            }

            referees.forEach(ref => {
                html +=
                    `<li class="flex items-center"><span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>${escapeHtml(ref)}</li>`;
            });

            html += `
                </ul>
            </div>

            <div class="bg-yellow-50 p-4 rounded-lg">
                <h4 class="font-medium text-yellow-700 mb-2">Allegati DOCX:</h4>
                <ul class="text-sm text-gray-700">
                    <li>• Convocazione.docx (per arbitri)</li>
                    <li>• Lettera_Circolo.docx (per circolo)</li>
                </ul>
            </div>
        </div>
    `;

            document.getElementById('preview-content').innerHTML = html;
            document.getElementById('preview-modal').classList.remove('hidden');
        }

        function closePreview() {
            document.getElementById('preview-modal').classList.add('hidden');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }


// Raccoglie le clausole selezionate dal form
function getSelectedClauses() {
    const clauses = {};
    // Trova tutti i radio button delle clausole che sono selezionati
    document.querySelectorAll('input[type="radio"][name^="clauses["]:checked').forEach(radio => {
        // Estrai il nome del placeholder dal name attribute (es: "clauses[CLAUSOLA_CLUB_SPESE]")
        const match = radio.name.match(/clauses\[([^\]]+)\]/);
        if (match) {
            // Salva il valore (anche se vuoto - il backend farà il filtraggio)
            clauses[match[1]] = radio.value;
        }
    });
    return clauses;
}

// Salva le clausole via AJAX
async function saveClauses() {
    const clauses = getSelectedClauses();

    const response = await fetch(`{{ route('admin.tournament-notifications.save-clauses', ['notification' => $notification->id]) }}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ clauses: clauses })
    });

    if (!response.ok) {
        throw new Error('Errore nel salvataggio delle clausole');
    }

    return await response.json();
}

        // Gestione rigenerazione documenti
        async function regenerateDocuments() {
            const button = document.getElementById('regenerateButton');
            const originalText = button.innerHTML;
            try {
                // Disabilita il bottone e mostra loading
                button.disabled = true;
                button.innerHTML = `
            <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Rigenerazione in corso...
        `;

        // 1. Salva le clausole selezionate prima di generare i documenti
                try {
                    await saveClauses();
                } catch (error) {
                    console.error('Errore nel salvataggio delle clausole:', error);
                    showToast('Errore nel salvataggio delle clausole', true);
                    throw error;
                }

        // 2. Genera convocazione (con clausole per arbitri)
                        await fetch(
                    `{{ route('admin.tournament-notifications.generate-document', ['notification' => $notification->id, 'type' => 'convocation']) }}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    });

        // 3. Genera lettera circolo (con clausole per circolo)
                        await fetch(
                    `{{ route('admin.tournament-notifications.generate-document', ['notification' => $notification->id, 'type' => 'club_letter']) }}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    });

                // 5. Aggiorna stato documenti nel modal
                if (typeof openDocumentManager === 'function') {
                    openDocumentManager({{ $notification->id }});
                }

                showToast('Documenti rigenerati con clausole selezionate');

            } catch (error) {
                console.error('Errore durante la rigenerazione:', error);
                showToast('Errore durante la rigenerazione dei documenti', true);
            } finally {
                // Ripristina il bottone
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }

        function toggleSection(sectionId) {
            const content = document.getElementById(`${sectionId}-content`);
            const icon = document.getElementById(`${sectionId}-icon`);

            // Se la sezione non esiste (render condizionale), non fare nulla
            if (!content || !icon) return;

            // Se l'elemento usa display:none inline, mantieni compatibilita'
            const isHiddenByClass = content.classList.contains('hidden');
            const isHiddenByStyle = content.style && content.style.display === 'none';

            if (isHiddenByClass || isHiddenByStyle) {
                content.classList.remove('hidden');
                if (content.style) content.style.display = '';
                icon.style.transform = 'rotate(180deg)';
                localStorage.setItem(`section_${sectionId}`, 'open');
            } else {
                // Se non usa la classe hidden, applicala per uniformita'
                content.classList.add('hidden');
                icon.style.transform = 'rotate(0)';
                localStorage.setItem(`section_${sectionId}`, 'closed');
            }
        }

        // On page load, restore accordion states
        document.addEventListener('DOMContentLoaded', function() {
            ['clausole', 'arbitri', 'preimpostati', 'istituzionali'].forEach(sectionId => {
                const content = document.getElementById(`${sectionId}-content`);
                const icon = document.getElementById(`${sectionId}-icon`);
                if (!content || !icon) return; // sezione opzionale non presente

                const savedState = localStorage.getItem(`section_${sectionId}`);
                if (savedState === 'open') {
                    // Apri solo se chiusa
                    const isHiddenByClass = content.classList.contains('hidden');
                    const isHiddenByStyle = content.style && content.style.display === 'none';
                    if (isHiddenByClass || isHiddenByStyle) {
                        toggleSection(sectionId);
                    }
                }
            });
        });

        // ═══════════════════════════════════════════════════════════════════════
        // FUNZIONI PER NOTIFICHE GARE NAZIONALI
        // ═══════════════════════════════════════════════════════════════════════

        // Conferma invio notifica nazionale
        function confirmNationalSend() {
            const type = document.querySelector('input[name="notification_type"]')?.value;
            const typeLabel = type === 'crc_referees' ? 'arbitri designati' : 'osservatori';

            // Conta destinatari CC selezionati
            let ccCount = 0;
            document.querySelectorAll('input[name^="cc_"]:checked').forEach(() => ccCount++);

            const sendToCampionati = document.getElementById('send_to_campionati')?.checked;
            const totalRecipients = (sendToCampionati ? 1 : 0) + ccCount;

            if (totalRecipients === 0) {
                alert('Seleziona almeno un destinatario.');
                return false;
            }

            return confirm(
                `Stai per inviare la notifica ${typeLabel} a ${totalRecipients} destinatari.\n\nVuoi procedere con l'invio?`
            );
        }

        // Mostra anteprima notifica nazionale
        function showNationalPreview(type) {
            const subject = document.getElementById('subject').value || 'Nessun oggetto';
            const message = document.getElementById('message').value || 'Nessun messaggio';
            const typeLabel = type === 'crc' ? 'Designazione Arbitri (CRC)' : 'Designazione Osservatori (Zona)';

            // Raccogli destinatari TO
            const sendToCampionati = document.getElementById('send_to_campionati')?.checked;

            // Raccogli destinatari CC
            const ccRecipients = [];
            document.querySelectorAll('input[name^="cc_"]:checked').forEach(cb => {
                const label = cb.closest('.flex')?.querySelector('label');
                if (label) {
                    ccRecipients.push(label.textContent.trim());
                }
            });

            // Costruisci HTML preview
            let html = `
                <div class="space-y-4">
                    <div class="bg-purple-50 p-3 rounded-lg">
                        <p class="text-sm font-medium text-purple-800">${typeLabel}</p>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-700 mb-2">Oggetto:</h4>
                        <p class="text-gray-900">${escapeHtml(subject)}</p>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-700 mb-2">Messaggio:</h4>
                        <p class="text-gray-900 whitespace-pre-wrap">${escapeHtml(message)}</p>
                    </div>

                    <div class="bg-green-50 p-4 rounded-lg">
                        <h4 class="font-medium text-green-700 mb-2">Destinatario Principale (TO):</h4>
                        <ul class="text-sm text-gray-700">
            `;

            if (sendToCampionati) {
                html += `<li class="flex items-center"><span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>Comitato Campionati (campionati@federgolf.it)</li>`;
            }

            html += `
                        </ul>
                    </div>

                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h4 class="font-medium text-blue-700 mb-2">In Copia (CC) - ${ccRecipients.length} destinatari:</h4>
                        <ul class="text-sm text-gray-700 space-y-1 max-h-32 overflow-y-auto">
            `;

            ccRecipients.forEach(rec => {
                html += `<li class="flex items-center"><span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>${escapeHtml(rec)}</li>`;
            });

            html += `
                        </ul>
                    </div>

                    <div class="bg-gray-100 p-4 rounded-lg">
                        <p class="text-sm text-gray-600"><strong>Allegati:</strong> Nessuno</p>
                    </div>
                </div>
            `;

            document.getElementById('preview-content').innerHTML = html;
            document.getElementById('preview-modal').classList.remove('hidden');
        }
    </script>
@endsection
