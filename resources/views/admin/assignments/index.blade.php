@extends('layouts.admin')

@section('title', 'Gestione Assegnazioni')

@section('content')
    <div class="container mx-auto px-4 py-8">
        {{-- Header --}}
        <x-table-header title="Gestione Assegnazioni" description="Gestisci le assegnazioni degli arbitri ai tornei"
            :create-route="route('admin.assignments.create')" create-text="👤 Assegna Singolo Arbitro" create-color="blue" :secondary-route="route('admin.tournaments.index')"
            secondary-text="🌏 Assegna per Torneo" secondary-color="green" />

        {{-- Alert Messages --}}
        @if (session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p class="font-bold">Successo!</p>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-bold">Errore!</p>
                <p>{{ session('error') }}</p>
            </div>
        @endif

{{-- Filters - Versione compatta --}}
<div class="bg-white shadow rounded-lg p-4 mb-6">
    <form method="GET" action="{{ route('admin.assignments.index') }}"
        class="grid grid-cols-1 md:grid-cols-6 gap-3">

        {{-- Tournament Filter --}}
        <div class="md:col-span-2">
            <label for="tournament_id" class="block text-xs font-medium text-gray-700 mb-1">Torneo</label>
            <select name="tournament_id" id="tournament_id"
                class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Tutti i tornei</option>
                @foreach ($tournaments as $tournament)
                    <option value="{{ $tournament->id }}"
                        {{ request('tournament_id') == $tournament->id ? 'selected' : '' }}>
                        {{ $tournament->name }} - {{ $tournament->club->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Referee Filter --}}
        <div class="md:col-span-2">
            <label for="user_id" class="block text-xs font-medium text-gray-700 mb-1">Arbitro</label>
            <select name="user_id" id="user_id"
                class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Tutti gli arbitri</option>
                @foreach ($referees as $referee)
                    <option value="{{ $referee->id }}" {{ request('user_id') == $referee->id ? 'selected' : '' }}>
                        {{ $referee->last_name }} {{ $referee->first_name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Ordina arbitri --}}
        <div>
            <label for="sort" class="block text-xs font-medium text-gray-700 mb-1">Ordina</label>
            <select name="sort" id="sort"
                class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Default</option>
                <option value="surname_asc" {{ request('sort') == 'surname_asc' ? 'selected' : '' }}>A-Z</option>
                <option value="surname_desc" {{ request('sort') == 'surname_desc' ? 'selected' : '' }}>Z-A</option>
            </select>
        </div>

        {{-- Submit/Reset --}}
        <div class="flex items-end space-x-1">
            <button type="submit"
                class="flex-1 bg-indigo-600 text-white px-3 py-1.5 text-sm rounded-md hover:bg-indigo-700">
                Filtra
            </button>
            <a href="{{ route('admin.assignments.index') }}"
                class="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-50">
                Reset
            </a>
        </div>
    </form>
</div>

        {{-- Assignments Table --}}
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Arbitro
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Torneo
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Ruolo
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Assegnato
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Stato
                        </th>
                        <th scope="col" class="relative px-6 py-3">
                            <span class="sr-only">Azioni</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">

                    @forelse($assignments as $assignment)
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $assignment->user->name }}
                                        </div>
                                        @php

                                        @endphp
                                        <div class="text-sm text-gray-500">
                                            {{ ucfirst($assignment->user->level ?? 'N/A') }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $assignment->tournament->name }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $assignment->tournament->club->name ?? 'N/A' }} -
                                    {{ $assignment->tournament->start_date ? Carbon\Carbon::parse($assignment->tournament->start_date)->format('d/m/Y') : 'N/A' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $assignment->role }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ $assignment->assigned_at ? Carbon\Carbon::parse($assignment->assigned_at)->format('d/m/Y H:i') : 'N/A' }}
                                </div>
                                @if ($assignment->assigned_by)
                                    <div class="text-xs text-gray-500">
                                        da {{ $assignment->assigned_by->name ?? 'N/A' }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span
                                    class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $assignment->is_confirmed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                    {{ $assignment->is_confirmed ? '✅ Confermato' : '⏳ Da confermare' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <a href="{{ route('admin.assignments.show', $assignment) }}"
                                        class="text-indigo-600 hover:text-indigo-900" title="Visualizza">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                            </path>
                                        </svg>
                                    </a>

                                    @if (!$assignment->is_confirmed)
                                        <form action="{{ route('admin.assignments.confirm', $assignment) }}" method="POST"
                                            class="inline">
                                            @csrf
                                            <button type="submit" class="text-green-600 hover:text-green-900"
                                                title="Conferma"
                                                onclick="return confirm('Confermare questa assegnazione?')">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif

                                    <a href="{{ route('admin.tournaments.show-assignment-form', $assignment->tournament) }}"
                                        class="text-indigo-600 hover:text-indigo-900" title="Invia Notifiche">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                            </path>
                                        </svg>
                                    </a>

                                    <form
                                        action="{{ route('admin.assignments.destroy', [$assignment->tournament_id, $assignment->user_id]) }}"
                                        method="POST" class="inline"
                                        onsubmit="return confirm('Sei sicuro di voler rimuovere questa assegnazione?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900" title="Rimuovi">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                                    </path>
                                </svg>
                                <p class="text-sm text-gray-400 mt-1">Prova a modificare i filtri di ricerca o seleziona un
                                    anno diverso</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $assignments->withQueryString()->links() }}
        </div>
    </div>
    <script>
let submitTimeout;

document.addEventListener('DOMContentLoaded', function() {
    const selects = document.querySelectorAll('select');

    selects.forEach(select => {
        select.addEventListener('change', function() {
            clearTimeout(submitTimeout);
            submitTimeout = setTimeout(() => {
                this.closest('form').submit();
            }, 300); // Attendi 300ms
        });
    });
});
</script>
@endsection
