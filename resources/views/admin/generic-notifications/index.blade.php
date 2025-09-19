@extends('layouts.admin')

@section('title', 'Lettere Generiche')

@section('content')
    <div class="max-w-7xl mx-auto py-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">
                <i class="fas fa-envelope mr-2"></i>Lettere Generiche
            </h2>
            <a href="{{ route('admin.letter-notifications.create') }}"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                <i class="fas fa-plus mr-1"></i>Nuova Lettera
            </a>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-blue-600 text-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-bold">{{ $stats['total'] ?? 0 }}</h3>
                        <p class="text-blue-100">Totali</p>
                    </div>
                    <i class="fas fa-list text-2xl text-blue-200"></i>
                </div>
            </div>
            <div class="bg-yellow-500 text-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-bold">{{ $stats['drafts'] ?? 0 }}</h3>
                        <p class="text-yellow-100">Bozze</p>
                    </div>
                    <i class="fas fa-edit text-2xl text-yellow-200"></i>
                </div>
            </div>
            <div class="bg-blue-500 text-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-bold">{{ $stats['ready'] ?? 0 }}</h3>
                        <p class="text-blue-100">Pronte</p>
                    </div>
                    <i class="fas fa-check text-2xl text-blue-200"></i>
                </div>
            </div>
            <div class="bg-green-600 text-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-bold">{{ $stats['sent_today'] ?? 0 }}</h3>
                        <p class="text-green-100">Inviate Oggi</p>
                    </div>
                    <i class="fas fa-paper-plane text-2xl text-green-200"></i>
                </div>
            </div>
        </div>

        {{-- Filtri --}}
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div class="md:col-span-2">
                    <select name="type"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Tutti i tipi</option>
                        @if (isset($types))
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}" {{ request('type') == $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div class="md:col-span-2">
                    <select name="status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Tutti gli stati</option>
                        @if (isset($statuses))
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div>
                    <input type="date" name="date_from"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        value="{{ request('date_from') }}">
                </div>
                <div>
                    <button type="submit"
                        class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                        Filtra
                    </button>
                </div>
            </form>
        </div>

        {{-- Lista Notifiche --}}
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6">
                @if (isset($letters) && $letters->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full table-auto">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-900">Titolo</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-900">Tipo</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-900">Template</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-900">Status</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-900">Destinatari</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-900">Creata</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-900">Azioni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($letters as $letter)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900">
                                                {{ $letter->title ?? 'N/A' }}</div>
                                            @if (!empty($letter->description))
                                                <div class="text-sm text-gray-500">
                                                    {{ Str::limit($letter->description, 50) }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                {{ $letter->type_label ?? ($letter->type ?? 'N/A') }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            {{ $letter->template->name ?? 'N/A' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                {{ $letter->status ?? 'draft' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            {{ $letter->formatted_recipients ?? '0 destinatari' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            {{ $letter->created_at ? $letter->created_at->format('d/m/Y H:i') : 'N/A' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center space-x-2">
                                                {{-- Edit --}}
                                                <a href="{{ route('admin.letter-notifications.edit', $letter->id) }}"
                                                    class="text-indigo-600 hover:text-indigo-900 p-1 rounded hover:bg-indigo-50"
                                                    title="Modifica">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                        </path>
                                                    </svg>
                                                </a>

                                                {{-- Show --}}
                                                <a href="{{ route('admin.letter-notifications.show', $letter) }}"
                                                    class="text-gray-600 hover:text-gray-900 p-1 rounded hover:bg-gray-50"
                                                    title="Visualizza">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                        </path>
                                                    </svg>
                                                </a>

                                                {{-- Invia --}}
                                                <form action="{{ route('admin.letter-notifications.send', $letter) }}"
                                                    method="POST" class="inline-block">
                                                    @csrf
                                                    <button type="submit"
                                                        class="text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-50"
                                                        onclick="return confirm('Inviare le notifiche?')" title="Invia">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8">
                                                            </path>
                                                        </svg>
                                                    </button>
                                                </form>

                                                {{-- Delete --}}
                                                <form action="{{ route('admin.letter-notifications.destroy', $letter) }}"
                                                    method="POST" class="inline-block">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="text-red-600 hover:text-red-900 p-1 rounded hover:bg-red-50"
                                                        onclick="return confirm('Eliminare questa notifica?')"
                                                        title="Elimina">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                            </path>
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
                @else
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-4xl text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Nessuna lettera trovata</h3>
                        <p class="text-gray-500 mb-6">Inizia creando la tua prima lettera generica</p>
                        <a href="{{ route('admin.letter-notifications.create') }}"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">
                            <i class="fas fa-plus mr-1"></i>Crea la prima lettera
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
