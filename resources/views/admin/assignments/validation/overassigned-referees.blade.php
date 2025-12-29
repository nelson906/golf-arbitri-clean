@extends('layouts.admin')

@section('title', 'Arbitri Sovrassegnati')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Arbitri Sovrassegnati</h1>
            <p class="mt-2 text-sm text-gray-600">Arbitri con carico di lavoro superiore alla soglia</p>
        </div>
        <a href="{{ route('admin.assignment-validation.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Torna
        </a>
    </div>

    <!-- Filtro -->
    <div class="bg-white shadow rounded-lg mb-6 p-6">
        <form method="GET" class="flex items-end space-x-4">
            <div class="flex-grow">
                <label class="block text-sm font-medium text-gray-700 mb-2">Soglia Assegnazioni</label>
                <input type="number" name="threshold" value="{{ $threshold }}" min="1" max="20" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <p class="mt-1 text-xs text-gray-500">Mostra arbitri con più di X assegnazioni</p>
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                Applica
            </button>
        </form>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-5 shadow rounded-lg">
            <p class="text-sm text-gray-500">Sovrassegnati</p>
            <p class="text-2xl font-bold">{{ $stats['total_overassigned'] }}</p>
        </div>
        <div class="bg-white p-5 shadow rounded-lg">
            <p class="text-sm text-gray-500">Media</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['avg_assignments'] }}</p>
        </div>
        <div class="bg-white p-5 shadow rounded-lg">
            <p class="text-sm text-gray-500">Massimo</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['max_assignments'] }}</p>
        </div>
        <div class="bg-white p-5 shadow rounded-lg">
            <p class="text-sm text-gray-500">Eccedenze Totali</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $stats['total_over_threshold'] }}</p>
        </div>
    </div>

    @if($referees->count() > 0)
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6 rounded">
            <p class="text-sm text-blue-700">
                <strong>Suggerimento:</strong> Considera di redistribuire alcune assegnazioni per bilanciare meglio il carico di lavoro.
            </p>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Arbitro</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Livello</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zona</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Assegnazioni</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Eccedenza</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">% Carico</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($referees as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="bg-blue-100 rounded-full p-2 mr-3">
                                    <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                            <span class="px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                {{ $item['assignments_count'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                +{{ $item['over_threshold'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center">
                                <div class="w-24 bg-gray-200 rounded-full h-5 mr-2">
                                    <div class="h-5 rounded-full {{ $item['workload_percentage'] > 150 ? 'bg-red-600' : ($item['workload_percentage'] > 120 ? 'bg-yellow-500' : 'bg-green-500') }}"
                                         style="width: {{ min($item['workload_percentage'], 100) }}%"></div>
                                </div>
                                <span class="text-sm font-medium">{{ $item['workload_percentage'] }}%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right text-sm font-medium space-x-2">
                            <a href="{{ route('admin.users.show', $item['referee']->id) }}" class="text-blue-600 hover:text-blue-900">
                                <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Legenda -->
        <div class="bg-white shadow rounded-lg mt-6 p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Legenda Percentuale Carico</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex items-center">
                    <div class="w-6 h-6 bg-green-500 rounded mr-2"></div>
                    <span class="text-sm">0-120%: Carico accettabile</span>
                </div>
                <div class="flex items-center">
                    <div class="w-6 h-6 bg-yellow-500 rounded mr-2"></div>
                    <span class="text-sm">121-150%: Carico elevato</span>
                </div>
                <div class="flex items-center">
                    <div class="w-6 h-6 bg-red-600 rounded mr-2"></div>
                    <span class="text-sm">>150%: Carico eccessivo</span>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-3">La percentuale è calcolata rispetto alla media delle assegnazioni di tutti gli arbitri attivi.</p>
        </div>
    @else
        <div class="bg-white shadow rounded-lg p-12 text-center">
            <svg class="mx-auto h-16 w-16 text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="text-xl font-medium text-gray-900 mb-2">Nessun Arbitro Sovrassegnato</h3>
            <p class="text-gray-600 mb-6">Con soglia a <strong>{{ $threshold }}</strong>, tutti hanno un carico accettabile.</p>
            <a href="{{ route('admin.assignment-validation.index') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-white bg-blue-600 hover:bg-blue-700">
                Torna al Dashboard
            </a>
        </div>
    @endif
</div>
@endsection
