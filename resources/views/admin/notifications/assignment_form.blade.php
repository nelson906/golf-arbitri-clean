{{-- File: resources/views/admin/notifications/assignment_form.blade.php --}}
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

            @if (session('error'))
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-400 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
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
                                                        <div class="text-xs text-gray-600 ml-2 p-2 bg-gray-50 rounded">
                                                            • {{ $referee->name }}
                                                            <div class="text-xs text-gray-500">{{ $referee->email }}</div>
                                                            @if ($referee->pivot->assigned_at)
                                                                <div class="text-xs text-gray-400">
                                                                    Assegnato:
                                                                    {{ \Carbon\Carbon::parse($referee->pivot->assigned_at)->format('d/m/Y H:i') }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <div class="text-gray-400 text-sm">
                                        ⚠️ Nessun arbitro assegnato al torneo
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Assegna prima gli arbitri per poter inviare le notifiche
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
<div id="message-preview" class="mt-2 p-4 bg-gray-50 rounded-lg prose prose-sm"
     style="display:none;">
</div>
                {{-- Main Content: Form --}}
                <div class="lg:col-span-2">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">

                            <form method="POST"
                                action="{{ route('admin.tournaments.send-assignment-with-convocation', $tournament) }}"
                                class="space-y-6">
                                @csrf

                                {{-- Template Selection --}}
                                {{-- @if ($templates->count() > 0) --}}
                                    {{-- <div>
                                        <label for="template_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            📝 Template Email (Opzionale)
                                        </label>
                                        <select name="template_id" id="template_id"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200"
                                            onchange="loadTemplate(this.value)">
                                            <option value="">Seleziona un template...</option>
                                            @foreach ($templates as $template)
                                                <option value="{{ $template->id }}"
                                                    data-subject="{{ $template->subject }}"
                                                    data-body="{{ $template->body }}">
                                                    {{ $template->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div> --}}
                                {{-- @endif --}}

                                {{-- Subject --}}
                                <div>
                                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                                        📧 Oggetto Email <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="subject" id="subject"
                                        value="{{ old('subject', 'Assegnazione Arbitri - ' . $tournament->name) }}"
                                        required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 @error('subject') border-red-500 @enderror">
                                    @error('subject')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Message --}}
                                <div>
                                    <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                                        📝 Messaggio <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="message" id="message" rows="8" required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 @error('message') border-red-500 @enderror"
                                        placeholder="Scrivi il messaggio per la notifica...">{{ old(
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
                                    @error('message')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <!-- Gestione Allegati -->
                                @if (
                                    $hasExistingConvocation ||
                                        (isset($documentStatus) && ($documentStatus['hasConvocation'] || $documentStatus['hasClubLetter'])))
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                        <h4 class="font-medium text-green-800 mb-3">📎 Documenti Disponibili</h4>

                                        <div class="space-y-2">
                                            @if (isset($documentStatus) && $documentStatus['hasConvocation'])
                                                <div class="flex items-center text-sm text-green-700">
                                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd"
                                                            d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                                                            clip-rule="evenodd"></path>
                                                    </svg>
                                                    Convocazione disponibile
                                                </div>
                                            @endif

                                            @if (isset($documentStatus) && $documentStatus['hasClubLetter'])
                                                <div class="flex items-center text-sm text-green-700">
                                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd"
                                                            d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                                                            clip-rule="evenodd"></path>
                                                    </svg>
                                                    Lettera circolo disponibile
                                                </div>
                                            @endif
                                        </div>

                                        <div class="mt-3">
                                            <label class="flex items-center">
                                                <input type="checkbox" name="attach_convocation" value="1" checked
                                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                <span class="ml-2 text-sm font-medium text-green-800">
                                                    Allega documenti disponibili alle email
                                                </span>
                                            </label>
                                            <p class="text-xs text-green-600 mt-1">
                                                ℹ️ La lettera circolo verrà allegata solo alle email inviate al circolo
                                                organizzatore
                                            </p>
                                        </div>
                                    </div>
                                @endif
                                {{-- Attachment Options --}}
                                @if ($documentStatus['hasConvocation'] || $documentStatus['hasClubLetter'])
                                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                        <div class="flex items-start">
                                            <input type="checkbox" name="attach_documents" id="attach_documents"
                                                value="1" checked
                                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1">
                                            <div class="ml-3">
                                                <label for="attach_documents" class="text-sm font-medium text-blue-900">
                                                    📎 Allega documenti disponibili
                                                </label>
                                                <div class="text-xs text-blue-700 mt-1 space-y-1">
                                                    @if ($documentStatus['hasConvocation'])
                                                        <div>✓ Convocazione SZR (solo per arbitri)</div>
                                                    @endif
                                                    @if ($documentStatus['hasClubLetter'])
                                                        <div>✓ Lettera Circolo (solo per circolo)</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                {{-- Recipients: Referees --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        👥 Destinatari Arbitri
                                    </label>
                                    @if ($assignedReferees->count() > 0)
                                        <div
                                            class="space-y-3 max-h-64 overflow-y-auto border border-gray-200 rounded-lg p-4">
                                            @foreach ($assignedReferees as $referee)
                                                <div class="flex items-center">
                                                    <input type="checkbox" name="recipients[]"
                                                        value="{{ $referee->id }}" id="referee_{{ $referee->id }}"
                                                        checked
                                                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                    <label for="referee_{{ $referee->id }}"
                                                        class="ml-3 text-sm text-gray-900 flex-1">
                                                        <div class="flex items-center justify-between">
                                                            <div>
                                                                <span class="font-medium">{{ $referee->name }}</span>
                                                                <span class="text-gray-600">({{ $referee->role }})</span>
                                                                <div class="text-xs text-gray-500">{{ $referee->email }}
                                                                </div>
                                                            </div>
                                                            <div class="text-xs text-gray-400">
                                                                Assegnato
                                                                {{ \Carbon\Carbon::parse($referee->pivot->assigned_at)->format('d/m/Y') }}
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                            <p class="text-sm text-yellow-800">⚠️ Nessun arbitro assegnato al torneo</p>
                                        </div>
                                    @endif
                                </div>

                                {{-- Club Notification --}}
                                <div>
                                    <div class="flex items-center">
                                        <input type="checkbox" name="send_to_club" id="send_to_club" value="1"
                                            checked
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <label for="send_to_club" class="ml-2 text-sm font-medium text-gray-700">
                                            🏌️ Invia notifica al circolo organizzatore
                                        </label>
                                    </div>
                                    <div class="mt-2 ml-6 text-sm text-gray-600">
                                        Circolo: {{ $tournament->club->name }}
                                        @if ($tournament->club->email)
                                            ({{ $tournament->club->email }})
                                        @else
                                            <span class="text-red-600">⚠️ Email mancante</span>
                                        @endif
                                    </div>
                                </div>
                                <!-- Indirizzi Preimpostati -->
                                @if (isset($groupedEmails) && $groupedEmails->count() > 0)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-3">
                                            📋 Indirizzi Preimpostati
                                        </label>
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
                                @endif
                                {{-- Institutional Emails --}}
                                {{-- ✅ Email Istituzionali - CORRETTA --}}
                                @if ($groupedEmails && $groupedEmails->count() > 0)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-3">
                                            📮 Email Istituzionali
                                        </label>
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
                                                                            <span class="text-xs text-blue-600">• Tutte le
                                                                                notifiche</span>
                                                                        @endif
                                                                    </label>
                                                                </div>
                                                            @else
                                                                {{-- DEBUG: Mostra il tipo di valore non valido --}}
                                                                <!-- Email non valida: {{ gettype($email) }} - {{ json_encode($email) }} -->
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
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
                                {{-- Submit Buttons --}}
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
    {{-- Modal Gestione Documenti --}}
    <div id="documentManagerModal"
        class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center pb-3 border-b">
                <h5 class="text-xl font-bold">📄 Gestione Documenti Notifica</h5>
                <button type="button" onclick="closeModal('documentManagerModal')"
                    class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>

            <div id="documentManagerContent" class="py-4">
                {{-- Contenuto caricato dinamicamente --}}
            </div>

            {{-- Pulsante Chiudi in basso --}}
            <div class="pt-4 border-t mt-4">
                <button type="button" onclick="closeModal('documentManagerModal')"
                    class="w-full sm:w-auto px-6 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Chiudi
                </button>
            </div>
        </div>
    </div>

    {{-- Modal Upload Documento --}}
    <div id="uploadDocumentModal"
        class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center pb-3">
                <h5 class="text-lg font-bold">📤 Carica Documento Modificato</h5>
                <button type="button" onclick="closeModal('uploadDocumentModal')" class="text-gray-400">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>

            <form id="uploadDocumentForm" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="document_type" id="upload_document_type">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Seleziona file Word (.docx)
                    </label>
                    <input type="file" name="document" required accept=".docx"
                        class="w-full border rounded px-3 py-2">
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded p-3 mb-4">
                    <p class="text-sm text-yellow-700">
                        <i class="fas fa-info-circle mr-1"></i>
                        Il file caricato sostituirà il documento esistente
                    </p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('uploadDocumentModal')"
                        class="px-4 py-2 bg-gray-500 text-white rounded">
                        Annulla
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded">
                        <i class="fas fa-upload mr-1"></i> Carica
                    </button>
                </div>
            </form>
        </div>
    </div>


    <script>
        function openDocumentManagerModal(tournamentId) {
            // Cerca se esiste una notifica per questo torneo
            fetch(`/admin/tournament-notifications/find-by-tournament/${tournamentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.notification_id) {
                        openDocumentManager(data.notification_id);
                    } else {
                        alert('Nessuna notifica trovata. Creane una prima.');
                    }
                });
        }

        function openDocumentManager(notificationId) {
            const content = document.getElementById('documentManagerContent');
            content.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin text-2xl"></i></div>';

            openModal('documentManagerModal');

            fetch(`/admin/tournament-notifications/${notificationId}/documents-status`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    content.innerHTML = buildDocumentManagerContent(data);
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML =
                        '<div class="text-center text-red-600">Errore nel caricamento dei documenti</div>';
                });
        }

        function buildDocumentManagerContent(data) {
            return `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="border rounded-lg p-4 ${data.convocation ? 'border-green-200 bg-green-50' : 'border-gray-200'}">
                <h4 class="font-bold text-lg mb-3 flex items-center">
                    <i class="fas fa-file-word mr-2 text-blue-600"></i>
                    Convocazione SZR
                </h4>

                ${data.convocation ? `
                            <div class="space-y-3">
                                <div class="text-sm text-gray-600">
                                    <p><strong>File:</strong> ${data.convocation.filename}</p>
                                    <p><strong>Generato:</strong> ${data.convocation.generated_at}</p>
                                    <p><strong>Dimensione:</strong> ${data.convocation.size}</p>
                                </div>

                                <div class="flex flex-col space-y-2">
                                    <a href="/admin/tournament-notifications/${data.notification_id}/download/convocation"
                                       class="bg-green-600 text-white px-4 py-2 rounded text-center hover:bg-green-700">
                                        <i class="fas fa-download mr-1"></i> Scarica
                                    </a>

                                    <button onclick="openUploadModal(${data.notification_id}, 'convocation')"
                                            class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                                        <i class="fas fa-upload mr-1"></i> Sostituisci
                                    </button>

                                    <button onclick="regenerateDocument(${data.notification_id}, 'convocation')"
                                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                        <i class="fas fa-redo mr-1"></i> Rigenera
                                    </button>

                                    <button onclick="deleteDocument(${data.notification_id}, 'convocation')"
                                            class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                                        <i class="fas fa-trash mr-1"></i> Elimina
                                    </button>
                                </div>
                            </div>
                        ` : `
                            <div class="text-center py-8">
                                <i class="fas fa-file-excel text-4xl text-gray-300 mb-3"></i>
                                <p class="text-gray-500 mb-4">Nessun documento presente</p>
                                <button onclick="generateDocument(${data.notification_id}, 'convocation')"
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                    <i class="fas fa-plus mr-1"></i> Genera Convocazione
                                </button>
                            </div>
                        `}
            </div>

            <div class="border rounded-lg p-4 ${data.club_letter ? 'border-green-200 bg-green-50' : 'border-gray-200'}">
                <h4 class="font-bold text-lg mb-3 flex items-center">
                    <i class="fas fa-building mr-2 text-green-600"></i>
                    Lettera Circolo
                </h4>

                ${data.club_letter ? `
                            <div class="space-y-3">
                                <div class="text-sm text-gray-600">
                                    <p><strong>File:</strong> ${data.club_letter.filename}</p>
                                    <p><strong>Generato:</strong> ${data.club_letter.generated_at}</p>
                                    <p><strong>Dimensione:</strong> ${data.club_letter.size}</p>
                                </div>

                                <div class="flex flex-col space-y-2">
                                    <a href="/admin/tournament-notifications/${data.notification_id}/download/club_letter"
                                       class="bg-green-600 text-white px-4 py-2 rounded text-center hover:bg-green-700">
                                        <i class="fas fa-download mr-1"></i> Scarica
                                    </a>

                                    <button onclick="openUploadModal(${data.notification_id}, 'club_letter')"
                                            class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                                        <i class="fas fa-upload mr-1"></i> Sostituisci
                                    </button>

                                    <button onclick="regenerateDocument(${data.notification_id}, 'club_letter')"
                                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                        <i class="fas fa-redo mr-1"></i> Rigenera
                                    </button>

                                    <button onclick="deleteDocument(${data.notification_id}, 'club_letter')"
                                            class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                                        <i class="fas fa-trash mr-1"></i> Elimina
                                    </button>
                                </div>
                            </div>
                        ` : `
                            <div class="text-center py-8">
                                <i class="fas fa-file-excel text-4xl text-gray-300 mb-3"></i>
                                <p class="text-gray-500 mb-4">Nessun documento presente</p>
                                <button onclick="generateDocument(${data.notification_id}, 'club_letter')"
                                        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                                    <i class="fas fa-plus mr-1"></i> Genera Lettera
                                </button>
                            </div>
                        `}
            </div>
        </div>
    `;
        }

        // Funzioni base per modal
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Funzione per aprire modal upload
        function openUploadModal(notificationId, documentType) {
            document.getElementById('upload_document_type').value = documentType;
            document.getElementById('uploadDocumentForm').action =
                `/admin/tournament-notifications/${notificationId}/upload/${documentType}`;
            openModal('uploadDocumentModal');
        }

        // Genera documento
        function generateDocument(notificationId, type) {
            if (confirm('Generare il documento?')) {
                // Mostra loading
                const content = document.getElementById('documentManagerContent');
                content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-blue-500"></i><p class="mt-4">Generazione in corso...</p></div>';

                fetch(`/admin/tournament-notifications/${notificationId}/generate/${type}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (response.ok) {
                        // Ricarica il contenuto del modal
                        setTimeout(() => openDocumentManager(notificationId), 1000);
                    } else {
                        alert('Errore nella generazione del documento');
                        openDocumentManager(notificationId);
                    }
                })
                .catch(error => {
                    alert('Errore: ' + error.message);
                    openDocumentManager(notificationId);
                });
            }
        }

        // Rigenera documento
        function regenerateDocument(notificationId, type) {
            if (confirm('Rigenerare il documento? Questo sovrascriverà il file esistente.')) {
                // Mostra loading
                const content = document.getElementById('documentManagerContent');
                content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-orange-500"></i><p class="mt-4">Rigenerazione in corso...</p></div>';

                fetch(`/admin/tournament-notifications/${notificationId}/regenerate/${type}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (response.ok) {
                        // Ricarica il contenuto del modal
                        setTimeout(() => openDocumentManager(notificationId), 1000);
                    } else {
                        alert('Errore nella rigenerazione del documento');
                        openDocumentManager(notificationId);
                    }
                })
                .catch(error => {
                    alert('Errore: ' + error.message);
                    openDocumentManager(notificationId);
                });
            }
        }

        // Elimina documento
        function deleteDocument(notificationId, type) {
            if (confirm('Eliminare il documento?')) {
                // Mostra loading
                const content = document.getElementById('documentManagerContent');
                content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-red-500"></i><p class="mt-4">Eliminazione in corso...</p></div>';

                // Usa AJAX per rimanere nel modal
                fetch(`/admin/tournament-notifications/${notificationId}/document/${type}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-HTTP-Method-Override': 'DELETE'
                    },
                    body: JSON.stringify({
                        '_method': 'DELETE'
                    })
                })
                .then(response => {
                    if (response.ok) {
                        // Aspetta un attimo e poi ricarica il contenuto del modal
                        setTimeout(() => {
                            openDocumentManager(notificationId);
                        }, 500);
                    } else {
                        return response.text().then(text => {
                            console.error('Response:', text);
                            alert('Errore nell\'eliminazione del documento');
                            openDocumentManager(notificationId);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Errore di connessione: ' + error.message);
                    openDocumentManager(notificationId);
                });
            }
        }

        // Chiudi modal cliccando fuori SOLO per uploadDocumentModal
        window.onclick = function(event) {
            const modal = document.getElementById('uploadDocumentModal');
            if (event.target === modal) {
                closeModal('uploadDocumentModal');
            }
        }

        // Gestione upload documento
        document.addEventListener('DOMContentLoaded', function() {
            const uploadForm = document.getElementById('uploadDocumentForm');
            if (uploadForm) {
                uploadForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const notificationId = this.action.match(/\/(\d+)\/upload/)[1];

                    // Chiudi modal upload
                    closeModal('uploadDocumentModal');

                    // Mostra loading nel modal principale
                    const content = document.getElementById('documentManagerContent');
                    content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-purple-500"></i><p class="mt-4">Caricamento in corso...</p></div>';

                    fetch(this.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(response => {
                        if (response.ok) {
                            // Ricarica il contenuto del modal
                            setTimeout(() => openDocumentManager(notificationId), 1500);
                        } else {
                            alert('Errore nel caricamento del documento');
                            openDocumentManager(notificationId);
                        }
                    })
                    .catch(error => {
                        alert('Errore: ' + error.message);
                        openDocumentManager(notificationId);
                    });
                });
            }
        });
    </script>


    {{-- JavaScript for Dynamic Functionality --}}
    <script>
        // Load template content
        function loadTemplate(templateId) {
            if (!templateId) return;

            const option = document.querySelector(`option[value="${templateId}"]`);
            if (option) {
                document.getElementById('subject').value = option.dataset.subject || '';
        // Carica body convertendo markdown in HTML per textarea
        let body = option.dataset.body || '';
        // Converti ** in newline per textarea
        body = body.replace(/\*\*(.*?)\*\*/g, '$1');
        document.getElementById('message').value = body;
            }
        }

        // Add additional email field
        function addEmailField() {
            const container = document.getElementById('additional-emails-container');
            const div = document.createElement('div');
            div.className = 'grid grid-cols-1 md:grid-cols-2 gap-3';
            div.innerHTML = `
                <input type="email" name="additional_emails[]" placeholder="email@esempio.com"
                       class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                <div class="flex space-x-2">
                    <input type="text" name="additional_names[]" placeholder="Nome (opzionale)"
                           class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                    <button type="button" onclick="this.closest('.grid').remove()"
                            class="px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
            container.appendChild(div);
        }

        // Form validation before submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const recipients = document.querySelectorAll('input[name="recipients[]"]:checked');
            const sendToClub = document.querySelector('input[name="send_to_club"]:checked');
            const institutionalEmails = document.querySelectorAll('input[name="institutional_emails[]"]:checked');
            const additionalEmails = Array.from(document.querySelectorAll('input[name="additional_emails[]"]'))
                .filter(input => input.value.trim() !== '');

            const totalRecipients = recipients.length + (sendToClub ? 1 : 0) + institutionalEmails.length +
                additionalEmails.length;

            if (totalRecipients === 0) {
                e.preventDefault();
                alert('Seleziona almeno un destinatario per la notifica.');
                return false;
            }

            return confirm(`Sei sicuro di voler inviare la notifica a ${totalRecipients} destinatari?`);
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addEmailBtn = document.getElementById('add-email-btn');
            const additionalEmailsContainer = document.getElementById('additional-emails-container');

            if (addEmailBtn && additionalEmailsContainer) {
                addEmailBtn.addEventListener('click', function() {
                    const newRow = document.createElement('div');
                    newRow.className = 'grid grid-cols-1 md:grid-cols-2 gap-3';
                    newRow.innerHTML = `
                <input type="email"
                       name="additional_emails[]"
                       placeholder="email@esempio.com"
                       class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                <div class="flex">
                    <input type="text"
                           name="additional_names[]"
                           placeholder="Nome (opzionale)"
                           class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                    <button type="button" class="remove-email-btn ml-2 px-3 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
                    additionalEmailsContainer.appendChild(newRow);

                    // Add remove functionality to the new button
                    const removeBtn = newRow.querySelector('.remove-email-btn');
                    removeBtn.addEventListener('click', function() {
                        newRow.remove();
                    });
                });

                // Handle existing remove buttons (if any)
                additionalEmailsContainer.addEventListener('click', function(e) {
                    if (e.target.closest('.remove-email-btn')) {
                        e.target.closest('.grid').remove();
                    }
                });
            }
        });

function updatePreview() {
    const message = document.getElementById('message').value;
    const preview = document.getElementById('message-preview');

    // Converti markdown in HTML
    let html = message
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');

    preview.innerHTML = html;
    preview.style.display = 'block';
}

// Aggiungi listener
document.getElementById('message').addEventListener('input', updatePreview);
</script>

@endsection
