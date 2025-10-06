{{-- Document Manager Modal --}}
<div id="documentManagerModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center pb-3 border-b">
            <h5 class="text-xl font-bold">📄 Gestione Documenti</h5>
            <button type="button" onclick="closeModal('documentManagerModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </button>
        </div>

        <div id="documentManagerContent" class="py-4">
            {{-- Content loaded dynamically --}}
        </div>

        <div class="pt-4 border-t mt-4">
            <button type="button" onclick="closeModal('documentManagerModal')" class="w-full sm:w-auto px-6 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                Chiudi
            </button>
        </div>
    </div>
</div>

<script>
function uploadDocument(notificationId, type, file) {
    if (!file) return;
    
    const content = document.getElementById('documentManagerContent');
    content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-orange-500"></i><p class="mt-4">Caricamento in corso...</p></div>';

    const formData = new FormData();
    formData.append('document', file);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

    fetch(`/admin/tournament-notifications/${notificationId}/upload/${type}`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Errore nel caricamento del file');
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.status) {
            content.innerHTML = buildContent(data.status);
        } else {
            throw new Error(data.message || 'Errore nel caricamento del file');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Errore: ' + error.message);
        openDocumentManager(notificationId);
    });
}

function openDocumentManagerModal(tournamentId) {
    fetch(`/admin/tournament-notifications/find-by-tournament/${tournamentId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
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
    content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-blue-500"></i><p class="mt-4">Caricamento in corso...</p></div>';

    openModal('documentManagerModal');

    fetch(`/admin/tournament-notifications/${notificationId}/documents-status`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            content.innerHTML = buildContent(data);
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = '<div class="text-center py-8 text-red-600">Errore nel caricamento dei documenti</div>';
        });
}

function buildContent(data) {
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

                        <button onclick="generateDocument(${data.notification_id}, 'convocation')"
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            <i class="fas fa-redo mr-1"></i> Rigenera
                        </button>

                        <button onclick="deleteDocument(${data.notification_id}, 'convocation')"
                                class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                            <i class="fas fa-trash mr-1"></i> Elimina
                        </button>

                        <label class="bg-purple-600 text-white px-4 py-2 rounded text-center hover:bg-purple-700 cursor-pointer">
                            <i class="fas fa-upload mr-1"></i> Carica
                            <input type="file" class="hidden" onchange="uploadDocument(${data.notification_id}, 'convocation', this.files[0])" accept=".doc,.docx">
                        </label>
                    </div>
                </div>
            ` : `
                <div class="text-center py-8">
                    <i class="fas fa-file-excel text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500 mb-4">Nessun documento presente</p>
                    <div class="space-y-2">
                        <button onclick="generateDocument(${data.notification_id}, 'convocation')"
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full">
                            <i class="fas fa-plus mr-1"></i> Genera Convocazione
                        </button>
                        
                        <label class="bg-purple-600 text-white px-4 py-2 rounded text-center hover:bg-purple-700 cursor-pointer block">
                            <i class="fas fa-upload mr-1"></i> Carica File
                            <input type="file" class="hidden" onchange="uploadDocument(${data.notification_id}, 'convocation', this.files[0])" accept=".doc,.docx">
                        </label>
                    </div>
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

                        <button onclick="generateDocument(${data.notification_id}, 'club_letter')"
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            <i class="fas fa-redo mr-1"></i> Rigenera
                        </button>

                        <button onclick="deleteDocument(${data.notification_id}, 'club_letter')"
                                class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                            <i class="fas fa-trash mr-1"></i> Elimina
                        </button>

                        <label class="bg-purple-600 text-white px-4 py-2 rounded text-center hover:bg-purple-700 cursor-pointer">
                            <i class="fas fa-upload mr-1"></i> Carica
                            <input type="file" class="hidden" onchange="uploadDocument(${data.notification_id}, 'club_letter', this.files[0])" accept=".doc,.docx">
                        </label>
                    </div>
                </div>
            ` : `
                <div class="text-center py-8">
                    <i class="fas fa-file-excel text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500 mb-4">Nessun documento presente</p>
                    <div class="space-y-2">
                        <button onclick="generateDocument(${data.notification_id}, 'club_letter')"
                                class="bg-green-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full">
                            <i class="fas fa-plus mr-1"></i> Genera Lettera
                        </button>
                        
                        <label class="bg-purple-600 text-white px-4 py-2 rounded text-center hover:bg-purple-700 cursor-pointer block">
                            <i class="fas fa-upload mr-1"></i> Carica File
                            <input type="file" class="hidden" onchange="uploadDocument(${data.notification_id}, 'club_letter', this.files[0])" accept=".doc,.docx">
                        </label>
                    </div>
                </div>
            `}
        </div>
    </div>`;
}

function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function generateDocument(notificationId, type) {
    if (!confirm('Generare il documento?')) return;

    const content = document.getElementById('documentManagerContent');
    content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-blue-500"></i><p class="mt-4">Generazione in corso...</p></div>';

    fetch(`/admin/tournament-notifications/${notificationId}/generate/${type}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Errore nella generazione del documento');
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.status) {
            content.innerHTML = buildContent(data.status);
        } else {
            throw new Error(data.message || 'Errore nella generazione del documento');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Errore: ' + error.message);
        openDocumentManager(notificationId);
    });
}

function deleteDocument(notificationId, type) {
    if (!confirm('Eliminare il documento?')) return;

    const content = document.getElementById('documentManagerContent');
    content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-red-500"></i><p class="mt-4">Eliminazione in corso...</p></div>';

    const formData = new FormData();
    formData.append('_method', 'DELETE');

    fetch(`/admin/tournament-notifications/${notificationId}/document/${type}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Errore nella risposta del server');
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.status) {
            content.innerHTML = buildContent(data.status);
        } else {
            throw new Error(data.message || 'Errore nell\'eliminazione del documento');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Errore: ' + error.message);
        openDocumentManager(notificationId);
    });
}
</script>