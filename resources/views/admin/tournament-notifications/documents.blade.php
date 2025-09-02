// resources/views/admin/tournament-notifications/documents.blade.php

<div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-semibold mb-4">📄 Documenti Allegati</h3>

    {{-- Documenti Circolo --}}
    <div class="mb-6">
        <h4 class="font-medium text-gray-700 mb-2">Circolo</h4>
        <div class="space-y-2">
            @if($notification->attachments['club']['facsimile'] ?? false)
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-blue-600 mr-2"><!-- Word icon --></svg>
                        <span>Facsimile Convocazione</span>
                    </div>
                    <div class="flex space-x-2">
                        <a href="{{ route('admin.tournament-documents.download', [
                            $notification, 'club', $notification->attachments['club']['facsimile']
                        ]) }}" class="text-blue-600 hover:underline">
                            Download
                        </a>
                        <button onclick="showUploadModal('facsimile')"
                                class="text-green-600 hover:underline">
                            Sostituisci
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Documenti Arbitri --}}
    <div>
        <h4 class="font-medium text-gray-700 mb-2">Convocazioni Arbitri</h4>
        <div class="space-y-2">
            @foreach($notification->attachments['referees'] ?? [] as $name => $file)
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-red-600 mr-2"><!-- PDF icon --></svg>
                        <span>{{ str_replace('_', ' ', $name) }}</span>
                    </div>
                    <a href="{{ route('admin.tournament-documents.download', [
                        $notification, 'referee', $file
                    ]) }}" class="text-blue-600 hover:underline">
                        Download
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Modal Upload --}}
<div id="uploadModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75">
    <div class="flex items-center justify-center min-h-screen">
        <div class="bg-white rounded-lg p-6 max-w-md w-full">
            <h3 class="text-lg font-semibold mb-4">Carica Nuovo Documento</h3>
            <form action="{{ route('admin.tournament-documents.upload', $notification) }}"
                  method="POST"
                  enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="type" id="uploadType">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Seleziona file Word (.doc, .docx)
                    </label>
                    <input type="file" name="document" accept=".doc,.docx" required
                           class="w-full border rounded p-2">
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideUploadModal()"
                            class="px-4 py-2 border rounded">
                        Annulla
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded">
                        Carica
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
