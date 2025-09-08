{{-- File: resources/views/admin/admin.letter-templates/index.blade.php --}}
@extends('layouts.admin')

@section('title', ' ' )

@section('content')

    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📝 Template Lettere
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('admin.tournament-notifications.index') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    📧 Notifiche
                </a>
                <a href="{{ route('admin.letter-templates.create') }}"
                   class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    ➕ Nuovo Template
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-400 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    {{-- Stats Header --}}
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ $templates->count() }}</div>
                            <div class="text-sm text-blue-600">Totale Template</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">
                                {{ $templates->where('is_active', true)->count() }}
                            </div>
                            <div class="text-sm text-green-600">Attivi</div>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">
                                {{ $templates->where('is_default', true)->count() }}
                            </div>
                            <div class="text-sm text-yellow-600">Predefiniti</div>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-purple-600">
                                {{ $templates->groupBy('type')->count() }}
                            </div>
                            <div class="text-sm text-purple-600">Tipologie</div>
                        </div>
                    </div>

                    {{-- Filter Bar --}}
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <form method="GET" class="flex flex-wrap gap-4 items-end">
                            <div class="flex-1 min-w-48">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipologia</label>
                                <select name="type" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Tutte le tipologie</option>
                                    @foreach($types as $key => $label)
                                        <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex-1 min-w-48">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                                <select name="zone_id" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Tutte le zone</option>
                                    @foreach($zones as $zone)
                                        <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                                            {{ $zone->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex-1 min-w-48">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ricerca</label>
                                <input type="text" name="search" value="{{ request('search') }}"
                                       placeholder="Nome o oggetto..."
                                       class="w-full rounded-md border-gray-300 shadow-sm">
                            </div>

                            <div class="flex space-x-2">
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                    🔍 Filtra
                                </button>
                                <a href="{{ route('admin.letter-templates.index') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    🗑️ Reset
                                </a>
                            </div>
                        </form>
                    </div>

                    @if($templates->count() > 0)
                        {{-- Templates Grid --}}
                        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                            @foreach($templates as $template)
                                <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">

                                    {{-- Card Header --}}
                                    <div class="p-4 border-b border-gray-200">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <h3 class="text-lg font-medium text-gray-900 mb-1">
                                                    {{ $template->name }}
                                                </h3>
                                                <div class="flex flex-wrap gap-2">
                                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                                        {{ $template->type === 'assignment' ? 'bg-blue-100 text-blue-800' : '' }}
                                                        {{ $template->type === 'convocation' ? 'bg-green-100 text-green-800' : '' }}
                                                        {{ $template->type === 'club' ? 'bg-purple-100 text-purple-800' : '' }}
                                                        {{ $template->type === 'institutional' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                                        {{ $template->type_label }}
                                                    </span>

                                                    @if($template->is_default)
                                                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800">
                                                            ⭐ Predefinito
                                                        </span>
                                                    @endif

                                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                                        {{ $template->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                        {{ $template->status_label }}
                                                    </span>
                                                </div>
                                            </div>

                                            {{-- Actions Dropdown --}}
                                            <div class="relative">
                                                <button class="p-2 text-gray-400 hover:text-gray-600" onclick="toggleDropdown('dropdown-{{ $template->id }}')">
                                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                                    </svg>
                                                </button>
                                                <div id="dropdown-{{ $template->id }}" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-20 border border-gray-200">
                                                    <a href="{{ route('admin.letter-templates.show', $template) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        👁️ Visualizza
                                                    </a>
                                                    <a href="{{ route('admin.letter-templates.preview', $template) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        🔍 Anteprima
                                                    </a>
                                                    <a href="{{ route('admin.letter-templates.edit', $template) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        ✏️ Modifica
                                                    </a>
                                                    <form method="POST" action="{{ route('admin.letter-templates.duplicate', $template) }}" class="block">
                                                        @csrf
                                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                            📋 Duplica
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.letter-templates.toggle-active', $template) }}" class="block">
                                                        @csrf
                                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                            {{ $template->is_active ? '⏸️ Disattiva' : '▶️ Attiva' }}
                                                        </button>
                                                    </form>
                                                    <div class="border-t border-gray-200">
                                                        <form method="POST" action="{{ route('admin.letter-templates.destroy', $template) }}"
                                                              onsubmit="return confirm('Sei sicuro di voler eliminare questo template?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                                🗑️ Elimina
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Card Body --}}
                                    <div class="p-4">
                                        <div class="space-y-3">

                                            {{-- Subject Preview --}}
                                            <div>
                                                <h4 class="text-sm font-medium text-gray-700 mb-1">📧 Oggetto:</h4>
                                                <p class="text-sm text-gray-600 bg-gray-50 p-2 rounded">
                                                    {{ Str::limit($template->subject, 80) }}
                                                </p>
                                            </div>

                                            {{-- Body Preview --}}
                                            <div>
                                                <h4 class="text-sm font-medium text-gray-700 mb-1">📝 Contenuto:</h4>
                                                <p class="text-xs text-gray-500 bg-gray-50 p-2 rounded h-20 overflow-hidden">
                                                    {{ Str::limit(strip_tags($template->body), 150) }}
                                                </p>
                                            </div>

                                            {{-- Scope Info --}}
                                            <div class="flex items-center justify-between text-xs text-gray-500">
                                                <div>
                                                    <span class="font-medium">Ambito:</span> {{ $template->scope_label }}
                                                </div>
                                                @if($template->zone)
                                                    <div>{{ $template->zone->name }}</div>
                                                @endif
                                            </div>

                                            {{-- Variables Used --}}
                                            @if($template->used_variables)
                                                <div>
                                                    <h5 class="text-xs font-medium text-gray-700 mb-1">🔧 Variabili:</h5>
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach(array_slice(array_keys($template->used_variables), 0, 3) as $variable)
                                                            <span class="inline-flex px-1 py-0.5 text-xs bg-indigo-100 text-indigo-800 rounded">
@php
    $cleanVariable = str_replace(['{{', '}}'], '', $variable);
@endphp
{{ $cleanVariable }}
                                                            </span>
                                                        @endforeach
                                                        @if(count($template->used_variables) > 3)
                                                            <span class="text-xs text-gray-500">+{{ count($template->used_variables) - 3 }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Card Footer --}}
                                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 rounded-b-lg">
                                        <div class="flex items-center justify-between">
                                            <div class="text-xs text-gray-500">
                                                Creato {{ $template->created_at->diffForHumans() }}
                                            </div>
                                            <div class="flex space-x-2">
                                                <a href="{{ route('admin.letter-templates.preview', $template) }}"
                                                   class="inline-flex items-center px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                                    🔍 Anteprima
                                                </a>
                                                <a href="{{ route('admin.letter-templates.edit', $template) }}"
                                                   class="inline-flex items-center px-2 py-1 text-xs bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200">
                                                    ✏️ Modifica
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Pagination --}}
                        <div class="mt-6">
                            {{ $templates->links() }}
                        </div>

                    @else
                        {{-- Empty State --}}
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">📝</div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Nessun template trovato</h3>
                            <p class="text-gray-500 mb-6">Non ci sono template che corrispondono ai criteri di ricerca.</p>
                            <a href="{{ route('admin.letter-templates.create') }}"
                               class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                ➕ Crea Primo Template
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript for Dropdowns --}}
    <script>
        function toggleDropdown(dropdownId) {
            // Close all other dropdowns
            document.querySelectorAll('[id^="dropdown-"]').forEach(function(dropdown) {
                if (dropdown.id !== dropdownId) {
                    dropdown.classList.add('hidden');
                }
            });

            // Toggle the requested dropdown
            const dropdown = document.getElementById(dropdownId);
            dropdown.classList.toggle('hidden');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('[onclick^="toggleDropdown"]')) {
                document.querySelectorAll('[id^="dropdown-"]').forEach(function(dropdown) {
                    dropdown.classList.add('hidden');
                });
            }
        });
    </script>
@endsection
