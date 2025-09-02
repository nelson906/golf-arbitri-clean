@extends('layouts.admin')

@section('title', 'Gestione Notifiche Tornei')

@section('content')
    <div class="container mx-auto px-4 py-6">
        <h1 class="text-2xl font-bold mb-6">📧 Notifiche Tornei</h1>

        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">
                            Torneo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data Preparazione</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destinatari</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stato</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Azioni</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Documenti</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach ($tournamentNotifications as $notification)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap w-1/4">
                                <div class="text-sm font-medium text-gray-900 truncate max-w-xs">
                                    {{ $notification->tournament->name }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $notification->tournament->start_date->format('d/m/Y') }}
                                </div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $notification->created_at->format('d/m/Y H:i') }}
                            </td>

                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 max-w-xs">
                                    {{-- Box con wrap per nomi arbitri --}}
                                    <div class="bg-gray-100 p-2 rounded text-xs break-words">
                                        {{ $notification->referee_list ?? 'Nessun arbitro' }}
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Totale: {{ $notification->total_recipients }} destinatari
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                            {{ $notification->status === 'sent' ? 'bg-green-100 text-green-800' : '' }}
                            {{ $notification->status === 'draft' ? 'bg-gray-100 text-gray-800' : '' }}
                            {{ $notification->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}">
                                    {{ $notification->status === 'draft' ? 'Bozza' : ucfirst($notification->status) }}
                                </span>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <div class="flex justify-center space-x-2">
                                    {{-- Invio (solo per draft) --}}
                                    @if ($notification->status === 'pending')
                                        <form action="{{ route('admin.tournament-notifications.send', $notification) }}"
                                            method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-blue-600 hover:text-blue-900"
                                                onclick="return confirm('Inviare le notifiche?')">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Reinvio (per sent/failed) --}}
                                    @if ($notification->status === 'sent' || $notification->status === 'failed')
                                        <form
                                            action="{{ route('admin.tournament-notifications.resend', $notification->id) }}"
                                            method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-amber-600 hover:text-amber-900"
                                                onclick="return confirm('Reinviare le notifiche?')">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                                    </path>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Edit --}}
                                    <a href="{{ route('admin.tournament-notifications.edit', $notification->id) }}"
                                        class="text-indigo-600 hover:text-indigo-900">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                            </path>
                                        </svg>
                                    </a>

                                    {{-- Show --}}
                                    <a href="{{ route('admin.tournament-notifications.show', $notification) }}"
                                        class="text-gray-600 hover:text-gray-900">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                            </path>
                                        </svg>
                                    </a>

                                    {{-- Delete --}}
                                    <form action="{{ route('admin.tournament-notifications.destroy', $notification) }}"
                                        method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900"
                                            onclick="return confirm('Eliminare questa notifica?')">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @php
                                    $attachments = is_string($notification->attachments)
                                        ? json_decode($notification->attachments, true)
                                        : $notification->attachments;
                                    $attachments = $attachments ?? [];

                                    $documentCount = 0;
                                    if (isset($attachments['convocation'])) {
                                        $documentCount++;
                                    }
                                    if (isset($attachments['club_letter']) || isset($attachments['club'])) {
                                        $documentCount++;
                                    }
                                @endphp

                                <div class="flex space-x-2">
                                    {{-- GESTIONE DOCUMENTI --}}
                                    <button onclick="openDocumentManager({{ $notification->id }})"
                                        class="inline-flex items-center px-3 py-1 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Gestisci
                                        <span class="ml-1 bg-green-100 text-green-800 rounded-full px-2 py-0.5 text-xs">
                                            {{ $documentCount }}
                                        </span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $tournamentNotifications->links() }}
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
        function openDocumentManager(notificationId) {
            const content = document.getElementById('documentManagerContent');
            content.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin text-2xl"></i></div>';

            openModal('documentManagerModal');

            // La fetch è qui, nella posizione corretta
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
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/admin/tournament-notifications/${notificationId}/generate/${type}`;

                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = '{{ csrf_token() }}';
                form.appendChild(token);

                document.body.appendChild(form);
                form.submit();
            }
        }

        // Rigenera documento
        function regenerateDocument(notificationId, type) {
            if (confirm('Rigenerare il documento? Questo sovrascriverà il file esistente.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/admin/tournament-notifications/${notificationId}/regenerate/${type}`;

                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = '{{ csrf_token() }}';
                form.appendChild(token);

                document.body.appendChild(form);
                form.submit();
            }
        }

        // Elimina documento
        function deleteDocument(notificationId, type) {
            if (confirm('Eliminare il documento?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/admin/tournament-notifications/${notificationId}/document/${type}`;

                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = '{{ csrf_token() }}';
                form.appendChild(token);

                const method = document.createElement('input');
                method.type = 'hidden';
                method.name = '_method';
                method.value = 'DELETE';
                form.appendChild(method);

                document.body.appendChild(form);
                form.submit();
            }
        }

        // Chiudi modal cliccando fuori
        window.onclick = function(event) {
            const modals = ['documentManagerModal', 'uploadDocumentModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        }
        // Chiudi modal cliccando fuori
        window.onclick = function(event) {
            const modals = ['documentManagerModal', 'uploadDocumentModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        }

        // AGGIUNGI QUI IL REFRESH DOPO UPLOAD
        document.addEventListener('DOMContentLoaded', function() {
            const uploadForm = document.getElementById('uploadDocumentForm');
            if (uploadForm) {
                uploadForm.addEventListener('submit', function() {
                    setTimeout(() => {
                        window.location.reload();
                    }, 2500);
                });
            }
        });
    </script>
@endsection
