{{--
    Partial: Form notifica per CRC_ADMIN (national_admin) - Gare Nazionali
    Destinatari: TO campionati@federgolf.it, CC zona torneo + arbitri designati
    Allegati: Nessuno
--}}

@php
    // DEBUG: mostra quanti arbitri ci sono
    \Log::info('Form CRC - assignedReferees', [
        'count' => $assignedReferees->count(),
        'referees' => $assignedReferees->map(fn($r) => ['id' => $r->id, 'name' => $r->name, 'role' => $r->pivot->role ?? 'N/A'])->toArray(),
    ]);

    // Filtra solo arbitri designati (esclude osservatori che sono di competenza ZONA)
    $designatedReferees = $assignedReferees->filter(fn($ref) => $ref->pivot->role !== 'Osservatore');

    // Prepara lista arbitri per il template
    $refereesList = $designatedReferees->map(function($ref) {
        return "- {$ref->name} ({$ref->pivot->role})";
    })->implode("\n");

    // Template fisso per CRC
    $defaultMessage = "Si comunicano di seguito i nominativi degli arbitri designati alla gara in oggetto:

{$refereesList}

Torneo: {$tournament->name}
Date: {$tournament->start_date->format('d/m/Y')}" .
($tournament->start_date->format('d/m/Y') != $tournament->end_date->format('d/m/Y') ? " - {$tournament->end_date->format('d/m/Y')}" : "") . "
Circolo: {$tournament->club->name}

Cordiali saluti";

    // Email Comitato Campionati
    $comitatoCampionatiEmail = $groupedEmails?->flatten()->first(fn($e) => str_contains(strtolower($e->email ?? ''), 'campionati@'));

    // Admin zonali della zona del torneo
    $zoneAdmins = \App\Models\User::where('user_type', 'admin')
        ->where('zone_id', $tournament->club->zone_id)
        ->where('is_active', true)
        ->get();

    // Notifica CRC esistente (mia)
    $myNotification = \App\Models\TournamentNotification::where('tournament_id', $tournament->id)
        ->where('notification_type', 'crc_referees')
        ->first();

    // Notifica ZONA (controparte)
    $counterpartNotification = \App\Models\TournamentNotification::where('tournament_id', $tournament->id)
        ->where('notification_type', 'zone_observers')
        ->first();
@endphp

