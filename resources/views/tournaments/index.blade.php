@extends(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']) ? 'layouts.admin' : 'layouts.app')

@section('title', 'Lista Tornei')

@section('content')
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Header --}}
            <div class="mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Lista Tornei</h1>
                        <p class="mt-1 text-sm text-gray-600">
                            Visualizza tutti i tornei disponibili ({{ $tournaments->count() }} tornei)
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <a href="#today-section"
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            📍 Vai a Oggi
                        </a>
                        <a href="{{ route('tournaments.calendar') }}"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            📅 Vista Calendario
                        </a>
                    </div>
                </div>
            </div>

            {{-- Filters --}}
            <div class="mb-6 bg-white rounded-lg shadow p-4">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Cerca</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}"
                            placeholder="Nome torneo o circolo..."
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>

                    <div>
                        <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                        <select name="zone_id" id="zone_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">Tutte le zone</option>
                            @foreach (\App\Models\Zone::orderBy('name')->get() as $zone)
                                <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                                    {{ $zone->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Mese</label>
                        <input type="month" name="month" id="month" value="{{ request('month') }}"
                            class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit"
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            Filtra
                        </button>
                        <a href="{{ route('tournaments.index') }}"
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            {{-- Timeline per giorni --}}
            <div class="space-y-2">
                @forelse($tournamentsByDate as $dateKey => $dayTournaments)
                    @php
                        $date = \Carbon\Carbon::parse($dateKey);
                        $isToday = $date->isToday();
                        $isPast = $date->isPast() && !$isToday;
                        $isWeekend = $date->isWeekend();
                    @endphp

                    {{-- Separatore per oggi --}}
                    @if($isToday)
                        <div id="today-section" class="relative py-4">
                            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                                <div class="w-full border-t-2 border-green-500"></div>
                            </div>
                            <div class="relative flex justify-center">
                                <span class="bg-green-500 text-white px-4 py-1 rounded-full text-sm font-bold">
                                    📍 OGGI - {{ $date->translatedFormat('l d F Y') }}
                                </span>
                            </div>
                        </div>
                    @endif

                    {{-- Giorno con o senza tornei --}}
                    <div class="rounded-lg {{ $isToday ? 'bg-green-50 border-2 border-green-300' : ($isPast ? 'bg-gray-50' : 'bg-white') }} {{ $isWeekend ? 'border-l-4 border-l-blue-400' : '' }} shadow-sm">
                        {{-- Header giorno --}}
                        <div class="px-4 py-2 {{ $dayTournaments->isEmpty() ? '' : 'border-b border-gray-200' }} flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-lg font-bold {{ $isToday ? 'text-green-700' : ($isPast ? 'text-gray-400' : 'text-gray-700') }}">
                                    {{ $date->format('d') }}
                                </span>
                                <span class="text-sm {{ $isToday ? 'text-green-600' : ($isPast ? 'text-gray-400' : 'text-gray-600') }}">
                                    {{ $date->translatedFormat('l') }}
                                    <span class="text-xs">{{ $date->translatedFormat('F Y') }}</span>
                                </span>
                            </div>
                            @if($dayTournaments->isNotEmpty())
                                <span class="text-xs font-medium px-2 py-1 rounded-full {{ $isToday ? 'bg-green-200 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $dayTournaments->count() }} {{ $dayTournaments->count() == 1 ? 'torneo' : 'tornei' }}
                                </span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </div>

                        {{-- Lista tornei del giorno --}}
                        @if($dayTournaments->isNotEmpty())
                            <div class="divide-y divide-gray-100">
                                @foreach($dayTournaments as $tournament)
                                    <div class="px-4 py-3 hover:bg-gray-50 transition-colors duration-150 flex items-center justify-between">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <div class="w-3 h-3 rounded-full flex-shrink-0"
                                                    style="background-color: {{ $tournament->tournamentType->calendar_color }}">
                                                </div>
                                                <span class="text-sm font-medium text-gray-900 truncate">
                                                    {{ $tournament->name }}
                                                </span>
                                                @if ($tournament->tournamentType->is_national)
                                                    <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">Nazionale</span>
                                                @endif
                                            </div>
                                            <div class="mt-1 flex items-center gap-4 text-xs text-gray-500">
                                                <span>🏌️ {{ $tournament->club->name }}</span>
                                                <span>📅 {{ $tournament->start_date->format('d/m') }} - {{ $tournament->end_date->format('d/m') }}</span>
                                                <span>🏷️ {{ $tournament->tournamentType->short_name }}</span>
                                                @if ($isNationalAdmin && $tournament->club->zone)
                                                    <span>🌍 {{ $tournament->club->zone->name }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-3 ml-4">
                                            {{-- Stato arbitri --}}
                                            <div class="text-center">
                                                <div class="text-sm font-medium {{ $tournament->assignments()->count() >= $tournament->tournamentType->min_referees ? 'text-green-600' : 'text-gray-600' }}">
                                                    {{ $tournament->assignments()->count() }}/{{ $tournament->tournamentType->min_referees }}
                                                </div>
                                                <div class="text-xs text-gray-400">arbitri</div>
                                            </div>

                                            {{-- Status badge --}}
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-{{ $tournament->status_color }}-100 text-{{ $tournament->status_color }}-800">
                                                {{ $tournament->status_label }}
                                            </span>

                                            {{-- Azioni --}}
                                            <a href="{{ route('tournaments.show', $tournament) }}"
                                                class="text-indigo-600 hover:text-indigo-900 text-sm font-medium whitespace-nowrap">
                                                Dettagli →
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="bg-white rounded-lg shadow p-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                            </path>
                        </svg>
                        <p class="text-gray-500">Nessun torneo trovato</p>
                        <p class="text-sm text-gray-400 mt-1">Prova a modificare i filtri di ricerca</p>
                    </div>
                @endforelse
            </div>

            {{-- Stats footer --}}
            @if($tournaments->isNotEmpty())
                <div class="mt-6 bg-white rounded-lg shadow p-4">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                        <div>
                            <div class="text-2xl font-bold text-indigo-600">{{ $tournaments->count() }}</div>
                            <div class="text-xs text-gray-500">Tornei totali</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-green-600">{{ $tournaments->where('status', 'open')->count() }}</div>
                            <div class="text-xs text-gray-500">Aperti</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-blue-600">{{ $tournaments->where('start_date', '>=', $today)->count() }}</div>
                            <div class="text-xs text-gray-500">Futuri</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-600">{{ $tournamentsByDate->count() }}</div>
                            <div class="text-xs text-gray-500">Giorni mostrati</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        // Scroll automatico a oggi al caricamento della pagina
        document.addEventListener('DOMContentLoaded', function() {
            const todaySection = document.getElementById('today-section');
            if (todaySection) {
                setTimeout(() => {
                    todaySection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            }
        });
    </script>
    @endpush
@endsection
