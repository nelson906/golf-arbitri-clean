@extends('layouts.admin')

@section('title', ' ')

@section('content')

    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📧 Invia Notifica Assegnazione - {{ $tournament->name }}
            </h2>
            <a href="{{ route('tournaments.show', $tournament) }}"
                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                ← Torna al Torneo
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

                    {{-- Document Status --}}
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
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">

                            <form method="POST" action="{{ route('admin.tournaments.send-assignment-with-convocation', $tournament) }}" class="space-y-6">
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
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">{{ old('message',
'Si comunica l\'assegnazione degli arbitri per il torneo ' . $tournament->name . ' che si terrà ' . $tournament->start_date->format('d/m/Y') . ($tournament->start_date->format('d/m/Y') != $tournament->end_date->format('d/m/Y') ? ' - ' . $tournament->end_date->format('d/m/Y') : '') . ' presso ' . $tournament->club->name . '.

Si prega di prendere nota degli arbitri assegnati e di procedere con le comunicazioni necessarie.

Cordiali saluti'
                                    ) }}</textarea>
                                </div>

                                {{-- ACCORDION: Clausole Aggiuntive --}}
                                @if(isset($availableClauses) && !empty($availableClauses))
                                <div class="bg-white rounded-lg shadow-md mb-6 overflow-hidden">
                                    <button type="button" class="w-full px-6 py-4 text-left flex justify-between items-center bg-blue-50 hover:bg-blue-100"
                                            onclick="toggleSection('clausole')">
                                        <div>
                                            <h5 class="text-lg font-semibold text-gray-800">📝 Clausole Aggiuntive</h5>
                                            <small class="text-gray-600">Seleziona le clausole da includere</small>
                                        </div>
                                        <svg id="clausole-icon" class="w-6 h-6 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
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

                                        @if($clubClauses->isNotEmpty())
                                        <div class="mb-6 pb-6 border-b border-gray-200">
                                            <h6 class="text-base font-semibold text-blue-700 mb-4">Clausole Lettera Circolo</h6>

                                            @foreach(['spese', 'logistica', 'responsabilita'] as $category)
                                                @php $categoryClauses = $clubClausesByCategory->get($category, collect()); @endphp
                                                @if($categoryClauses->isNotEmpty())
                                                <div class="mb-5">
                                                    <label class="block text-sm font-semibold text-gray-700 mb-3">
                                                        {{ \App\Models\NotificationClause::CATEGORIES[$category] ?? ucfirst($category) }}
                                                    </label>
                                                    @foreach($categoryClauses as $clause)
                                                        <div class="flex items-start mb-3 p-3 bg-gray-50 rounded-lg">
                                                            <input type="radio" name="clauses[CLAUSOLA_{{ strtoupper($category) }}]" value="{{ $clause['id'] }}"
                                                                   id="clause_club_{{ $clause['id'] }}" class="mt-1 w-4 h-4 text-blue-600">
                                                            <label for="clause_club_{{ $clause['id'] }}" class="ml-3 flex-1 cursor-pointer">
                                                                <span class="block font-medium text-gray-900">{{ $clause['title'] }}</span>
                                                                <span class="block text-sm text-gray-600 mt-1">{{ Str::limit($clause['content'], 150) }}</span>
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                    <div class="flex items-start p-3">
                                                        <input type="radio" name="clauses[CLAUSOLA_{{ strtoupper($category) }}]" value=""
                                                               id="clause_club_none_{{ $category }}" checked class="mt-1 w-4 h-4">
                                                        <label for="clause_club_none_{{ $category }}" class="ml-3 text-sm text-gray-500 italic cursor-pointer">
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
                                                $refereeClauses = $refereeClauses->merge(collect($availableClauses['referee']));
                                            }
                                            if (isset($availableClauses['all'])) {
                                                $refereeClauses = $refereeClauses->merge(collect($availableClauses['all']));
                                            }
                                            $refereeClausesByCategory = $refereeClauses->groupBy('category');
                                        @endphp

                                        @if($refereeClauses->isNotEmpty())
                                        <div>
                                            <h6 class="text-base font-semibold text-green-700 mb-4">Clausole Convocazione Arbitri</h6>

                                            @foreach(['responsabilita', 'comunicazioni', 'altro'] as $category)
                                                @php $categoryClauses = $refereeClausesByCategory->get($category, collect()); @endphp
                                                @if($categoryClauses->isNotEmpty())
                                                <div class="mb-5">
                                                    <label class="block text-sm font-semibold text-gray-700 mb-3">
                                                        {{ \App\Models\NotificationClause::CATEGORIES[$category] ?? ucfirst($category) }}
                                                    </label>
                                                    @foreach($categoryClauses as $clause)
                                                        <div class="flex items-start mb-3 p-3 bg-gray-50 rounded-lg">
                                                            <input type="radio" name="clauses[CLAUSOLA_{{ strtoupper($category) }}]" value="{{ $clause['id'] }}"
                                                                   id="clause_ref_{{ $clause['id'] }}" class="mt-1 w-4 h-4 text-green-600">
                                                            <label for="clause_ref_{{ $clause['id'] }}" class="ml-3 flex-1 cursor-pointer">
                                                                <span class="block font-medium text-gray-900">{{ $clause['title'] }}</span>
                                                                <span class="block text-sm text-gray-600 mt-1">{{ Str::limit($clause['content'], 150) }}</span>
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                    <div class="flex items-start p-3">
                                                        <input type="radio" name="clauses[CLAUSOLA_{{ strtoupper($category) }}]" value=""
                                                               id="clause_ref_none_{{ $category }}" checked class="mt-1 w-4 h-4">
                                                        <label for="clause_ref_none_{{ $category }}" class="ml-3 text-sm text-gray-500 italic cursor-pointer">
                                                            Nessuna clausola
                                                        </label>
                                                    </div>
                                                </div>
                                                @endif
                                            @endforeach
                                        </div>
                                        @endif
                                    </div>
                                </div>
                                @endif

                                {{-- ACCORDION: Destinatari Arbitri --}}
                                <div class="bg-white rounded-lg shadow-md mb-6 overflow-hidden">
                                    <button type="button" class="w-full px-6 py-4 text-left flex justify-between items-center bg-green-50 hover:bg-green-100"
                                            onclick="toggleSection('arbitri')">
                                        <div>
                                            <h5 class="text-lg font-semibold text-gray-800">👥 Destinatari Arbitri</h5>
                                            <small class="text-gray-600">{{ $assignedReferees->count() }} arbitri</small>
                                        </div>
                                        <svg id="arbitri-icon" class="w-6 h-6 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>

