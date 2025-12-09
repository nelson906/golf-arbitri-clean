@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h2 class="text-2xl font-semibold mb-6">Benvenuto, {{ $user->name }}!</h2>

                    {{-- Stats Grid --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        {{-- Assegnazioni Anno Corrente --}}
                        <div class="bg-blue-50 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="flex-1">
                                    <p class="text-sm text-blue-600 font-medium">Assegnazioni {{ date('Y') }}</p>
                                    <p class="text-2xl font-bold text-blue-900">{{ $stats->assignments_this_year ?? 0 }}</p>
                                </div>
                                <svg class="w-12 h-12 text-blue-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                    </path>
                                </svg>
                            </div>
                        </div>

                        {{-- Assegnazioni Totali --}}
                        <div class="bg-green-50 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="flex-1">
                                    <p class="text-sm text-green-600 font-medium">Assegnazioni Totali</p>
                                    <p class="text-2xl font-bold text-green-900">{{ $stats->total_assignments ?? 0 }}</p>
                                </div>
                                <svg class="w-12 h-12 text-green-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>

                        {{-- Livello Arbitro --}}
                        <div class="bg-purple-50 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="flex-1">
                                    <p class="text-sm text-purple-600 font-medium">Livello</p>
                                    <p class="text-lg font-bold text-purple-900">
                                        {{ ucfirst($user->level ?? 'Non specificato') }}</p>
                                </div>
                                <svg class="w-12 h-12 text-purple-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                        </div>

                        {{-- Zona --}}
                        <div class="bg-yellow-50 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="flex-1">
                                    <p class="text-sm text-yellow-600 font-medium">Zona</p>
                                    <p class="text-lg font-bold text-yellow-900">{{ $user->zone->name ?? 'Non assegnata' }}
                                    </p>
                                </div>
                                <svg class="w-12 h-12 text-yellow-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                    </path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Quick Actions --}}
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold mb-4">Azioni Rapide</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <a href="{{ route('user.availability.tournaments') }}"
                                class="block bg-blue-600 text-white text-center py-3 px-6 rounded-lg hover:bg-blue-700 transition">
                                Dichiara Disponibilità
                            </a>
                            <a href="{{ route('user.availability.index') }}"
                                class="block bg-gray-600 text-white text-center py-3 px-6 rounded-lg hover:bg-gray-700 transition">
                                Le Mie Disponibilità
                            </a>
                            <a href="{{ route('user.availability.calendar') }}"
                                class="block bg-green-600 text-white text-center py-3 px-6 rounded-lg hover:bg-green-700 transition">
                                Calendario
                            </a>
                        </div>
                    </div>


                    {{-- Tornei Aperti per Disponibilita --}}
                    @if ($openTournaments->isNotEmpty())
                        <div class="mb-8 bg-amber-50 border border-amber-200 rounded-lg p-4">
                            <h3 class="text-lg font-semibold mb-4 text-amber-800">Tornei Aperti per Disponibilita</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach ($openTournaments->take(6) as $tournament)
                                    <div class="bg-white border rounded-lg p-3">
                                        <h4 class="font-medium text-sm">{{ $tournament->name }}</h4>
                                        <p class="text-xs text-gray-600">{{ $tournament->start_date->format('d/m/Y') }}</p>
                                        <p class="text-xs text-gray-500">{{ $tournament->club->name ?? 'N/A' }}</p>
                                        <p class="text-xs text-amber-600 mt-1">Scadenza:
                                            {{ Carbon\Carbon::parse($tournament->availability_deadline)?->format('d/m/Y') ?? 'N/A' }}</p>
                                    </div>
                                @endforeach
                            </div>
                            @if ($openTournaments->count() > 6)
                                <div class="mt-3 text-center">
                                    <a href="{{ route('user.availability.tournaments') }}"
                                        class="text-amber-700 hover:text-amber-900 text-sm">
                                        Vedi tutti i {{ $openTournaments->count() }} tornei aperti &rarr;
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                        {{-- Prossimi Tornei Assegnati --}}
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Prossimi Tornei Assegnati</h3>
                            @if ($upcomingAssignments->isEmpty())
                                <p class="text-gray-500">Nessun torneo assegnato in programma.</p>
                            @else
                                <div class="space-y-3">
                                    @foreach ($upcomingAssignments as $assignment)
                                        <div class="border rounded-lg p-4 hover:bg-gray-50">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <h4 class="font-medium">{{ $assignment->tournament->name }}</h4>
                                                    <p class="text-sm text-gray-600">
                                                        {{ $assignment->tournament->start_date->format('d/m/Y') }}
                                                        @if (
                                                            $assignment->tournament->end_date &&
                                                                $assignment->tournament->start_date->format('Y-m-d') !== $assignment->tournament->end_date->format('Y-m-d'))
                                                            - {{ $assignment->tournament->end_date->format('d/m/Y') }}
                                                        @endif
                                                    </p>
                                                    <p class="text-sm text-gray-500">
                                                        {{ $assignment->tournament->club->name ?? 'N/A' }}</p>
                                                </div>
                                                <span
                                                    class="px-2 py-1 text-xs rounded-full {{ $assignment->is_confirmed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                    {{ $assignment->is_confirmed ? 'Confermato' : 'In attesa' }}
                                                </span>
                                            </div>
                                            @if ($assignment->role)
                                                <p class="text-sm text-indigo-600 mt-1">Ruolo: {{ $assignment->role }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Disponibilità in Attesa --}}
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Disponibilita in Attesa di Assegnazione</h3>
                            @if ($pendingAvailabilities->isEmpty())
                                <p class="text-gray-500">Nessuna disponibilita in attesa.</p>
                            @else
                                <div class="space-y-3">
                                    @foreach ($pendingAvailabilities as $availability)
                                        <div class="border rounded-lg p-4 hover:bg-gray-50">
                                            <h4 class="font-medium">{{ $availability->tournament->name }}</h4>
                                            <p class="text-sm text-gray-600">
                                                {{ $availability->tournament->start_date->format('d/m/Y') }}
                                                @if (
                                                    $availability->tournament->end_date &&
                                                        $availability->tournament->start_date->format('Y-m-d') !== $availability->tournament->end_date->format('Y-m-d'))
                                                    - {{ $availability->tournament->end_date->format('d/m/Y') }}
                                                @endif
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                {{ $availability->tournament->club->name ?? 'N/A' }}</p>
                                            <p class="text-xs text-blue-600 mt-1"> Dichiarata il
                                                {{ $availability->created_at->format('d/m/Y') }}
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Assegnazioni Recenti --}}
                    @if ($recentAssignments->isNotEmpty())
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold mb-4">Tornei Completati Recentemente</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach ($recentAssignments as $assignment)
                                    <div class="border rounded-lg p-3 bg-gray-50">
                                        <h4 class="font-medium text-sm">{{ $assignment->tournament->name }}</h4>
                                        <p class="text-xs text-gray-600">
                                            {{ $assignment->tournament->start_date->format('d/m/Y') }}
                                            @if ($assignment->tournament->end_date)
                                                - {{ $assignment->tournament->end_date->format('d/m/Y') }}
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ $assignment->tournament->club->name ?? 'N/A' }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Statistiche per Tipo Torneo --}}
                    @if (!empty($assignmentsByType))
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold mb-4">Assegnazioni {{ date('Y') }} per Tipo Torneo</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($assignmentsByType as $type => $count)
                                    <span class="px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-sm">
                                        {{ $type }}: {{ $count }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Calendario Mini --}}
                    @if (!empty($calendarEvents))
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h3 class="text-lg font-semibold mb-4">Prossimi Impegni (3 mesi)</h3>
                            <div id="mini-calendar"></div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if (!empty($calendarEvents))
        @push('styles')
            <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
            <style>
                #mini-calendar {
                    max-width: 100%;
                }

                .fc-event {
                    cursor: pointer;
                }
            </style>
        @endpush

        @push('scripts')
            <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var calendarEl = document.getElementById('mini-calendar');
                    if (calendarEl) {
                        var calendar = new FullCalendar.Calendar(calendarEl, {
                            initialView: 'listMonth',
                            locale: 'it',
                            height: 'auto',
                            headerToolbar: {
                                left: 'prev,next',
                                center: 'title',
                                right: 'listMonth,dayGridMonth'
                            },
                            events: @json($calendarEvents),
                            eventClick: function(info) {
                                // Optional: navigate to tournament details
                            }
                        });
                        calendar.render();
                    }
                });
            </script>
        @endpush
    @endif
@endsection
