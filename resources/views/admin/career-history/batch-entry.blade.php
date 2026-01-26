@extends('layouts.admin')

@section('title', 'Inserimento Multiplo ' . $year . ' - ' . $user->name)

@section('content')
    <div class="container mx-auto px-4 py-8" x-data="batchEntry()">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Inserimento Multiplo - {{ $user->name }}</h1>
                <p class="mt-1 text-sm text-gray-600">Anno {{ $year }} - Aggiungi più tornei velocemente</p>
            </div>
            <a href="{{ route('admin.career-history.edit-year', [$user, $year]) }}"
                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                ← Torna a Modifica Anno
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
        @if ($errors->any())
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Lista tornei selezionati --}}
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900">
                                Tornei Selezionati
                                <span class="text-sm font-normal text-gray-500">(<span x-text="tournaments.length"></span>)</span>
                            </h3>
                            <button type="button" 
                                @click="clearAll()"
                                x-show="tournaments.length > 0"
                                class="text-sm text-red-600 hover:text-red-900">
                                🗑️ Svuota Lista
                            </button>
                        </div>
                    </div>

                    <div x-show="tournaments.length === 0" class="px-6 py-8 text-center text-gray-500">
                        Nessun torneo selezionato. Usa il form a destra per aggiungerne.
                    </div>

                    <div x-show="tournaments.length > 0" class="divide-y divide-gray-200">
                        <template x-for="(tournament, index) in tournaments" :key="index">
                            <div class="px-4 py-4 hover:bg-gray-50">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm font-medium text-gray-900" x-text="(index + 1) + '. ' + tournament.name"></span>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            <span x-text="tournament.club_name"></span> • 
                                            <span x-text="tournament.date_range"></span>
                                        </div>
                                        <div class="mt-2 flex items-center space-x-3">
                                            <div>
                                                <span class="text-xs text-gray-500">Ruolo:</span>
                                                <span class="ml-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800" 
                                                    x-text="tournament.role || 'Nessuno'"></span>
                                            </div>
                                            <div>
                                                <span class="text-xs text-gray-500">Giorni:</span>
                                                <span class="ml-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800" 
                                                    x-text="tournament.days_count + ' / ' + tournament.total_days"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2 ml-4">
                                        <button type="button" 
                                            @click="editTournament(index)"
                                            class="text-blue-600 hover:text-blue-900 text-sm"
                                            title="Modifica">
                                            ✏️
                                        </button>
                                        <button type="button" 
                                            @click="removeTournament(index)"
                                            class="text-red-600 hover:text-red-900 text-sm"
                                            title="Rimuovi">
                                            ✕
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Riepilogo --}}
                    <div x-show="tournaments.length > 0" class="px-4 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div>
                                <div class="text-2xl font-bold text-gray-900" x-text="tournaments.length"></div>
                                <div class="text-xs text-gray-500">Tornei</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900" x-text="getTotalDays()"></div>
                                <div class="text-xs text-gray-500">Giorni Totali</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900" x-text="getAvgDays()"></div>
                                <div class="text-xs text-gray-500">Media/Torneo</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Form aggiunta torneo --}}
            <div>
                <div class="bg-white rounded-lg shadow">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Aggiungi Torneo</h3>
                    </div>
                    <div class="p-4">
                        @if ($availableTournaments->count() > 0)
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Torneo</label>
                                    <select x-model="currentTournamentId" 
                                        @change="loadTournamentData()"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">Seleziona...</option>
                                        @foreach ($availableTournaments as $t)
                                            <option value="{{ $t->id }}"
                                                data-name="{{ $t->name }}"
                                                data-club="{{ $t->club->name ?? 'N/A' }}"
                                                data-start="{{ $t->start_date->format('Y-m-d') }}"
                                                data-end="{{ $t->end_date->format('Y-m-d') }}"
                                                data-start-fmt="{{ $t->start_date->format('d/m') }}"
                                                data-end-fmt="{{ $t->end_date->format('d/m') }}">
                                                {{ $t->start_date->format('d/m') }} - {{ $t->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div x-show="currentTournamentId">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruolo</label>
                                    <select x-model="currentRole"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">Nessun ruolo</option>
                                        <option value="Arbitro">Arbitro</option>
                                        <option value="Direttore di Torneo">Direttore di Torneo</option>
                                        <option value="Osservatore">Osservatore</option>
                                    </select>
                                </div>

                                <div x-show="currentTournamentId">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Giorni Effettivi
                                        <span class="text-xs text-gray-500" x-show="currentTotalDays > 0">
                                            (torneo di <span x-text="currentTotalDays"></span> giorni)
                                        </span>
                                    </label>
                                    
                                    {{-- Slider visuale --}}
                                    <div class="flex items-center space-x-2 mb-2">
                                        <template x-for="day in currentTotalDays" :key="day">
                                            <div class="flex-1 h-8 rounded cursor-pointer transition-colors"
                                                :class="day <= currentDaysCount ? 'bg-green-500' : 'bg-gray-200'"
                                                @click="currentDaysCount = day">
                                            </div>
                                        </template>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2">
                                        <input type="number" 
                                            x-model.number="currentDaysCount"
                                            :max="currentTotalDays"
                                            min="1"
                                            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <span class="text-sm text-gray-500">/ <span x-text="currentTotalDays"></span></span>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Clicca sulle barre sopra o digita il numero
                                    </p>
                                </div>

                                <button type="button"
                                    @click="addTournament()"
                                    :disabled="!currentTournamentId"
                                    class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white px-4 py-2 rounded-md text-sm font-medium">
                                    ➕ Aggiungi alla Lista
                                </button>
                            </div>
                        @else
                            <p class="text-sm text-gray-500 text-center py-4">
                                Nessun torneo disponibile per il {{ $year }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Pulsanti azione --}}
        <div class="mt-6 flex items-center justify-between">
            <a href="{{ route('admin.career-history.edit-year', [$user, $year]) }}"
                class="px-6 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                🔙 Annulla
            </a>
            
            <button type="button"
                @click="submitBatch()"
                x-show="tournaments.length > 0"
                class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm font-medium">
                💾 Salva Tutto (<span x-text="tournaments.length"></span> tornei)
            </button>
        </div>

        {{-- Form hidden per submit --}}
        <form id="batchForm" method="POST" action="{{ route('admin.career-history.batch-save', $user) }}" style="display: none;">
            @csrf
            <input type="hidden" name="year" value="{{ $year }}">
            <input type="hidden" name="tournaments" x-model="tournamentsJson()">
        </form>
    </div>

    <script>
    function batchEntry() {
        return {
            tournaments: [],
            currentTournamentId: '',
            currentRole: '',
            currentDaysCount: 1,
            currentTotalDays: 0,
            
            loadTournamentData() {
                if (!this.currentTournamentId) return;
                
                const select = document.querySelector('select[x-model="currentTournamentId"]');
                const option = select.options[select.selectedIndex];
                
                const startDate = new Date(option.dataset.start);
                const endDate = new Date(option.dataset.end);
                this.currentTotalDays = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;
                this.currentDaysCount = this.currentTotalDays;
            },
            
            addTournament() {
                if (!this.currentTournamentId) return;
                
                const select = document.querySelector('select[x-model="currentTournamentId"]');
                const option = select.options[select.selectedIndex];
                
                this.tournaments.push({
                    tournament_id: parseInt(this.currentTournamentId),
                    name: option.dataset.name,
                    club_name: option.dataset.club,
                    date_range: option.dataset.startFmt + ' - ' + option.dataset.endFmt,
                    total_days: this.currentTotalDays,
                    days_count: this.currentDaysCount,
                    role: this.currentRole
                });
                
                // Reset form
                this.currentTournamentId = '';
                this.currentRole = '';
                this.currentDaysCount = 1;
                this.currentTotalDays = 0;
            },
            
            removeTournament(index) {
                this.tournaments.splice(index, 1);
            },
            
            editTournament(index) {
                const tournament = this.tournaments[index];
                // Rimuovi e ripopola form
                this.tournaments.splice(index, 1);
                this.currentTournamentId = tournament.tournament_id.toString();
                this.currentRole = tournament.role;
                this.currentDaysCount = tournament.days_count;
                this.currentTotalDays = tournament.total_days;
            },
            
            clearAll() {
                if (confirm('Vuoi davvero svuotare la lista?')) {
                    this.tournaments = [];
                }
            },
            
            getTotalDays() {
                return this.tournaments.reduce((sum, t) => sum + t.days_count, 0);
            },
            
            getAvgDays() {
                if (this.tournaments.length === 0) return 0;
                return (this.getTotalDays() / this.tournaments.length).toFixed(1);
            },
            
            tournamentsJson() {
                return JSON.stringify(this.tournaments);
            },
            
            submitBatch() {
                if (this.tournaments.length === 0) {
                    alert('Nessun torneo da salvare!');
                    return;
                }
                
                if (confirm(`Confermi di voler aggiungere ${this.tournaments.length} tornei allo storico?`)) {
                    document.getElementById('batchForm').submit();
                }
            }
        }
    }
    </script>
@endsection
