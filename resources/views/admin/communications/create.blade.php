{{-- FILE: resources/views/admin/communications/create.blade.php --}}
@extends('layouts.admin')

@section('title', 'Nuova Comunicazione')

@section('content')
<div class="p-6">
    <div class="max-w-4xl mx-auto">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex items-center gap-4 mb-2">
                <a href="{{ route('admin.communications.index') }}"
                   class="text-gray-600 hover:text-gray-900 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-gray-900">📢 Nuova Comunicazione</h1>
            </div>
            <p class="text-gray-600">Crea una nuova comunicazione per il sistema</p>
        </div>

        {{-- Form Card --}}
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Dettagli Comunicazione</h2>
            </div>

            <form method="POST" action="{{ route('admin.communications.store') }}" class="p-6">
                @csrf

                {{-- Titolo --}}
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Titolo *
                    </label>
                    <input type="text"
                           name="title"
                           value="{{ old('title') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('title') border-red-500 @enderror"
                           placeholder="Inserisci il titolo della comunicazione"
                           required>
                    @error('title')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Riga: Tipo, Priorità, Stato --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Tipo *
                        </label>
                        <select name="type"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('type') border-red-500 @enderror"
                                required>
                            <option value="">Seleziona tipo...</option>
                            <option value="announcement" {{ old('type') === 'announcement' ? 'selected' : '' }}>📢 Annuncio</option>
                            <option value="alert" {{ old('type') === 'alert' ? 'selected' : '' }}>⚠️ Avviso Importante</option>
                            <option value="maintenance" {{ old('type') === 'maintenance' ? 'selected' : '' }}>🔧 Manutenzione</option>
                            <option value="info" {{ old('type') === 'info' ? 'selected' : '' }}>ℹ️ Informazione</option>
                        </select>
                        @error('type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Priorità *
                        </label>
                        <select name="priority"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('priority') border-red-500 @enderror"
                                required>
                            <option value="normal" {{ old('priority') === 'normal' ? 'selected' : '' }}>Normale</option>
                            <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Bassa</option>
                            <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>Alta</option>
                            <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>Urgente</option>
                        </select>
                        @error('priority')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Stato *
                        </label>
                        <select name="status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('status') border-red-500 @enderror"
                                required>
                            <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>📝 Bozza</option>
                            <option value="published" {{ old('status') === 'published' ? 'selected' : '' }}>✅ Pubblica subito</option>
                        </select>
                        @error('status')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Zona (solo per national/super admin) --}}
                @if(auth()->user()->user_type === 'national_admin' || auth()->user()->user_type === 'super_admin')
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Zona di destinazione
                    </label>
                    <select name="zone_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('zone_id') border-red-500 @enderror">
                        <option value="">🌍 Tutte le zone (Comunicazione globale)</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}" {{ old('zone_id') == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('zone_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">
                        Se non specifichi una zona, la comunicazione sarà visibile a tutti gli utenti del sistema
                    </p>
                </div>
                @endif

                {{-- Contenuto --}}
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Contenuto *
                    </label>
                    <textarea name="content"
                              rows="12"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('content') border-red-500 @enderror"
                              placeholder="Scrivi qui il contenuto della comunicazione..."
                              required>{{ old('content') }}</textarea>
                    @error('content')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">
                        Puoi utilizzare HTML base per la formattazione (grassetto, corsivo, elenchi, ecc.)
                    </p>
                </div>

                {{-- Programmazione temporale --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            📅 Programmazione (opzionale)
                        </label>
                        <input type="datetime-local"
                               name="scheduled_at"
                               value="{{ old('scheduled_at') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('scheduled_at') border-red-500 @enderror">
                        @error('scheduled_at')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-sm text-gray-500">
                            Lascia vuoto per pubblicare immediatamente (se stato = pubblicato)
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ⏰ Scadenza (opzionale)
                        </label>
                        <input type="datetime-local"
                               name="expires_at"
                               value="{{ old('expires_at') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('expires_at') border-red-500 @enderror">
                        @error('expires_at')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-sm text-gray-500">
                            Dopo questa data la comunicazione non sarà più visibile
                        </p>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.communications.index') }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Annulla
                    </a>

                    <div class="flex gap-3">
                        {{-- Salva Bozza --}}
                        <button type="button"
                                onclick="document.querySelector('select[name=status]').value='draft'; this.form.submit();"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                            Salva Bozza
                        </button>

                        {{-- Salva e Pubblica --}}
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            Salva Comunicazione
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Help Card --}}
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">
                        💡 Suggerimenti per una buona comunicazione
                    </h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <ul class="list-disc pl-5 space-y-1">
                            <li><strong>Titolo chiaro:</strong> Usa un titolo descrittivo e conciso</li>
                            <li><strong>Tipo appropriato:</strong> Scegli il tipo giusto per far risaltare la comunicazione</li>
                            <li><strong>Priorità corretta:</strong> Usa "Urgente" solo per comunicazioni davvero importanti</li>
                            <li><strong>Contenuto strutturato:</strong> Organizza il testo con paragrafi e elenchi</li>
                            <li><strong>Programmazione:</strong> Usa la programmazione per comunicazioni future</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-resize textarea
    const textarea = document.querySelector('textarea[name="content"]');
    if (textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    }

    // Preview area for content (optional enhancement)
    const contentTextarea = document.querySelector('textarea[name="content"]');
    if (contentTextarea) {
        let timeoutId;
        contentTextarea.addEventListener('input', function() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                // Could add live preview here if needed
                console.log('Content updated');
            }, 500);
        });
    }
});
</script>
@endpush
