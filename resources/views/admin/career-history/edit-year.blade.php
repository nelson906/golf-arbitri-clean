@extends('layouts.admin')

@section('title', 'Modifica ' . $year . ' - ' . $user->name)

@section('content')
    <div class="container mx-auto px-4 py-8">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $user->name }} - Anno {{ $year }}</h1>
                <p class="mt-1 text-sm text-gray-600">Modifica lo storico per questo anno</p>
            </div>
            <a href="{{ route('admin.career-history.show', $user) }}"
                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                Torna allo Storico
            </a>
        </div>

        {{-- Alert --}}
        @if (session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                {{ session('success') }}
            </div>
        @endif
        @if (session('warning'))
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                {{ session('warning') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Tornei nello storico --}}
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">
                            Tornei {{ $year }}
                            <span class="text-sm font-normal text-gray-500">({{ count($tournaments) }})</span>
                        </h3>
                    </div>

                    @if (count($tournaments) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Torneo
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Club
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Giorni
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ruolo
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($tournaments as $tournament)
                                        @php
                                            $assignment = collect($assignments)->firstWhere(
                                                'tournament_id',
                                                $tournament['id'],
                                            );
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $tournament['name'] }}
                                                </div>
                                                <div class="text-xs text-gray-500">ID: {{ $tournament['id'] }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                {{ $tournament['club_name'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                {{ \Carbon\Carbon::parse($tournament['start_date'])->format('d/m') }}
                                                @if ($tournament['end_date'] != $tournament['start_date'])
                                                    - {{ \Carbon\Carbon::parse($tournament['end_date'])->format('d/m') }}
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @php
                                                    $startDate = \Carbon\Carbon::parse($tournament['start_date']);
                                                    $endDate = \Carbon\Carbon::parse($tournament['end_date']);
                                                    $totalDays = $startDate->diffInDays($endDate) + 1;
                                                    $daysCount = $tournament['days_count'] ?? $totalDays;
                                                @endphp
                                                <div class="flex items-center space-x-2">
                                                    <span class="text-sm {{ $daysCount < $totalDays ? 'text-orange-600 font-semibold' : 'text-gray-700' }}">
                                                        {{ $daysCount }}
                                                        @if ($daysCount < $totalDays)
                                                            <span class="text-xs text-gray-500">/ {{ $totalDays }}</span>
                                                        @endif
                                                    </span>
                                                    <button type="button" 
                                                        onclick='openEditTournamentModal({{ $tournament["id"] }}, {{ json_encode($tournament["name"]) }}, {{ $totalDays }}, {{ $daysCount }}, {{ json_encode($tournament) }})'
                                                        class="text-blue-600 hover:text-blue-900 text-xs"
                                                        title="Modifica torneo">
                                                        ✏️
                                                    </button>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @if ($assignment)
                                                    <span
                                                        class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        {{ $assignment['role'] ?? 'N/A' }}
                                                    </span>
                                                @else
                                                    <span class="text-xs text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                                <form action="{{ route('admin.career-history.remove-tournament', $user) }}"
                                                    method="POST" class="inline"
                                                    onsubmit="return confirm('Rimuovere questo torneo dallo storico?')">
                                                    @csrf
                                                    <input type="hidden" name="year" value="{{ $year }}">
                                                    <input type="hidden" name="tournament_id"
                                                        value="{{ $tournament['id'] }}">
                                                    <button type="submit" class="text-red-600 hover:text-red-900 text-sm">
                                                        Rimuovi
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="px-6 py-8 text-center text-gray-500">
                            Nessun torneo per questo anno
                        </div>
                    @endif
                </div>
            </div>

            {{-- Aggiungi tornei --}}
            <div>
                {{-- Aggiungi singolo torneo --}}
                <div class="bg-white rounded-lg shadow">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Aggiungi Torneo</h3>
                    </div>
                    <div class="p-4">
                        @if ($availableTournaments->count() > 0)
                            <form action="{{ route('admin.career-history.add-tournament', $user) }}" method="POST">
                                @csrf
                                <input type="hidden" name="year" value="{{ $year }}">

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Torneo</label>
                                        <select name="tournament_id" required
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            id="tournament-select"
                                            onchange="updateDaysInfo(this)">
                                            <option value="">Seleziona...</option>
                                            @foreach ($availableTournaments as $t)
                                                <option value="{{ $t->id }}" 
                                                    data-start="{{ $t->start_date->format('Y-m-d') }}"
                                                    data-end="{{ $t->end_date->format('Y-m-d') }}">
                                                    {{ $t->start_date->format('d/m') }} - {{ $t->name }}
                                                    ({{ $t->club->name ?? 'N/A' }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Giorni Effettivi
                                            <span class="text-xs text-gray-500" id="days-hint"></span>
                                        </label>
                                        <input type="number" 
                                            name="days_count" 
                                            id="days-count"
                                            min="1" 
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            placeholder="Es. 3">
                                        <p class="mt-1 text-xs text-gray-500">
                                            Lascia vuoto per conteggiare automaticamente tutti i giorni del torneo
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Ruolo
                                            (opzionale)</label>
                                        <select name="role"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">Nessun ruolo</option>
                                            <option value="Arbitro">Arbitro</option>
                                            <option value="Direttore di Torneo">Direttore di Torneo</option>
                                            <option value="Osservatore">Osservatore</option>
                                        </select>
                                    </div>

                                    <script>
                                    function updateDaysInfo(select) {
                                        const option = select.options[select.selectedIndex];
                                        const startDate = option.dataset.start;
                                        const endDate = option.dataset.end;
                                        const hint = document.getElementById('days-hint');
                                        const daysInput = document.getElementById('days-count');
                                        
                                        if (startDate && endDate) {
                                            const start = new Date(startDate);
                                            const end = new Date(endDate);
                                            const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
                                            hint.textContent = `(torneo di ${days} ${days === 1 ? 'giorno' : 'giorni'})`;
                                            daysInput.max = days;
                                            daysInput.placeholder = `Es. ${Math.min(days, 3)}`;
                                        } else {
                                            hint.textContent = '';
                                            daysInput.max = '';
                                        }
                                    }
                                    </script>

                                    <button type="submit"
                                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                        Aggiungi
                                    </button>
                                </div>
                            </form>
                        @else
                            <p class="text-sm text-gray-500 text-center py-4">
                                Nessun altro torneo disponibile per il {{ $year }}
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Aggiungi piu tornei --}}
                @if ($availableTournaments->count() > 1)
                    <div class="bg-white rounded-lg shadow mt-4">
                        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Aggiungi Piu Tornei</h3>
                            <p class="text-xs text-gray-500 mt-1">Seleziona piu tornei da aggiungere insieme</p>
                        </div>
                        <div class="p-4">
                            <form action="{{ route('admin.career-history.add-multiple-tournaments', $user) }}"
                                method="POST">
                                @csrf
                                <input type="hidden" name="year" value="{{ $year }}">

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Tornei</label>
                                        <select name="tournament_ids[]" multiple required
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            size="{{ min($availableTournaments->count(), 8) }}">
                                            @foreach ($availableTournaments as $t)
                                                <option value="{{ $t->id }}">
                                                    {{ $t->start_date->format('d/m') }} - {{ $t->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1">Tieni premuto Ctrl/Cmd per selezionare piu
                                            tornei</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Ruolo per tutti
                                            (opzionale)</label>
                                        <select name="role"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">Nessun ruolo</option>
                                            <option value="Arbitro">Arbitro</option>
                                            <option value="Arbitro Principale">Arbitro Principale</option>
                                            <option value="Arbitro di Supporto">Arbitro di Supporto</option>
                                            <option value="Starter">Starter</option>
                                            <option value="Referee">Referee</option>
                                        </select>
                                    </div>

                                    <button type="submit"
                                        class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                        Aggiungi Selezionati
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- Riepilogo --}}
                <div class="bg-gray-50 rounded-lg p-4 mt-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Riepilogo {{ $year }}</h4>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Tornei:</dt>
                            <dd class="font-medium text-gray-900">{{ count($tournaments) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Assegnazioni:</dt>
                            <dd class="font-medium text-gray-900">{{ count($assignments) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Disponibilita:</dt>
                            <dd class="font-medium text-gray-900">{{ count($availabilities) }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Link a Batch Entry --}}
                @if ($availableTournaments->count() > 3)
                    <div class="mt-4">
                        <a href="{{ route('admin.career-history.batch-entry', [$user, $year]) }}"
                            class="block w-full text-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            ➕ Inserimento Multiplo Veloce
                        </a>
                        <p class="mt-1 text-xs text-center text-gray-500">
                            Consigliato per aggiungere molti tornei contemporaneamente
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal per modifica torneo completo --}}
    <div id="editTournamentModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <form id="editTournamentForm" method="POST" action="{{ route('admin.career-history.update-tournament', $user) }}">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="tournament_id" id="modal-tournament-id">
                
                <div class="px-6 py-4 border-b border-gray-200 sticky top-0 bg-white">
                    <h3 class="text-lg font-medium text-gray-900">Modifica Dati Torneo</h3>
                    <p class="mt-1 text-sm text-gray-500">Correggi eventuali errori nei dati del torneo</p>
                </div>
                
                <div class="px-6 py-4 space-y-4">
                    {{-- Nome Torneo --}}
                    <div>
                        <label for="modal-name" class="block text-sm font-medium text-gray-700 mb-1">
                            Nome Torneo
                        </label>
                        <input type="text" 
                            name="name" 
                            id="modal-name"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    {{-- Club --}}
                    <div>
                        <label for="modal-club-name" class="block text-sm font-medium text-gray-700 mb-1">
                            Club
                        </label>
                        <input type="text" 
                            name="club_name" 
                            id="modal-club-name"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    {{-- Date --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="modal-start-date" class="block text-sm font-medium text-gray-700 mb-1">
                                Data Inizio
                            </label>
                            <input type="date" 
                                name="start_date" 
                                id="modal-start-date"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="modal-end-date" class="block text-sm font-medium text-gray-700 mb-1">
                                Data Fine
                            </label>
                            <input type="date" 
                                name="end_date" 
                                id="modal-end-date"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    {{-- Giorni Effettivi --}}
                    <div>
                        <label for="modal-days-count" class="block text-sm font-medium text-gray-700 mb-1">
                            Giorni Effettivi Arbitrati
                            <span class="text-xs text-gray-500" id="modal-days-hint"></span>
                        </label>
                        <input type="number" 
                            name="days_count" 
                            id="modal-days-count"
                            min="1" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">
                            Quanti giorni hai effettivamente prestato servizio
                        </p>
                    </div>
                </div>
                
                <div class="px-6 py-4 bg-gray-50 flex justify-end space-x-3 rounded-b-lg sticky bottom-0">
                    <button type="button" 
                        onclick="closeEditTournamentModal()"
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Annulla
                    </button>
                    <button type="submit"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        💾 Salva Modifiche
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEditTournamentModal(tournamentId, tournamentName, totalDays, currentDays, tournament) {
        // Popola i campi
        document.getElementById('modal-tournament-id').value = tournamentId;
        document.getElementById('modal-name').value = tournamentName;
        document.getElementById('modal-days-count').value = currentDays;
        document.getElementById('modal-days-count').max = totalDays;
        
        // Hint giorni totali
        document.getElementById('modal-days-hint').textContent = `(torneo di ${totalDays} giorni)`;
        
        // Popola altri campi se disponibili
        if (tournament.club_name) {
            document.getElementById('modal-club-name').value = tournament.club_name;
        }
        if (tournament.start_date) {
            document.getElementById('modal-start-date').value = tournament.start_date;
        }
        if (tournament.end_date) {
            document.getElementById('modal-end-date').value = tournament.end_date;
        }
        
        // Mostra modal
        document.getElementById('editTournamentModal').classList.remove('hidden');
        
        // Auto-calcola giorni totali quando cambiano le date
        updateTotalDaysFromDates();
    }

    function closeEditTournamentModal() {
        document.getElementById('editTournamentModal').classList.add('hidden');
    }

    function updateTotalDaysFromDates() {
        const startInput = document.getElementById('modal-start-date');
        const endInput = document.getElementById('modal-end-date');
        const daysInput = document.getElementById('modal-days-count');
        const hint = document.getElementById('modal-days-hint');
        
        startInput.addEventListener('change', calculateDays);
        endInput.addEventListener('change', calculateDays);
        
        function calculateDays() {
            const start = new Date(startInput.value);
            const end = new Date(endInput.value);
            
            if (start && end && start <= end) {
                const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
                daysInput.max = days;
                hint.textContent = `(torneo di ${days} giorni)`;
                
                // Se giorni attuali > nuova durata, aggiusta
                if (parseInt(daysInput.value) > days) {
                    daysInput.value = days;
                }
            }
        }
    }

    // Chiudi modal con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditTournamentModal();
        }
    });

    // Chiudi modal cliccando fuori
    document.getElementById('editTournamentModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditTournamentModal();
        }
    });
    </script>
@endsection
