@extends('layouts.admin')

@section('title', 'Gestione Clausole Notifiche')

@section('content')
<div class="container mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Gestione Clausole Notifiche</h1>
            <p class="text-gray-600 mt-1">Crea e gestisci le clausole parametriche per le notifiche</p>
        </div>
        <a href="{{ route('super-admin.clauses.create') }}"
           class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg shadow-md transition duration-200">
            <span class="mr-2">+</span> Nuova Clausola
        </a>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
            <p>{{ session('error') }}</p>
        </div>
    @endif

    @if(session('warning'))
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded" role="alert">
            <p>{{ session('warning') }}</p>
        </div>
    @endif

    {{-- Filtri --}}
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="{{ route('super-admin.clauses.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">

            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Cerca</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Codice, titolo..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>

            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-2">Categoria</label>
                <select id="category" name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Tutte le categorie</option>
                    @foreach($categories as $key => $label)
                        <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="applies_to" class="block text-sm font-medium text-gray-700 mb-2">Applicabile a</label>
                <select id="applies_to" name="applies_to" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Tutti</option>
                    @foreach($appliesTo as $key => $label)
                        <option value="{{ $key }}" {{ request('applies_to') === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                <label class="flex items-center">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        {{ request('is_active') ? 'checked' : '' }}
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                    >
                    <span class="ml-2 text-sm text-gray-700">Solo attive</span>
                </label>
            </div>

            <div class="md:col-span-4 flex gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition duration-200">
                    Filtra
                </button>
                <a href="{{ route('super-admin.clauses.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg transition duration-200">
                    Reset
                </a>
            </div>

        </form>
    </div>

    {{-- Tabella Clausole --}}
    <div class="bg-white rounded-lg shadow-md overflow-hidden">

        @if($clauses->isEmpty())
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                </svg>
                <p class="text-gray-500 mt-4">Nessuna clausola trovata</p>
                <a href="{{ route('super-admin.clauses.create') }}" class="inline-block mt-4 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    Crea la prima clausola
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Codice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Titolo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applicabile a</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ordine</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($clauses as $clause)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <code class="text-sm text-blue-600 font-mono">{{ $clause->code }}</code>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $clause->title }}</div>
                                    <div class="text-sm text-gray-500">{{ Str::limit($clause->content, 80) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $categoryColors = [
                                            'spese' => 'bg-green-100 text-green-800',
                                            'logistica' => 'bg-blue-100 text-blue-800',
                                            'responsabilita' => 'bg-yellow-100 text-yellow-800',
                                            'comunicazioni' => 'bg-purple-100 text-purple-800',
                                            'altro' => 'bg-gray-100 text-gray-800'
                                        ];
                                        $colorClass = $categoryColors[$clause->category] ?? 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $colorClass }}">
                                        {{ $clause->category_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $clause->applies_to_label }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 text-xs font-semibold text-gray-700 bg-gray-100 rounded">
                                        {{ $clause->sort_order }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <form action="{{ route('super-admin.clauses.toggle-active', $clause) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="relative inline-flex items-center h-6 rounded-full w-11 transition-colors focus:outline-none {{ $clause->is_active ? 'bg-green-500' : 'bg-gray-300' }}">
                                            <span class="inline-block w-4 h-4 transform bg-white rounded-full transition-transform {{ $clause->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                        </button>
                                    </form>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex justify-center gap-2">
                                        <a href="{{ route('super-admin.clauses.edit', $clause) }}"
                                           class="text-blue-600 hover:text-blue-900 transition duration-200"
                                           title="Modifica">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>
                                        <form action="{{ route('super-admin.clauses.destroy', $clause) }}"
                                              method="POST"
                                              class="inline"
                                              onsubmit="return confirm('Sei sicuro di voler eliminare questa clausola?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="text-red-600 hover:text-red-900 transition duration-200"
                                                    title="Elimina">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Paginazione --}}
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t border-gray-200">
                <div class="text-sm text-gray-700">
                    Visualizzati <span class="font-medium">{{ $clauses->firstItem() }}</span> - <span class="font-medium">{{ $clauses->lastItem() }}</span> di <span class="font-medium">{{ $clauses->total() }}</span> clausole
                </div>
                <div>
                    {{ $clauses->links() }}
                </div>
            </div>
        @endif

    </div>

</div>
@endsection
