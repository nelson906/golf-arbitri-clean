{{-- File: resources/views/documents/create.blade.php --}}
@extends('layouts.admin')

@section('title', 'Carica Nuovo Documento')

@section('content')
<div class="py-6">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">📤 Carica Nuovo Documento</h1>
            <p class="text-gray-600 mt-1">Aggiungi un nuovo documento al sistema</p>
        </div>
        <a href="{{ route('admin.documents.index') }}"
           class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg">
            ← Torna alla Lista
        </a>
    </div>

    {{-- Form Upload --}}
    <div class="bg-white rounded-lg shadow">
        <div class="p-6">
            <form method="POST" action="{{ route('admin.documents.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- File Upload --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            File *
                        </label>
                        <input type="file" name="file" required
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-sm text-gray-500 mt-1">Max 10MB. Formati supportati: PDF, DOC, DOCX, XLS, XLSX, immagini</p>
                        @error('file')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Nome --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nome Documento
                        </label>
                        <input type="text" name="name" value="{{ old('name') }}"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Lascia vuoto per usare il nome del file">
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Categoria --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Categoria *
                        </label>
                        <select name="category" required
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Seleziona categoria</option>
                            <option value="general" {{ old('category') === 'general' ? 'selected' : '' }}>Generale</option>
                            <option value="tournament" {{ old('category') === 'tournament' ? 'selected' : '' }}>Torneo</option>
                            <option value="regulation" {{ old('category') === 'regulation' ? 'selected' : '' }}>Regolamento</option>
                            <option value="form" {{ old('category') === 'form' ? 'selected' : '' }}>Modulo</option>
                            <option value="template" {{ old('category') === 'template' ? 'selected' : '' }}>Template</option>
                        </select>
                        @error('category')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Descrizione --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Descrizione
                        </label>
                        <textarea name="description" rows="3"
                                  class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Descrizione opzionale del documento">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Zona (solo per National Admin) --}}
                    @if(auth()->user()->user_type === 'national_admin' || auth()->user()->user_type === 'super_admin')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Zona
                        </label>
                        <select name="zone_id"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Globale (tutte le zone)</option>
                            {{-- Qui dovresti caricare le zone dal database --}}
                        </select>
                        @error('zone_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif

                    {{-- Pubblico --}}
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="is_public" value="1" {{ old('is_public') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">Documento pubblico</span>
                        </label>
                        <p class="text-sm text-gray-500 mt-1">I documenti pubblici sono visibili a tutti gli utenti</p>
                    </div>
                </div>

                {{-- Bottoni --}}
                <div class="flex justify-end space-x-4 mt-6 pt-6 border-t">
                    <a href="{{ route('admin.documents.index') }}"
                       class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg">
                        Annulla
                    </a>
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                        📤 Carica Documento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
