@extends('layouts.admin')

@section('title', 'Dettagli Notifiche - ' . $tournamentNotification->tournament->name)

@section('content')
    {{-- @php
        dump($tournamentNotification->toArray());
    @endphp --}}

    <div class="container mx-auto px-4">
        <!-- 🏆 Header Torneo e Statistiche -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="bg-blue-600 text-white px-6 py-4 rounded-t-lg">
                        <h4 class="text-xl font-semibold mb-0">🏆 {{ $tournamentNotification->tournament->name }}</h4>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <ul class="space-y-2">
                                    <li><strong>📅 Date Torneo:</strong>
                                        {{ $tournamentNotification->tournament->start_date->format('d/m/Y') }} -
                                        {{ $tournamentNotification->tournament->end_date->format('d/m/Y') }}</li>
                                    <li><strong>🏌️ Circolo:</strong>
                                        {{ $tournamentNotification->tournament->club->name ?? 'N/A' }}</li>
                                    <li><strong>🌍 Zona:</strong> {{ $tournamentNotification->tournament->zone->name }}</li>
                                    <li><strong>📊 Stato Torneo:</strong>
                                        <span
                                            class="inline-flex px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 rounded-full">{{ $tournamentNotification->tournament->status }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <ul class="space-y-2">
                                    <li><strong>📧 Notifiche Inviate:</strong>
                                        {{ $tournamentNotification->sent_at ? $tournamentNotification->sent_at->format('d/m/Y H:i') : 'Mai inviate' }}
                                    </li>
                                    <li><strong>👤 Inviato da:</strong>
                                        {{ $tournamentNotification->sentBy ? $tournamentNotification->sentBy->name : 'Sistema' }}
                                    </li>
                                    <li><strong>⏰ Tempo trascorso:</strong> {{ $tournamentNotification->time_ago }}</li>
                                    <li><strong>📊 Stato Notifiche:</strong>
                                        <span
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $tournamentNotification->status === 'sent' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $tournamentNotification->status === 'partial' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        {{ $tournamentNotification->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}">
                                            {{ $tournamentNotification->status_formatted }}
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h5 class="text-lg font-semibold mb-0">📌 Status Invio</h5>
                    </div>
                    <div class="p-6">
                        <div class="flex flex-col space-y-4">
                            <!-- Stato -->
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Stato:</span>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    {{ $tournamentNotification->status === 'sent' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $tournamentNotification->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $tournamentNotification->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}">
                                    {{ $tournamentNotification->status_formatted }}
                                </span>
                            </div>

                            <!-- Data Invio -->
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Ultimo invio:</span>
                                <span class="text-sm">
                                    {{ $tournamentNotification->sent_at ? $tournamentNotification->sent_at->format('d/m/Y H:i') : 'Mai inviato' }}
                                </span>
                            </div>

                            <!-- Errori se presenti -->
                            @if($tournamentNotification->status === 'failed' && !empty($tournamentNotification->metadata['last_error']))
                                <div class="mt-4 p-3 bg-red-50 rounded-lg">
                                    <p class="text-sm text-red-600">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        {{ $tournamentNotification->metadata['last_error'] }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 📊 Breakdown per Tipo Destinatario -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div>
                <div class="bg-white rounded-lg shadow-md">
                    <div class="px-6 py-3 bg-gray-50 rounded-t-lg">
                        <h6 class="text-sm font-semibold mb-0">🏌️ Circolo</h6>
                    </div>
                    <div class="p-6 text-center">
                        @php $clubStats = $tournamentNotification->stats @endphp
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <h4 class="text-xl font-bold text-green-600">{{ $clubStats['club_sent'] }}</h4>
                                <p class="text-sm text-gray-600">Inviati</p>
                            </div>
                            <div>
                                <h4 class="text-xl font-bold text-red-600">{{ $clubStats['club_failed'] }}</h4>
                                <p class="text-sm text-gray-600">Falliti</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="bg-white rounded-lg shadow-md">
                    <div class="px-6 py-3 bg-gray-50 rounded-t-lg">
                        <h6 class="text-sm font-semibold mb-0">⚖️ Arbitri</h6>
                    </div>
                    <div class="p-6 text-center">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <h4 class="text-xl font-bold text-green-600">{{ $clubStats['referees_sent'] }}</h4>
                                <p class="text-sm text-gray-600">Inviati</p>
                            </div>
                            <div>
                                <h4 class="text-xl font-bold text-red-600">{{ $clubStats['referees_failed'] }}</h4>
                                <p class="text-sm text-gray-600">Falliti</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="bg-white rounded-lg shadow-md">
                    <div class="px-6 py-3 bg-gray-50 rounded-t-lg">
                        <h6 class="text-sm font-semibold mb-0">🏛️ Istituzionali</h6>
                    </div>
                    <div class="p-6 text-center">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <h4 class="text-xl font-bold text-green-600">{{ $clubStats['institutional_sent'] }}</h4>
                                <p class="text-sm text-gray-600">Inviati</p>
                            </div>
                            <div>
                                <h4 class="text-xl font-bold text-red-600">{{ $clubStats['institutional_failed'] }}</h4>
                                <p class="text-sm text-gray-600">Falliti</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 📋 Destinatari e Documenti -->
        <div class="bg-white rounded-lg shadow-md mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="text-lg font-semibold mb-0">📋 Destinatari e Documenti</h5>
            </div>
            @php
                $t = $tournamentNotification->tournament;
                $zoneId = $t->club->zone_id ?? $t->zone_id;
                $zoneFolder = ($t->tournamentType && $t->tournamentType->is_national) ? 'CRC' : ('SZR' . ($zoneId ?? ''));
                $docs = is_array($tournamentNotification->documents) ? $tournamentNotification->documents : (json_decode($tournamentNotification->documents ?? '[]', true) ?? []);
            @endphp
            <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- 🏌️ Circolo -->
                <div class="bg-blue-50 rounded-lg p-4">
                    <h6 class="font-semibold mb-3 flex items-center">
                        <span class="text-blue-600">🏌️</span>
                        <span class="ml-2">Circolo</span>
                    </h6>
                    <div class="space-y-2">
                        <p class="text-sm">{{ $t->club->email }}</p>
                        @if(!empty($docs['club_letter']))
                            <a href="{{ Storage::url('convocazioni/' . $zoneFolder . '/generated/' . $docs['club_letter']) }}" 
                               class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                                <i class="fas fa-file-word mr-1"></i>
                                Lettera Circolo
                            </a>
                        @endif
                    </div>
                </div>

                <!-- ⚖️ Arbitri -->
                <div class="bg-green-50 rounded-lg p-4">
                    <h6 class="font-semibold mb-3 flex items-center">
                        <span class="text-green-600">⚖️</span>
                        <span class="ml-2">Arbitri</span>
                    </h6>
                    <div class="space-y-2">
                        @foreach($t->assignments->groupBy('role') as $role => $assignments)
                            <div class="mb-2">
                                <p class="text-sm font-medium">{{ $role }}:</p>
                                @foreach($assignments as $assignment)
                                    <p class="text-sm pl-3">- {{ $assignment->user->name }}</p>
                                @endforeach
                            </div>
                        @endforeach
                        @if(!empty($docs['convocation']))
                            <a href="{{ Storage::url('convocazioni/' . $zoneFolder . '/generated/' . $docs['convocation']) }}" 
                               class="text-sm text-green-600 hover:text-green-800 flex items-center">
                                <i class="fas fa-file-word mr-1"></i>
                                Convocazione
                            </a>
                        @endif
                    </div>
                </div>

                <!-- 🏛️ Istituzionali -->
                <div class="bg-yellow-50 rounded-lg p-4">
                    <h6 class="font-semibold mb-3 flex items-center">
                        <span class="text-yellow-600">🏛️</span>
                        <span class="ml-2">Istituzionali</span>
                    </h6>
                    <div class="space-y-2">
                        <p class="text-sm">SZR {{ $t->zone->name ?? '' }}</p>
                        <p class="text-sm">Ufficio Campionati</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ⚡ Azioni -->
        <div class="bg-white rounded-lg shadow-md mt-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="text-lg font-semibold mb-0">⚡ Azioni</h5>
            </div>
            <div class="p-6">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="{{ route('admin.tournament-notifications.index') }}"
                            class="inline-flex items-center px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors duration-200">
                            <i class="fas fa-arrow-left mr-2"></i> Torna alla Lista
                        </a>

                        <a href="{{ route('tournaments.show', $tournamentNotification->tournament) }}"
                            class="inline-flex items-center px-4 py-2 border border-blue-500 text-blue-500 hover:bg-blue-50 rounded-lg transition-colors duration-200">
                            <i class="fas fa-trophy mr-2"></i> Visualizza Torneo
                        </a>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4">
                        @if ($tournamentNotification->canBeResent())
                            <button type="button"
                                class="inline-flex items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg transition-colors duration-200"
                                onclick="resendTournament()">
                                <i class="fas fa-redo mr-2"></i>
                                {{ $tournamentNotification->sent_at ? 'Reinvia Notifiche' : 'Invia Notifiche' }}
                            </button>
                        @endif

                        <form method="POST"
                            action="{{ route('admin.tournament-notifications.destroy', $tournamentNotification) }}"
                            class="inline-block"
                            onsubmit="return confirm('Eliminare tutte le notifiche per questo torneo?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-red-500 text-red-500 hover:bg-red-50 rounded-lg transition-colors duration-200">
                                <i class="fas fa-trash mr-2"></i> Elimina
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 📧 Modal Contenuto Notifica -->
    <div id="contentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center pb-3">
                <h5 class="text-lg font-bold">📧 Contenuto Notifica</h5>
                <button type="button"
                    class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center"
                    onclick="closeModal('contentModal')">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
            <div id="notification-content" class="py-4">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin"></i> Caricamento...
                </div>
            </div>
            <div class="flex justify-end pt-2">
                <button type="button" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg"
                    onclick="closeModal('contentModal')">Chiudi</button>
            </div>
        </div>
    </div>

    <!-- 📎 Modal Allegati -->
    <div id="attachmentsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-md shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center pb-3">
                <h5 class="text-lg font-bold">📎 Allegati</h5>
                <button type="button"
                    class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center"
                    onclick="closeModal('attachmentsModal')">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
            <div id="attachments-content" class="py-4">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin"></i> Caricamento...
                </div>
            </div>
            <div class="flex justify-end pt-2">
                <button type="button" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg"
                    onclick="closeModal('attachmentsModal')">Chiudi</button>
            </div>
        </div>
    </div>

    <!-- 🔄 Modal Reinvio -->
    <div id="resendModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-md shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center pb-3">
                <h5 class="text-lg font-bold">{{ $tournamentNotification->sent_at ? '🔄 Reinvia Notifiche' : '✉️ Invia Notifiche' }}</h5>
                <button type="button"
                    class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center"
                    onclick="closeModal('resendModal')">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
            <div class="py-4">
                <p class="mb-4">Sei sicuro di voler reinviare tutte le notifiche per questo torneo?</p>
                <p class="text-yellow-600 flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Questo sostituirà le notifiche precedenti e invierà nuovamente tutte le email.
                </p>
            </div>
            <div class="flex justify-end space-x-4 pt-2">
                <button type="button" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg"
                    onclick="closeModal('resendModal')">Annulla</button>
                <form method="POST"
                    action="{{ route('admin.tournament-notifications.resend', $tournamentNotification) }}"
                    class="inline-block">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg">
                        <i class="fas fa-redo mr-2"></i> Reinvia
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        /**
         * 🔧 Utility function per aprire/chiudere modal
         */
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        /**
         * 👁️ Mostra contenuto notifica
         */
        function showNotificationContent(notificationId) {
            const content = document.getElementById('notification-content');

            // Simula caricamento contenuto (sostituire con chiamata AJAX reale)
            content.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Caricamento...</div>';
            openModal('contentModal');

            // Simula dati (da sostituire con chiamata AJAX)
            setTimeout(() => {
                content.innerHTML = `
            <div class="mb-4">
                <strong class="text-gray-700">Oggetto:</strong><br>
                <div class="p-3 bg-gray-100 border rounded-lg mt-2">Convocazione Ufficiale - Torneo Test</div>
            </div>
            <div>
                <strong class="text-gray-700">Corpo del messaggio:</strong><br>
                <div class="p-4 bg-gray-100 border rounded-lg mt-2 whitespace-pre-line">Gentile Mario Rossi,

È ufficialmente convocato come Arbitro per:

**Torneo Test**
Date: 15/08/2025 - 17/08/2025
Circolo: Golf Club Roma

La convocazione ufficiale è in allegato.

Cordiali saluti,
Sezione Zonale Regole</div>
            </div>
        `;
            }, 500);
        }

        /**
         * 📎 Mostra allegati
         */
        function showAttachments(notificationId) {
            const content = document.getElementById('attachments-content');

            content.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Caricamento...</div>';
            openModal('attachmentsModal');

            // Simula dati allegati
            setTimeout(() => {
                content.innerHTML = `
            <div class="space-y-3">
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <div>
                        <div class="flex items-center">
                            <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                            <span class="font-medium">Convocazione_Ufficiale.pdf</span>
                        </div>
                        <div class="text-sm text-gray-500 mt-1">245 KB</div>
                    </div>
                    <a href="#" class="px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white text-sm rounded-lg transition-colors duration-200">
                        <i class="fas fa-download mr-1"></i> Scarica
                    </a>
                </div>
            </div>
        `;
            }, 500);
        }

        /**
         * 🔄 Reinvia singola notifica
         */
        function resendSingle(notificationId) {
            if (confirm('Reinviare questa notifica?')) {
                // Implementare chiamata AJAX per reinvio singolo
                alert('Funzione da implementare: reinvio singola notifica');
            }
        }

        /**
         * 🔄 Reinvia tutte le notifiche del torneo
         */
        function resendTournament() {
            openModal('resendModal');
        }

        // Chiudi modal quando si clicca fuori
        window.onclick = function(event) {
            const modals = ['contentModal', 'attachmentsModal', 'resendModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        }
    </script>
@endpush

@push('styles')
    <style>
        /* Custom styles for responsive behavior */
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.85rem;
            }
        }
    </style>
@endpush
