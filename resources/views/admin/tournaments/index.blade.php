@extends('layouts.admin')

@section('title', 'Gestione Tornei')

@section('content')
    <div class="container mx-auto px-4 py-8">
        {{-- Header --}}
        <x-table-header title="Gestione Tornei" description="Gestisci i tornei della tua zona" :create-route="route('admin.tournaments.create')"
            create-text="Nuovo Torneo">

            {{-- Azioni aggiuntive opzionali --}}
            <x-slot name="additionalActions">
                <a href="{{ route('admin.tournaments.calendar') }}"
                    class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                    Calendario
                </a>
            </x-slot>
        </x-table-header>

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
                <p>{!! session('error') !!}</p>
            </div>
        @endif

        @if (session('warning'))
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6" role="alert">
                <p class="font-bold">Attenzione</p>
                <p class="mb-3">{{ session('warning') }}</p>
                @if (session('tournament_id'))
                    <form action="{{ route('admin.tournaments.destroy', session('tournament_id')) }}" method="POST"
                        class="inline-flex items-center gap-2">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="confirm" value="1">
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">
                            Conferma eliminazione di "{{ session('tournament_name') }}"
                        </button>
                        <a href="{{ route('admin.tournaments.index') }}"
                            class="px-4 py-2 rounded border border-gray-300 hover:bg-gray-50 text-gray-700">
                            Annulla
                        </a>
                    </form>
                @endif
            </div>
        @endif

        {{-- Filters --}}
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <form method="GET" action="{{ route('admin.tournaments.index') }}" id="filterForm"
                class="grid grid-cols-1 md:grid-cols-5 gap-4">
                {{-- Search --}}
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Cerca</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}"
                        placeholder="Nome torneo o circolo..."
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                {{-- Status Filter --}}
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                    <select name="status" id="status"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tutti gli stati</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Zone Filter (only for national admins) --}}
                @if ($isNationalAdmin)
                    <div>
                        <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                        <select name="zone_id" id="zone_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Tutte le zone</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->id }}"
                                    {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                                    {{ $zone->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Category Filter --}}
                <div>
                    <label for="tournament_type_id" class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                    <select name="tournament_type_id" id="tournament_type_id"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tutte le categorie</option>
                        @foreach ($tournamentTypes as $type)
                            <option value="{{ $type->id }}"
                                {{ request('tournament_type_id') == $type->id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Month Filter --}}
                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Mese</label>
                    <input type="month" name="month" id="month" value="{{ request('month') }}"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                {{-- Submit Button --}}
                <div class="flex items-end space-x-2">
                    <button type="submit"
                        class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition duration-200">
                        Filtra
                    </button>
                    <a href="{{ route('admin.tournaments.index') }}"
                        class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 transition duration-200">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- Tournaments Table --}}
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Torneo
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Circolo
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Categoria
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Arbitri
                        </th>
                        {{-- <th scope="col"
                            class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Stato
                        </th> --}}
                        <th scope="col" class="relative px-6 py-3">
                            <span class="sr-only">Azioni</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($tournaments as $tournament)
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $tournament->name }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    Scadenza:
                                    {{ Carbon\Carbon::parse($tournament->availability_deadline)->format('d/m/Y') }}
                                    @if ($tournament->days_until_deadline < 0)
                                        <span class="text-xs text-gray-500">(scaduta)</span>
                                    @elseif ($tournament->days_until_deadline <= 60)
                                        <span
                                            class="text-xs {{ $tournament->days_until_deadline <= 3 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                            ({{ $tournament->days_until_deadline }} giorni)
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ $tournament->start_date->format('d/m') }} -
                                    {{ $tournament->end_date->format('d/m/Y') }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $tournament->start_date->diffInDays($tournament->end_date) + 1 }} giorni
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">{{ $tournament->club->name }}</div>
                                @if ($isNationalAdmin && $tournament->club->zone)
                                    <div class="text-xs text-gray-500">{{ $tournament->club->zone->name }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 rounded-full mr-2"
                                        style="background-color: {{ $tournament->tournamentType->calendar_color }}"></div>
                                    <span class="text-sm text-gray-900">
                                        {{ $tournament->tournamentType->short_name }}
                                    </span>
                                </div>
                                @if ($tournament->tournamentType->is_national)
                                    <span class="text-xs text-blue-600">Nazionale</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="text-sm text-gray-900">
                                    @if ($tournament->assignments()->count() > 0)
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">
                                            {{ $tournament->assignments()->count() }} /
                                            {{ $tournament->tournamentType->min_referees }}
                                        </span>
                                    @endif

                                </div>
                                <div class="text-xs text-gray-500">
                                    Disp: {{ $tournament->availabilities()->count() }}
                                </div>
                            </td>
                            {{-- <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                            bg-{{ $tournament->status_color }}-100 text-{{ $tournament->status_color }}-800">
                                    {{ $tournament->status_label }}
                                </span>
                            </td> --}}
<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
    <div class="flex flex-col space-y-2 items-end">

        {{-- Prima Riga: Azione principale di gestione --}}
        <a href="{{ route('admin.tournaments.show', $tournament) }}"
            class="bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700 w-full max-w-[160px] text-center">
            🔧 Setup e Arbitri
        </a>

        {{-- Seconda Riga: Notifiche --}}
        @if($tournament->notification)
            @if($tournament->notification->is_prepared && !$tournament->notification->sent_at)
                <form action="{{ route('admin.tournament-notifications.send', $tournament) }}" method="POST" class="w-full max-w-[160px]">
                    @csrf
                    <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded text-xs hover:bg-green-700 w-full">
                        ✉️ Invia Notifica
                    </button>
                </form>
            @elseif($tournament->notification->sent_at)
                <form action="{{ route('admin.tournament-notifications.resend', $tournament) }}" method="POST" class="w-full max-w-[160px]">
                    @csrf
                    <button type="submit" class="bg-yellow-600 text-white px-3 py-1 rounded text-xs hover:bg-yellow-700 w-full">
                        🔄 Reinvia Notifica
                    </button>
                </form>
            @else
                <a href="{{ route('admin.tournaments.show-assignment-form', $tournament) }}"
                   class="prepare-notification-btn bg-indigo-600 text-white px-3 py-1 rounded text-xs hover:bg-indigo-700 w-full max-w-[160px] text-center"
                   data-tournament-id="{{ $tournament->id }}"
                   data-tournament-name="{{ $tournament->name }}"
                   data-assignments-count="{{ $tournament->assignments()->count() }}">
                    📝 Prepara Notifica
                </a>
            @endif
        @else
            <a href="{{ route('admin.tournaments.show-assignment-form', $tournament) }}"
               class="prepare-notification-btn bg-indigo-600 text-white px-3 py-1 rounded text-xs hover:bg-indigo-700 w-full max-w-[160px] text-center"
               data-tournament-id="{{ $tournament->id }}"
               data-tournament-name="{{ $tournament->name }}"
               data-assignments-count="{{ $tournament->assignments()->count() }}">
                📝 Prepara Notifica
            </a>
        @endif
    </div>
</td>                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                    </path>
                                </svg>
                                <p class="text-gray-500">Nessun torneo trovato</p>
                                <p class="text-sm text-gray-400 mt-1">Prova a modificare i filtri di ricerca</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $tournaments->withQueryString()->links() }}
        </div>

        {{-- Summary Stats --}}
        <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow p-6 text-center">
                <div class="text-3xl font-bold text-indigo-600">
                    {{ $tournaments->total() }}
                </div>
                <div class="text-sm text-gray-600 mt-1">Tornei Totali</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 text-center">
                <div class="text-3xl font-bold text-green-600">
                    {{ $tournaments->count() }}
                </div>
                <div class="text-sm text-gray-600 mt-1">Aperti</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 text-center">
                <div class="text-3xl font-bold text-yellow-600">
                    {{ $tournaments->whereIn('status', ['closed', 'assigned'])->count() }}
                </div>
                <div class="text-sm text-gray-600 mt-1">In Corso</div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 text-center">
                <div class="text-3xl font-bold text-gray-600">
                    {{ $tournaments->count() }}
                </div>
                <div class="text-sm text-gray-600 mt-1">Completati</div>
            </div>
        </div>
    </div>

    {{-- Modal per conferma assegnazioni mancanti --}}
    <div id="noAssignmentsModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100">
                    <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 text-center mt-5">Nessun Arbitro Assegnato</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500 text-center">
                        Il torneo "<span id="modalTournamentName" class="font-semibold"></span>" non ha ancora arbitri assegnati.
                    </p>
                    <p class="text-sm text-gray-500 text-center mt-2">
                        Vuoi procedere alla pagina di gestione del torneo per effettuare le assegnazioni?
                    </p>
                </div>
                <div class="items-center px-4 py-3">
                    <button id="goToSetupBtn"
                        class="px-4 py-2 bg-blue-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Vai a Setup e Arbitri
                    </button>
                    <button id="cancelModalBtn"
                        class="mt-3 px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        Annulla
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('filterForm');
            if (form) {
                // Gestisci l'invio del form
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    // Crea un nuovo FormData dal form
                    const formData = new FormData(form);
                    const params = new URLSearchParams();

                    // Aggiungi solo i parametri non vuoti
                    for (let [key, value] of formData.entries()) {
                        if (value && value.trim() !== '') {
                            params.append(key, value);
                        }
                    }

                    // Costruisci l'URL
                    const baseUrl = form.action;
                    const queryString = params.toString();
                    const finalUrl = queryString ? `${baseUrl}?${queryString}` : baseUrl;

                    // Naviga all'URL
                    window.location.href = finalUrl;
                });

                // Auto-submit quando cambia un filtro
                const inputs = form.querySelectorAll('select, input[type="text"], input[type="month"]');
                inputs.forEach(input => {
                    if (input.type !== 'text') { // Non auto-submit per il campo di ricerca
                        input.addEventListener('change', function() {
                            form.dispatchEvent(new Event('submit'));
                        });
                    }
                });
            }

            // Gestione Modal per assegnazioni mancanti
            const modal = document.getElementById('noAssignmentsModal');
            const modalTournamentName = document.getElementById('modalTournamentName');
            const goToSetupBtn = document.getElementById('goToSetupBtn');
            const cancelModalBtn = document.getElementById('cancelModalBtn');
            let currentTournamentId = null;

            // Gestisci click su tutti i bottoni "Prepara Notifica"
            document.querySelectorAll('.prepare-notification-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const assignmentsCount = parseInt(this.dataset.assignmentsCount);

                    // Se non ci sono assegnazioni, mostra il modal
                    if (assignmentsCount === 0) {
                        e.preventDefault();
                        currentTournamentId = this.dataset.tournamentId;
                        modalTournamentName.textContent = this.dataset.tournamentName;
                        modal.classList.remove('hidden');
                    }
                    // Altrimenti, lascia procedere normalmente il link
                });
            });

            // Gestisci click su "Vai a Setup e Arbitri"
            if (goToSetupBtn) {
                goToSetupBtn.addEventListener('click', function() {
                    if (currentTournamentId) {
                        window.location.href = `/admin/tournaments/${currentTournamentId}`;
                    }
                });
            }

            // Gestisci click su "Annulla" o click fuori dal modal
            if (cancelModalBtn) {
                cancelModalBtn.addEventListener('click', function() {
                    modal.classList.add('hidden');
                    currentTournamentId = null;
                });
            }

            // Chiudi modal cliccando sull'overlay
            modal?.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                    currentTournamentId = null;
                }
            });

            // Chiudi modal con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    modal.classList.add('hidden');
                    currentTournamentId = null;
                }
            });
        });
    </script>
@endpush
