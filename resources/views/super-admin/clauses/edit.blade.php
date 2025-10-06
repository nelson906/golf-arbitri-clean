@extends('layouts.admin')

@section('title', 'Modifica Clausola')

@section('content')
<div class="container mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center mb-3">
            <a href="{{ route('super-admin.clauses.index') }}"
               class="mr-4 text-gray-600 hover:text-gray-900 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Modifica Clausola</h1>
        </div>
        <p class="text-gray-600">Modifica la clausola: <strong>{{ $clause->title }}</strong></p>
    </div>

    {{-- Errors --}}
    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
            <p class="font-bold">Errore di validazione:</p>
            <ul class="mt-2 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Success --}}
    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('super-admin.clauses.update', $clause) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Colonna Principale --}}
            <div class="lg:col-span-2">

                {{-- Informazioni Base --}}
                <div class="bg-white rounded-lg shadow-md mb-6">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 rounded-t-lg">
                        <h2 class="text-lg font-semibold text-gray-800">Informazioni Base</h2>
                    </div>
                    <div class="p-6 space-y-6">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="code" class="block text-sm font-medium text-gray-700 mb-2">
                                    Codice <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="code"
                                    name="code"
                                    value="{{ old('code', $clause->code) }}"
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('code') border-red-500 @enderror"
                                >
                                <p class="mt-1 text-sm text-gray-500">
                                    Codice univoco alfanumerico (solo minuscole, numeri e underscore)
                                </p>
                                @error('code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                                    Titolo <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="title"
                                    name="title"
                                    value="{{ old('title', $clause->title) }}"
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('title') border-red-500 @enderror"
                                >
                                @error('title')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
                                    Categoria <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="category"
                                    name="category"
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('category') border-red-500 @enderror"
                                >
                                    <option value="">Seleziona categoria</option>
                                    @foreach($categories as $key => $label)
                                        <option value="{{ $key }}" {{ old('category', $clause->category) === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="applies_to" class="block text-sm font-medium text-gray-700 mb-2">
                                    Applicabile a <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="applies_to"
                                    name="applies_to"
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('applies_to') border-red-500 @enderror"
                                >
                                    <option value="">Seleziona destinatario</option>
                                    @foreach($appliesTo as $key => $label)
                                        <option value="{{ $key }}" {{ old('applies_to', $clause->applies_to) === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('applies_to')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="content" class="block text-sm font-medium text-gray-700 mb-2">
                                Contenuto Clausola <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                id="content"
                                name="content"
                                rows="8"
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('content') border-red-500 @enderror"
                            >{{ old('content', $clause->content) }}</textarea>
                            <p class="mt-1 text-sm text-gray-500">
                                Questo testo verrà inserito nel documento quando la clausola viene selezionata
                            </p>
                            @error('content')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </div>

                {{-- Utilizzo --}}
                @if($usageCount > 0)
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                Questa clausola è utilizzata in <strong>{{ $usageCount }}</strong> notifiche.
                                Le modifiche al contenuto impatteranno solo le notifiche future.
                            </p>
                        </div>
                    </div>
                </div>
                @endif

            </div>

            {{-- Sidebar --}}
            <div class="lg:col-span-1">

                {{-- Opzioni --}}
                <div class="bg-white rounded-lg shadow-md mb-6">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 rounded-t-lg">
                        <h2 class="text-lg font-semibold text-gray-800">Opzioni</h2>
                    </div>
                    <div class="p-6 space-y-4">

                        <div>
                            <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">
                                Ordine di visualizzazione
                            </label>
                            <input
                                type="number"
                                id="sort_order"
                                name="sort_order"
                                value="{{ old('sort_order', $clause->sort_order) }}"
                                min="0"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('sort_order') border-red-500 @enderror"
                            >
                            <p class="mt-1 text-sm text-gray-500">
                                Ordine crescente (0 = prima)
                            </p>
                            @error('sort_order')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center">
                            <input
                                type="checkbox"
                                id="is_active"
                                name="is_active"
                                value="1"
                                {{ old('is_active', $clause->is_active) ? 'checked' : '' }}
                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                            >
                            <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                Clausola attiva
                            </label>
                        </div>
                        <p class="text-sm text-gray-500">
                            Solo le clausole attive sono disponibili per la selezione
                        </p>

                    </div>
                </div>

                {{-- Azioni --}}
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 space-y-3">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg transition duration-200">
                            Salva Modifiche
                        </button>
                        <a href="{{ route('super-admin.clauses.index') }}"
                           class="block w-full text-center bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold px-6 py-3 rounded-lg transition duration-200">
                            Annulla
                        </a>
                    </div>
                </div>

            </div>
        </div>

    </form>

</div>
@endsection
