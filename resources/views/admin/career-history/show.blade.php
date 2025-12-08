@extends('layouts.admin')

@section('title', 'Storico - ' . $user->name)

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $user->name }}</h1>
            <p class="mt-1 text-sm text-gray-600">{{ $user->email }} - {{ ucfirst($user->level ?? 'N/A') }}</p>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('admin.career-history.archive-form') }}?user_id={{ $user->id }}"
               class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                Archivia Anno
            </a>
            <a href="{{ route('admin.career-history.index') }}"
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                Torna alla Lista
            </a>
        </div>
    </div>

    {{-- Alert --}}
    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            {{ session('error') }}
        </div>
    @endif

    @if($history)
        {{-- Stats Card --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Anni di Attivita</div>
                <div class="text-2xl font-bold text-indigo-600">{{ count($history->tournaments_by_year ?? []) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Tornei Totali</div>
                <div class="text-2xl font-bold text-green-600">
                    {{ collect($history->tournaments_by_year ?? [])->flatten(1)->count() }}
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Assegnazioni Totali</div>
                <div class="text-2xl font-bold text-blue-600">
                    {{ collect($history->assignments_by_year ?? [])->flatten(1)->count() }}
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Ultimo Aggiornamento</div>
                <div class="text-2xl font-bold text-gray-600">{{ $history->last_updated_year }}</div>
            </div>
        </div>

        {{-- Anni disponibili --}}
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Storico per Anno</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Anno</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Tornei</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Assegnazioni</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Disponibilita</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @php
                            $years = collect($history->tournaments_by_year ?? [])->keys()->sortDesc();
                        @endphp
                        @forelse($years as $year)
                            @php
                                $tournaments = $history->tournaments_by_year[$year] ?? [];
                                $assignments = $history->assignments_by_year[$year] ?? [];
                                $availabilities = $history->availabilities_by_year[$year] ?? [];
                            @endphp
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-lg font-semibold text-gray-900">{{ $year }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                        {{ count($tournaments) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ count($assignments) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        {{ count($availabilities) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <a href="{{ route('admin.career-history.edit-year', [$user, $year]) }}"
                                       class="text-indigo-600 hover:text-indigo-900">
                                        Modifica
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                    Nessun anno nello storico
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Career Stats JSON --}}
        @if($history->career_stats)
            <div class="bg-white rounded-lg shadow mt-6">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Statistiche Carriera</h3>
                </div>
                <div class="p-4">
                    <pre class="bg-gray-100 p-4 rounded text-sm overflow-x-auto">{{ json_encode($history->career_stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
        @endif
    @else
        <div class="bg-white rounded-lg shadow p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Nessuno storico</h3>
            <p class="mt-1 text-sm text-gray-500">Non esiste ancora uno storico carriera per questo arbitro.</p>
            <div class="mt-6">
                <a href="{{ route('admin.career-history.archive-form') }}?user_id={{ $user->id }}"
                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    Crea Storico
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
