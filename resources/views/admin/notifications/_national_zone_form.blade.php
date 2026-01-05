{{--
    Partial: Form notifica per ADMIN ZONA - Gare Nazionali (Osservatori)
    Destinatari: TO campionati@federgolf.it, CC CRC (national_admin) + osservatori
    Allegati: Nessuno
--}}

@php
    // Filtra solo gli osservatori
    $observers = $assignedReferees->filter(fn($ref) => $ref->pivot->role === 'Osservatore');

    // Prepara lista osservatori per il template
    $observersList = $observers->map(function($ref) {
        return "- {$ref->name}";
    })->implode("\n");

    // Template fisso per Admin Zona
    $defaultMessage = "Si comunicano di seguito i nominativi degli arbitri designati alla gara in oggetto nel ruolo di Osservatori:

{$observersList}

Torneo: {$tournament->name}
Date: {$tournament->start_date->format('d/m/Y')}" .
($tournament->start_date->format('d/m/Y') != $tournament->end_date->format('d/m/Y') ? " - {$tournament->end_date->format('d/m/Y')}" : "") . "
Circolo: {$tournament->club->name}

Cordiali saluti";

    // CRC / National Admins
    $nationalAdmins = \App\Models\User::where('user_type', 'national_admin')
        ->where('is_active', true)
        ->get();

    // Notifica ZONA esistente (mia)
    $myNotification = \App\Models\TournamentNotification::where('tournament_id', $tournament->id)
        ->where('notification_type', 'zone_observers')
        ->first();

    // Notifica CRC (controparte)
    $counterpartNotification = \App\Models\TournamentNotification::where('tournament_id', $tournament->id)
        ->where('notification_type', 'crc_referees')
        ->first();
@endphp

<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6">

        {{-- Stato notifiche per questa gara --}}
        <div class="mb-6 grid grid-cols-2 gap-4">
            {{-- Notifica controparte (CRC) --}}
            <div class="p-3 rounded-lg {{ $counterpartNotification && $counterpartNotification->sent_at ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200' }}">
                <div class="flex items-center">
                    @if($counterpartNotification && $counterpartNotification->sent_at)
                        <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                        <span class="text-sm font-medium text-green-800">Arbitri (CRC): Inviata</span>
                    @else
                        <span class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></span>
                        <span class="text-sm font-medium text-yellow-800">Arbitri (CRC): In attesa</span>
                    @endif
                </div>
                @if($counterpartNotification && $counterpartNotification->sent_at)
                    <p class="text-xs text-green-600 mt-1">{{ $counterpartNotification->sent_at->format('d/m/Y H:i') }}</p>
                @endif
            </div>

            {{-- Mia notifica (ZONA) --}}
            <div class="p-3 rounded-lg {{ $myNotification && $myNotification->sent_at ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200' }}">
                <div class="flex items-center">
                    @if($myNotification && $myNotification->sent_at)
                        <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                        <span class="text-sm font-medium text-green-800">Osservatori: Inviata</span>
                    @else
                        <span class="w-3 h-3 bg-gray-400 rounded-full mr-2"></span>
                        <span class="text-sm font-medium text-gray-600">Osservatori: Da inviare</span>
                    @endif
                </div>
                @if($myNotification && $myNotification->sent_at)
                    <p class="text-xs text-green-600 mt-1">{{ $myNotification->sent_at->format('d/m/Y H:i') }}</p>
                @endif
            </div>
        </div>

        {{-- Banner informativo --}}
        <div class="mb-6 p-4 bg-purple-50 border-l-4 border-purple-500 rounded">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="text-sm text-purple-800">
                    <p class="font-medium">Notifica Gara Nazionale - Designazione Osservatori</p>
                    <p class="mt-1">Questa notifica comunica i nominativi degli osservatori designati al Comitato Campionati, con copia al CRC e agli osservatori interessati.</p>
                </div>
            </div>
        </div>

        {{-- Verifica presenza osservatori --}}
        @if($observers->isEmpty())
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            <strong>Nessun osservatore assegnato.</strong> Non ci sono arbitri con ruolo "Osservatore" per questa gara.
                        </p>
                        <div class="mt-3">
                            <a href="{{ route('admin.assignments.assign-referees', $tournament) }}"
                               class="inline-flex items-center px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-md transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Assegna Osservatori
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.tournaments.send-national-notification', $tournament) }}" class="space-y-6">
            @csrf
            <input type="hidden" name="notification_type" value="zone_observers">

            {{-- Oggetto Email --}}
            <div>
                <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                    Oggetto Email <span class="text-red-500">*</span>
                </label>
                <input type="text" name="subject" id="subject"
                    value="{{ old('subject', 'Designazione Osservatori - ' . $tournament->name) }}"
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
                <p class="mt-1 text-xs text-gray-500">Il messaggio include automaticamente la lista degli osservatori designati.</p>
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

                {{-- CRC / National Admins --}}
                <div class="mb-4">
                    <p class="text-xs text-gray-600 mb-2 font-medium">CRC (Commissione Regole e Competizioni):</p>
                    @if($nationalAdmins->count() > 0)
                        <div class="space-y-1">
                            @foreach($nationalAdmins as $nationalAdmin)
                                <div class="flex items-center">
                                    <input type="checkbox" name="cc_national_admins[]" value="{{ $nationalAdmin->id }}"
                                        id="national_admin_{{ $nationalAdmin->id }}" checked
                                        class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    <label for="national_admin_{{ $nationalAdmin->id }}" class="ml-2 text-sm text-gray-700">
                                        {{ $nationalAdmin->name }} ({{ $nationalAdmin->email }})
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-500 italic">Nessun admin nazionale configurato</p>
                    @endif

                    {{-- Email CRC istituzionale --}}
                    <div class="flex items-center mt-2">
                        <input type="checkbox" name="send_to_crc" id="send_to_crc" value="1" checked
                            class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                        <label for="send_to_crc" class="ml-2 text-sm text-gray-700">
                            <span class="font-medium">Email CRC</span>
                            <span class="text-gray-500">({{ config('golf.emails.crc', 'crc@federgolf.it') }})</span>
                        </label>
                    </div>
                </div>

                {{-- Osservatori --}}
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <p class="text-xs text-gray-600 font-medium">Osservatori designati:</p>
                        <a href="{{ route('admin.assignments.assign-referees', $tournament) }}"
                           class="text-xs text-blue-600 hover:text-blue-800 hover:underline">
                            + Gestisci osservatori
                        </a>
                    </div>
                    @if($observers->count() > 0)
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            @foreach($observers as $observer)
                                <div class="flex items-center">
                                    <input type="checkbox" name="cc_observers[]" value="{{ $observer->id }}"
                                        id="observer_{{ $observer->id }}" checked
                                        class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    <label for="observer_{{ $observer->id }}" class="ml-2 text-sm text-gray-700">
                                        <span class="font-medium">{{ $observer->name }}</span>
                                        <span class="text-xs text-gray-400">- {{ $observer->email }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-500 italic">Nessun osservatore assegnato</p>
                    @endif
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
                    <button type="button" onclick="showNationalPreview('zone')"
                        class="w-full sm:w-auto px-5 py-2 bg-gray-100 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-200 flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Anteprima
                    </button>

                    {{-- Send Now Button --}}
                    <button type="submit" onclick="return confirmNationalSend()"
                        class="w-full sm:w-auto px-5 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center justify-center"
                        @if($observers->isEmpty()) disabled class="opacity-50 cursor-not-allowed" @endif>
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
