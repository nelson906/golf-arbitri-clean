@extends('layouts.admin')

@section('title', 'Modifica Letterhead')

@section('content')
    <div class="container mx-auto px-4 py-6">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center space-x-3 mb-2">
                        <a href="{{ route('admin.letterheads.index') }}" class="text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </a>
                        <h1 class="text-2xl font-bold text-gray-900">Modifica Letterhead</h1>
                    </div>
                    <nav class="flex text-sm text-gray-500" aria-label="Breadcrumb">
                        <a href="{{ route('admin.letterheads.index') }}" class="hover:text-gray-700">Letterheads</a>
                        <span class="mx-2">/</span>
                        <a href="{{ route('admin.letterheads.show', $letterhead) }}"
                            class="hover:text-gray-700">{{ Str::limit($letterhead->title, 30) }}</a>
                        <span class="mx-2">/</span>
                        <span class="text-gray-900">Modifica</span>
                    </nav>
                </div>
                <div class="flex space-x-2">
                    <a href="{{ route('admin.letterheads.preview', $letterhead) }}"
                        class="inline-flex items-center px-3 py-2 border border-blue-300 text-sm font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        Anteprima
                    </a>
                    <a href="{{ route('admin.letterheads.show', $letterhead) }}"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Indietro
                    </a>
                </div>
            </div>
        </div>

        {{-- Form --}}
        <div class="bg-white rounded-lg shadow p-6">
            <form method="POST" action="{{ route('admin.letterheads.update', $letterhead) }}" enctype="multipart/form-data"
                class="space-y-6">
                @csrf
                @method('PUT')

                {{-- Informazioni Generali --}}
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Informazioni Generali</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Titolo --}}
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Titolo *</label>
                            <input type="text" name="title" id="title"
                                value="{{ old('title', $letterhead->title) }}"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('title') border-red-500 @enderror"
                                required>
                            @error('title')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Zona --}}
                        <div>
                            <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                            <select name="zone_id" id="zone_id"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('zone_id') border-red-500 @enderror">
                                <option value="">Globale</option>
                                @foreach ($zones as $zone)
                                    <option value="{{ $zone->id }}"
                                        {{ old('zone_id', $letterhead->zone_id) == $zone->id ? 'selected' : '' }}>
                                        {{ $zone->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Lascia vuoto per una letterhead globale disponibile per
                                tutte le zone</p>
                            @error('zone_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Descrizione --}}
                    <div class="mt-6">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Descrizione</label>
                        <textarea name="description" id="description" rows="3"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror"
                            placeholder="Descrizione della letterhead...">{{ old('description', $letterhead->description) }}</textarea>
                        @error('description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Logo Upload --}}
                    <div class="mt-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Scegli file --}}
                            <div>
                                <label for="logo" class="block text-sm font-medium text-gray-700 mb-1">Scegli
                                    file</label>
                                <input type="file" name="logo" id="logo" accept="image/*"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('logo') border-red-500 @enderror">
                                <p class="mt-1 text-xs text-gray-500">JPG, PNG, SVG fino a 2MB.
                                    {{ $letterhead->logo_path ? 'Lascia vuoto per mantenere il logo attuale.' : '' }}</p>
                                @error('logo')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Logo attuale e preview --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                                @if ($letterhead->logo_path)
                                    <div class="mb-4 flex items-center space-x-4">
                                        <img src="{{ $letterhead->logo_url }}" alt="Logo attuale"
                                            class="h-16 w-auto object-contain border rounded">
                                        <div>
                                            <p class="text-sm text-gray-600">Logo attuale:
                                                {{ basename($letterhead->logo_path) }}</p>
                                            <button type="button" onclick="removeLogo()"
                                                class="text-sm text-red-600 hover:text-red-800">Rimuovi logo</button>
                                        </div>
                                    </div>
                                @endif
                                <div id="logo-preview" class="hidden">
                                    <img id="preview-image" src="" alt="Preview"
                                        class="max-h-16 max-w-24 object-contain border rounded">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Header e Footer --}}
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="header_text" class="block text-sm font-medium text-gray-700 mb-1">Testo
                                Header</label>
                            <textarea name="header_text" id="header_text" rows="4"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('header_text') border-red-500 @enderror"
                                placeholder="FEDERAZIONE ITALIANA GOLF&#10;Commissione Regole e Competizioni">{{ old('header_text', $letterhead->header_text) }}</textarea>
                            @error('header_text')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="footer_text" class="block text-sm font-medium text-gray-700 mb-1">Testo
                                Footer</label>
                            <textarea name="footer_text" id="footer_text" rows="4"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('footer_text') border-red-500 @enderror"
                                placeholder="Via Flaminia, 388 - 00196 Roma | Tel: +39 06 3685477 | Email: segreteria@federgolf.it">{{ old('footer_text', $letterhead->footer_text) }}</textarea>
                            @error('footer_text')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Informazioni di Contatto --}}
                <div class="border-t pt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Informazioni di Contatto</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="contact_address"
                                class="block text-sm font-medium text-gray-700 mb-1">Indirizzo</label>
                            <input type="text" name="contact_info[address]" id="contact_address"
                                value="{{ old('contact_info.address', $letterhead->contact_info['address'] ?? '') }}"
                                placeholder="Via Flaminia, 388 - 00196 Roma"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('contact_info.address') border-red-500 @enderror">
                            @error('contact_info.address')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="contact_phone"
                                class="block text-sm font-medium text-gray-700 mb-1">Telefono</label>
                            <input type="text" name="contact_info[phone]" id="contact_phone"
                                value="{{ old('contact_info.phone', $letterhead->contact_info['phone'] ?? '') }}"
                                placeholder="+39 06 3685477"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('contact_info.phone') border-red-500 @enderror">
                            @error('contact_info.phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="contact_info[email]" id="contact_email"
                                value="{{ old('contact_info.email', $letterhead->contact_info['email'] ?? '') }}"
                                placeholder="segreteria@federgolf.it"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('contact_info.email') border-red-500 @enderror">
                            @error('contact_info.email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="contact_website" class="block text-sm font-medium text-gray-700 mb-1">Sito
                                Web</label>
                            <input type="url" name="contact_info[website]" id="contact_website"
                                value="{{ old('contact_info.website', $letterhead->contact_info['website'] ?? '') }}"
                                placeholder="https://www.federgolf.it"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500 @error('contact_info.website') border-red-500 @enderror">
                            @error('contact_info.website')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Impostazioni --}}
                <div class="border-t pt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Impostazioni</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {{-- Stato --}}
                        <div class="space-y-4">
                            <div class="flex items-center">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" id="is_active" value="1"
                                    {{ old('is_active', $letterhead->is_active) ? 'checked' : '' }}
                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="is_active" class="ml-2 block text-sm text-gray-900">Attiva</label>
                            </div>

                            <div class="flex items-center">
                                <input type="hidden" name="is_default" value="0">
                                <input type="checkbox" name="is_default" id="is_default" value="1"
                                    {{ old('is_default', $letterhead->is_default) ? 'checked' : '' }}
                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="is_default" class="ml-2 block text-sm text-gray-900">Predefinita</label>
                            </div>
                            <p class="text-xs text-gray-500">Se selezionato, diventerà la letterhead predefinita per la
                                zona</p>
                        </div>

                        {{-- Font --}}
                        <div>
                            <label for="font_family" class="block text-sm font-medium text-gray-700 mb-1">Font</label>
                            <select name="settings[font][family]" id="font_family"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="Arial"
                                    {{ old('settings.font.family', $letterhead->settings['font']['family'] ?? 'Arial') === 'Arial' ? 'selected' : '' }}>
                                    Arial</option>
                                <option value="Times New Roman"
                                    {{ old('settings.font.family', $letterhead->settings['font']['family'] ?? '') === 'Times New Roman' ? 'selected' : '' }}>
                                    Times New Roman</option>
                                <option value="Helvetica"
                                    {{ old('settings.font.family', $letterhead->settings['font']['family'] ?? '') === 'Helvetica' ? 'selected' : '' }}>
                                    Helvetica</option>
                            </select>

                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <div>
                                    <label for="font_size" class="block text-xs text-gray-500">Dimensione</label>
                                    <input type="number" name="settings[font][size]" id="font_size" min="8"
                                        max="24"
                                        value="{{ old('settings.font.size', $letterhead->settings['font']['size'] ?? 11) }}"
                                        class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm">
                                </div>
                                <div>
                                    <label for="font_color" class="block text-xs text-gray-500">Colore</label>
                                    <input type="color" name="settings[font][color]" id="font_color"
                                        value="{{ old('settings.font.color', $letterhead->settings['font']['color'] ?? '#000000') }}"
                                        class="w-full border border-gray-300 rounded-md px-1 py-1">
                                </div>
                            </div>
                        </div>

                        {{-- Margini --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Margini (mm)</label>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label for="margin_top" class="block text-xs text-gray-500">Alto</label>
                                    <input type="number" name="settings[margins][top]" id="margin_top" min="0"
                                        max="100"
                                        value="{{ old('settings.margins.top', $letterhead->settings['margins']['top'] ?? 20) }}"
                                        class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm">
                                </div>
                                <div>
                                    <label for="margin_bottom" class="block text-xs text-gray-500">Basso</label>
                                    <input type="number" name="settings[margins][bottom]" id="margin_bottom"
                                        min="0" max="100"
                                        value="{{ old('settings.margins.bottom', $letterhead->settings['margins']['bottom'] ?? 20) }}"
                                        class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm">
                                </div>
                                <div>
                                    <label for="margin_left" class="block text-xs text-gray-500">Sinistra</label>
                                    <input type="number" name="settings[margins][left]" id="margin_left" min="0"
                                        max="100"
                                        value="{{ old('settings.margins.left', $letterhead->settings['margins']['left'] ?? 25) }}"
                                        class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm">
                                </div>
                                <div>
                                    <label for="margin_right" class="block text-xs text-gray-500">Destra</label>
                                    <input type="number" name="settings[margins][right]" id="margin_right"
                                        min="0" max="100"
                                        value="{{ old('settings.margins.right', $letterhead->settings['margins']['right'] ?? 25) }}"
                                        class="w-full border border-gray-300 rounded-md px-2 py-1 text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Azioni --}}
                <div class="flex justify-between pt-6 border-t">
                    <a href="{{ route('admin.letterheads.show', $letterhead) }}"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Annulla
                    </a>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        Aggiorna Letterhead
                    </button>
                </div>
            </form>
        </div>

        {{-- Modal per rimozione logo --}}
        <div id="removeLogoModal"
            class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Rimuovi Logo</h3>
                        <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="mb-4">
                        <p class="text-sm text-gray-600">Sei sicuro di voler rimuovere il logo attuale?</p>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Annulla
                        </button>
                        <form method="POST" action="{{ route('admin.letterheads.remove-logo', $letterhead) }}"
                            style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                Rimuovi
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endsection

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Anteprima logo
                const logoInput = document.getElementById('logo');
                const logoPreview = document.getElementById('logo-preview');
                const previewImage = document.getElementById('preview-image');

                if (logoInput && logoPreview && previewImage) {
                    logoInput.addEventListener('change', function(e) {
                        const file = e.target.files[0];
                        if (file) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                previewImage.src = e.target.result;
                                logoPreview.classList.remove('hidden');
                            };
                            reader.readAsDataURL(file);
                        } else {
                            logoPreview.classList.add('hidden');
                        }
                    });
                }
            });

            // Funzioni per gestire la modal Tailwind
            function removeLogo() {
                const modal = document.getElementById('removeLogoModal');
                if (modal) {
                    modal.classList.remove('hidden');
                }
            }

            function closeModal() {
                const modal = document.getElementById('removeLogoModal');
                if (modal) {
                    modal.classList.add('hidden');
                }
            }

            // Chiudi modal cliccando fuori
            document.addEventListener('click', function(event) {
                const modal = document.getElementById('removeLogoModal');
                if (modal && event.target === modal) {
                    closeModal();
                }
            });

            // Chiudi modal con ESC
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });
        </script>
    @endpush