<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6">

        {{-- Stato notifiche per questa gara --}}
        <div class="mb-6 grid grid-cols-2 gap-4">
            {{-- Mia notifica (CRC) --}}
            <div class="p-3 rounded-lg {{ $myNotification && $myNotification->sent_at ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200' }}">
                <div class="flex items-center">
                    @if($myNotification && $myNotification->sent_at)
                        <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                        <span class="text-sm font-medium text-green-800">Arbitri designati: Inviata</span>
                    @else
                        <span class="w-3 h-3 bg-gray-400 rounded-full mr-2"></span>
                        <span class="text-sm font-medium text-gray-600">Arbitri designati: Da inviare</span>
                    @endif
                </div>
                @if($myNotification && $myNotification->sent_at)
                    <p class="text-xs text-green-600 mt-1">{{ $myNotification->sent_at->format('d/m/Y H:i') }}</p>
                @endif
            </div>

            {{-- Notifica controparte (ZONA) --}}
            <div class="p-3 rounded-lg {{ $counterpartNotification && $counterpartNotification->sent_at ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200' }}">
                <div class="flex items-center">
                    @if($counterpartNotification && $counterpartNotification->sent_at)
                        <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                        <span class="text-sm font-medium text-green-800">Osservatori (ZONA): Inviata</span>
                    @else
                        <span class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></span>
                        <span class="text-sm font-medium text-yellow-800">Osservatori (ZONA): In attesa</span>
                    @endif
                </div>
                @if($counterpartNotification && $counterpartNotification->sent_at)
                    <p class="text-xs text-green-600 mt-1">{{ $counterpartNotification->sent_at->format('d/m/Y H:i') }}</p>
                @endif
            </div>
        </div>

        {{-- Banner informativo --}}
        <div class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-500 rounded">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="text-sm text-blue-800">
                    <p class="font-medium">Notifica Gara Nazionale - Designazione Arbitri</p>
                    <p class="mt-1">Questa notifica comunica i nominativi degli arbitri designati al Comitato Campionati, con copia alla zona di competenza e agli arbitri interessati.</p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.tournaments.send-national-notification', $tournament) }}" class="space-y-6">
            @csrf
            <input type="hidden" name="notification_type" value="crc_referees">

            {{-- Oggetto Email --}}
            <div>
                <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                    Oggetto Email <span class="text-red-500">*</span>
                </label>
                <input type="text" name="subject" id="subject"
                    value="{{ old('subject', 'Designazione Arbitri - ' . $tournament->name) }}"
                    required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
            </div>

            {{-- Messaggio (template fisso ma modificabile) --}}
            <div>
                <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                    Messaggio <span class="text-red-500">*</span>
                </label>
                <textarea name="message" id="message" rows="12" required
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 font-mono text-sm">{{ old('message', $defaultMessage) }}</textarea>
                <p class="mt-1 text-xs text-gray-500">Il messaggio include automaticamente la lista degli arbitri designati.</p>
            </div>

            {{-- Destinatari Principali (TO) --}}
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h4 class="text-sm font-medium text-green-800 mb-3">
                    Destinatario Principale (TO)
                </h4>
                <div class="flex items-center">
                    <input type="checkbox" name="send_to_campionati" id="send_to_campionati" value="1" checked
                        class="h-4 w-4 text-green-600 border-gray-300 rounded">
                    <label for="send_to_campionati" class="ml-2 text-sm text-gray-700">
                        <span class="font-medium">Comitato Campionati</span>
                        <span class="text-gray-500">({{ config('golf.emails.ufficio_campionati', 'campionati@federgolf.it') }})</span>
                    </label>
                </div>
            </div>

            {{-- Destinatari in Copia (CC) --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="text-sm font-medium text-blue-800 mb-3">
                    Destinatari in Copia (CC)
                </h4>

                {{-- Zona del torneo --}}
                <div class="mb-4">
                    <p class="text-xs text-gray-600 mb-2 font-medium">Zona di competenza:</p>
                    <div class="flex items-center mb-2">
                        <input type="checkbox" name="send_to_zone" id="send_to_zone" value="1" checked
                            class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                        <label for="send_to_zone" class="ml-2 text-sm text-gray-700">
                            <span class="font-medium">{{ $tournament->club->zone->name ?? 'Zona' }}</span>
                            @if($tournament->club->zone->email ?? null)
                                <span class="text-gray-500">({{ $tournament->club->zone->email }})</span>
                            @endif
                        </label>
                    </div>
                    @if($zoneAdmins->count() > 0)
                        <div class="ml-6 space-y-1">
                            @foreach($zoneAdmins as $zoneAdmin)
                                <div class="flex items-center">
                                    <input type="checkbox" name="cc_zone_admins[]" value="{{ $zoneAdmin->id }}"
                                        id="zone_admin_{{ $zoneAdmin->id }}" checked
                                        class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    <label for="zone_admin_{{ $zoneAdmin->id }}" class="ml-2 text-xs text-gray-600">
                                        {{ $zoneAdmin->name }} ({{ $zoneAdmin->email }})
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Arbitri designati (esclude osservatori) --}}
                <div>
                    <p class="text-xs text-gray-600 mb-2 font-medium">Arbitri designati ({{ $designatedReferees->count() }}):</p>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        @forelse($designatedReferees as $referee)
                            <div class="flex items-center">
                                <input type="checkbox" name="cc_referees[]" value="{{ $referee->id }}"
                                    id="referee_{{ $referee->id }}" checked
                                    class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                <label for="referee_{{ $referee->id }}" class="ml-2 text-sm text-gray-700">
                                    <span class="font-medium">{{ $referee->name }}</span>
                                    <span class="text-gray-500">({{ $referee->pivot->role }})</span>
                                    <span class="text-xs text-gray-400">- {{ $referee->email }}</span>
                                </label>
                            </div>
                        @empty
                            <p class="text-xs text-gray-500 italic">Nessun arbitro designato (Direttore/Arbitro)</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Info: Nessun allegato --}}
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <div class="flex items-center text-gray-600">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-sm">Questa notifica non include allegati (convocazione/lettera circolo).</span>
                </div>
            </div>

            {{-- Hidden action field --}}
            <input type="hidden" name="action" id="form-action" value="send">

            {{-- Submit Buttons --}}
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-6 border-t border-gray-200">
                <a href="{{ route('admin.tournaments.show', $tournament) }}"
                    class="w-full sm:w-auto px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 text-center">
                    Annulla
                </a>

                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    {{-- Preview Button --}}
                    <button type="button" onclick="showNationalPreview('crc')"
                        class="w-full sm:w-auto px-5 py-2 bg-gray-100 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-200 flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Anteprima
                    </button>

                    {{-- Send Now Button --}}
                    <button type="submit" onclick="return confirmNationalSend()"
                        class="w-full sm:w-auto px-5 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        Invia Notifica
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