<div id="arbitri-content" class="p-6 hidden">
                                        @if ($assignedReferees->count() > 0)
                                            <div class="space-y-3 max-h-64 overflow-y-auto">
                                                @foreach ($assignedReferees as $referee)
                                                    <div class="flex items-center">
                                                        <input type="checkbox" name="recipients[]" value="{{ $referee->id }}"
                                                               id="referee_{{ $referee->id }}" checked
                                                               class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                                        <label for="referee_{{ $referee->id }}" class="ml-3 text-sm flex-1">
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
                                        <button type="button" class="w-full px-6 py-4 text-left flex justify-between items-center bg-purple-50 hover:bg-purple-100"
                                                onclick="toggleSection('preimpostati')">
                                            <div>
                                                <h5 class="text-lg font-semibold text-gray-800">📋 Indirizzi Preimpostati</h5>
                                                <small class="text-gray-600">Indirizzi standard per le notifiche</small>
                                            </div>
                                            <svg id="preimpostati-icon" class="w-6 h-6 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>

                                        <div id="preimpostati-content" class="p-6" style="display: none;">
                                            <div class="bg-gray-50 p-4 rounded-lg">
                                                @foreach ($groupedEmails as $category => $emails)
                                                    <div class="mb-4 last:mb-0">
                                                        <h4 class="font-medium text-gray-900 mb-2">{{ $category }}</h4>
                                                        <div class="space-y-2">
                                                            @foreach ($emails as $email)
                                                                <div class="flex items-center">
                                                                    <input type="checkbox" id="fixed_{{ $email->id }}"
                                                                        name="fixed_addresses[]" value="{{ $email->id }}"
                                                                        {{ $email->is_default ? 'checked' : '' }}
                                                                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                                    <label for="fixed_{{ $email->id }}"
                                                                        class="ml-2 text-sm text-gray-700">
                                                                        <span class="font-medium">{{ $email->name }}</span>
                                                                        <span class="text-gray-500">({{ $email->email }})</span>
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
                                        <button type="button" class="w-full px-6 py-4 text-left flex justify-between items-center bg-blue-50 hover:bg-blue-100"
                                                onclick="toggleSection('istituzionali')">
                                            <div>
                                                <h5 class="text-lg font-semibold text-gray-800">📮 Email Istituzionali</h5>
                                                <small class="text-gray-600">Email degli organi federali</small>
                                            </div>
                                            <svg id="istituzionali-icon" class="w-6 h-6 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
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
                                                                            <span class="font-medium">{{ $email->name }}</span>
                                                                            <span class="text-gray-500">({{ $email->email }})</span>
                                                                            @if ($email->receive_all_notifications)
                                                                                <span class="text-xs text-blue-600">• Tutte le notifiche</span>
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
                                            <input type="checkbox" name="send_to_club" id="send_to_club" value="1" checked
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
                                                <p class="text-xs text-red-500 mt-1">
                                                    ⚠️ Email della sezione non configurata
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

                                {{-- Submit --}}
                                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                                    <a href="{{ route('admin.tournaments.show', $tournament) }}"
                                        class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                        Annulla
                                    </a>
                                    <button type="submit"
                                        class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                        </svg>
                                        Invia Notifiche
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.tournament-notifications._document_manager_modal')

    <script>
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
</script>
@endsection
