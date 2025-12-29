@extends('layouts.admin')

@section('title', 'Arbitri Sottoutilizzati')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Arbitri Sottoutilizzati</h1>
            <p class="mt-2 text-sm text-gray-600">Arbitri con poche assegnazioni rispetto alla soglia minima</p>
        </div>
        <a href="{{ route('admin.assignment-validation.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Torna
        </a>
    </div>

    <!-- Filtri -->
    <div class="bg-white shadow rounded-lg mb-6 p-6">
        <form method="GET" class="flex items-end space-x-4">
            <div class="flex-grow">
                <label class="block text-sm font-medium text-gray-700 mb-2">Soglia Minima</label>
                <input type="number" name="threshold" value="{{ $threshold }}" min="0" max="10" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <p class="mt-1 text-xs text-gray-500">Mostra arbitri con meno di X assegnazioni</p>
            </div>
            <div>
                <label class="flex items-center">
                    <input type="checkbox" name="only_available" {{ request('only_available') ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700">Solo disponibili</span>
                </label>
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Applica Filtro
            </button>
        </form>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-5 shadow rounded-lg">
            <p class="text-sm text-gray-500">Sottoutilizzati</p>
            <p class="text-2xl font-bold">{{ $stats['total_underassigned'] }}</p>
        </div>
        <div class="bg-white p-5 shadow rounded-lg border-l-4 border-green-500">
            <p class="text-sm text-gray-500">Disponibili</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['available'] }}</p>
        </div>
        <div class="bg-white p-5 shadow rounded-lg border-l-4 border-red-500">
            <p class="text-sm text-gray-500">Non Disponibili</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['unavailable'] }}</p>
        </div>
        <div class="bg-white p-5 shadow rounded-lg border-l-4 border-yellow-500">
            <p class="text-sm text-gray-500">Stato Sconosciuto</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $stats['unknown'] }}</p>
        </div>
    </div>

    @if($referees->count() > 0)
        @if($stats['available'] > 0)
        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6 rounded">
            <p class="text-sm text-green-700">
                <strong>Opportunità:</strong> Ci sono <strong>{{ $stats['available'] }}</strong> arbitri disponibili con poche assegnazioni.
            </p>
        </div>
        @endif

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Arbitro</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Livello</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zona</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Assegnazioni</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Disponibilità</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($referees as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="bg-gray-100 rounded-full p-2 mr-3">
                                    <svg class="h-5 w-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">{{ $item['referee']->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $item['referee']->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-800">
                                {{ ucfirst($item['referee']->level) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ $item['referee']->zone->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <span class="px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                    {{ $item['assignments_count'] }}
                                </span>
                                <span class="text-xs text-gray-500">
                                    (-{{ $item['under_threshold'] }})
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($item['availability_status'] === 'available')
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Disponibile
                                </span>
                            @elseif($item['availability_status'] === 'unavailable')
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                    Non Disponibile
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                                    </svg>
                                    Sconosciuto
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right text-sm font-medium space-x-2">
                            <a href="{{ route('admin.users.show', $item['referee']->id) }}" class="text-blue-600 hover:text-blue-900">
                                <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                            @if($item['availability_status'] === 'available')
                            <a href="{{ route('admin.assignments.create') }}?user_id={{ $item['referee']->id }}" class="text-green-600 hover:text-green-900">
                                <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                            </a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="bg-white shadow rounded-lg p-12 text-center">
            <svg class="mx-auto h-16 w-16 text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="text-xl font-medium text-gray-900 mb-2">Ottima Distribuzione!</h3>
            <p class="text-gray-600 mb-6">Con soglia a <strong>{{ $threshold }}</strong>, tutti gli arbitri sono utilizzati adeguatamente.</p>
            <a href="{{ route('admin.assignment-validation.index') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-white bg-blue-600 hover:bg-blue-700">
                Torna al Dashboard
            </a>
        </div>
    @endif
</div>
@endsection
