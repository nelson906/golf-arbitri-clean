@extends('layouts.admin')

@section('header')
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-900">📄 Gestione Carta Intestata</h1>
        <a href="{{ route('admin.letterheads.create') }}"
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
            ➕ Nuova Letterhead
        </a>
    </div>
@endsection

@section('content')
    <div class="space-y-6">

        {{-- Filtri e Ricerca --}}
        <div class="bg-white rounded-lg shadow p-6">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-64">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">
                        🔍 Cerca
                    </label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}"
                        placeholder="Nome, zona, descrizione..."
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">
                        🌍 Zona
                    </label>
                    <select name="zone_id" id="zone_id"
                        class="block rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Tutte le zone</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                        📊 Stato
                    </label>
                    <select name="status" id="status"
                        class="block rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Tutti</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Attivi</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Disattivi</option>
                    </select>
                </div>
                <button type="submit"
                    class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    Filtra
                </button>
                <a href="{{ route('admin.letterheads.index') }}"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-700 font-medium py-2 px-4 rounded-md transition duration-200">
                    Reset
                </a>
            </form>
        </div>

        {{-- Lista Letterheads --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    Letterheads ({{ $letterheads->total() }})
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Letterhead
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Zona
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Stato
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Ultimo Aggiornamento
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Azioni
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($letterheads as $letterhead)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        @if ($letterhead->logo_path)
                                            <img src="{{ Storage::url($letterhead->logo_path) }}" alt="Logo"
                                                class="h-10 w-10 rounded-lg object-cover mr-3">
                                        @else
                                            <div
                                                class="h-10 w-10 bg-gray-200 rounded-lg flex items-center justify-center mr-3">
                                                📄
                                            </div>
                                        @endif
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $letterhead->title }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ $letterhead->description ?? 'Nessuna descrizione' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900">
                                        {{ $letterhead->zone?->name ?? 'Globale' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center space-x-2">
                                        <span
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $letterhead->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $letterhead->is_active ? 'Attivo' : 'Disattivo' }}
                                        </span>
                                        @if ($letterhead->is_default)
                                            <span
                                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                Default
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $letterhead->updated_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                    <a href="{{ route('admin.letterheads.preview', $letterhead) }}"
                                        class="text-gray-600 hover:text-gray-900" title="Anteprima">
                                        👁️
                                    </a>
                                    <a href="{{ route('admin.letterheads.edit', $letterhead) }}"
                                        class="text-blue-600 hover:text-blue-900" title="Modifica">
                                        ✏️
                                    </a>
                                    <form method="POST"
                                        action="{{ route('admin.letterheads.toggle-active', $letterhead) }}"
                                        class="inline">
                                        @csrf
                                        <button type="submit" class="text-yellow-600 hover:text-yellow-900"
                                            title="{{ $letterhead->is_active ? 'Disattiva' : 'Attiva' }}">
                                            {{ $letterhead->is_active ? '⏸️' : '▶️' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.letterheads.duplicate', $letterhead) }}"
                                        class="inline">
                                        @csrf
                                        <button type="submit" class="text-green-600 hover:text-green-900" title="Duplica">
                                            📋
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.letterheads.destroy', $letterhead) }}"
                                        class="inline"
                                        onsubmit="return confirm('Sei sicuro di voler eliminare questa letterhead?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900" title="Elimina">
                                            🗑️
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="text-gray-500">
                                        <div class="text-4xl mb-2">📄</div>
                                        <div class="text-lg font-medium">Nessuna letterhead trovata</div>
                                        <div class="text-sm">Crea la prima letterhead per iniziare</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($letterheads->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $letterheads->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
