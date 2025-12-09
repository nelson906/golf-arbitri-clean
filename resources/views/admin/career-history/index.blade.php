@extends('layouts.admin')

@section('title', 'Storico Carriera Arbitri')

@section('content')
    <div class="container mx-auto px-4 py-8">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Storico Carriera Arbitri</h1>
                <p class="mt-1 text-sm text-gray-600">Gestisci lo storico carriera degli arbitri</p>
            </div>
            <a href="{{ route('admin.career-history.archive-form') }}"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                {{ $canArchiveAll ?? false ? 'Archivia Anno' : 'Archivia Anno Arbitro' }}
            </a>
        </div>

        {{-- Filtri --}}
        <div class="bg-white rounded-lg shadow mb-6 p-4">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cerca</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Nome o email..."
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                {{-- Zone filter only for super_admin --}}
                @if (isset($zones) && $zones->count() > 0)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                        <select name="zone_id"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Tutte le zone</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                                    {{ $zone->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ha storico</label>
                    <select name="has_history"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tutti</option>
                        <option value="1" {{ request('has_history') === '1' ? 'selected' : '' }}>Con storico</option>
                        <option value="0" {{ request('has_history') === '0' ? 'selected' : '' }}>Senza storico</option>
                    </select>
                </div>
                <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm">
                    Filtra
                </button>
                <a href="{{ route('admin.career-history.index') }}" class="text-gray-600 hover:text-gray-800 px-4 py-2">
                    Reset
                </a>
            </form>
        </div>

        {{-- Alert --}}
        @if (session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                {{ session('error') }}
            </div>
        @endif

        {{-- Tabella --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Arbitro</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zona</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Livello</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Assegnazioni</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Anni Storico</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Completezza</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($referees as $referee)
                        @php
                            $history = $referee->careerHistory;
                            $yearsCount = $history ? count($history->tournaments_by_year ?? []) : 0;
                            $completeness = $history?->data_completeness_score ?? 0;
                        @endphp
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $referee->name }}</div>
                                <div class="text-sm text-gray-500">{{ $referee->email }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $referee->zone->name ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ ucfirst($referee->level ?? 'N/A') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                {{ $referee->assignments_count ?? 0 }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if ($yearsCount > 0)
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        {{ $yearsCount }} anni
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">
                                        Nessuno
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if ($completeness > 0)
                                    <div class="w-16 mx-auto bg-gray-200 rounded-full h-2">
                                        <div class="bg-indigo-600 h-2 rounded-full"
                                            style="width: {{ $completeness * 100 }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500">{{ number_format($completeness * 100, 0) }}%</span>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <a href="{{ route('admin.career-history.show', $referee) }}"
                                    class="text-indigo-600 hover:text-indigo-900">
                                    Visualizza
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                Nessun arbitro trovato
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginazione --}}
        <div class="mt-4">
            {{ $referees->links() }}
        </div>
    </div>
@endsection
