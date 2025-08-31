@extends('layouts.admin')

@section('title', 'Torneo: ' . $tournament->name)

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $tournament->name }}</h1>
                <p class="mt-1 text-gray-600">
                    {{ $tournament->club->name }} - {{ $tournament->start_date->format('d/m/Y') }} - {{ $tournament->end_date->format('d/m/Y') }}
                </p>
            </div>
            <a href="{{ route('tournaments.index') }}"
               class="text-gray-600 hover:text-gray-900 flex items-center px-4 py-2">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Torna all'elenco
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Content --}}
        <div class="lg:col-span-2">
            {{-- Tournament Details --}}
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Dettagli Torneo</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Categoria</dt>
                        <dd class="mt-1 text-sm text-gray-900 flex items-center">
                            <div class="w-3 h-3 rounded-full mr-2"
                                 style="background-color: {{ $tournament->tournamentCategory->calendar_color }}"></div>
                            {{ $tournament->tournamentCategory->name }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Zona</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $tournament->zone->name }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $tournament->start_date->format('d/m/Y') }} - {{ $tournament->end_date->format('d/m/Y') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Stato</dt>
                        <dd class="mt-1">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                {{ $tournament->status === 'open' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $tournament->status === 'open' ? 'Aperto' : ucfirst($tournament->status) }}
                            </span>
                        </dd>
                    </div>

                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Circolo</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $tournament->club->name }}
                            <div class="text-xs text-gray-500">{{ $tournament->club->full_address }}</div>
                        </dd>
                    </div>
                </dl>

                @if($tournament->notes)
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <dt class="text-sm font-medium text-gray-500">Note</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $tournament->notes }}</dd>
                </div>
                @endif
            </div>

            {{-- Referee Actions (only for referees) --}}
            @if(auth()->user()->user_type === 'referee')
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Le tue azioni</h3>

                @if($userAssignment)
                    <div class="bg-green-50 border border-green-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800">
                                    Sei stato assegnato a questo torneo!
                                </p>
                                <p class="mt-1 text-sm text-green-700">
                                    Ruolo: {{ $userAssignment->role }}
                                </p>
                            </div>
                        </div>
                    </div>
                @elseif($userAvailability)
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-blue-800">
                                    Hai dichiarato la tua disponibilità
                                </p>
                                <p class="mt-1 text-sm text-blue-700">
                                    Inserita il {{ $userAvailability->submitted_at->format('d/m/Y H:i') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @elseif($tournament->status === 'open')
                    <form action="{{ route('referee.applications.apply', $tournament) }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-200">
                            Dichiara Disponibilità
                        </button>
                    </form>
                @else
                    <p class="text-gray-500">Le iscrizioni per questo torneo sono chiuse.</p>
                @endif
            </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div>
            {{-- Tournament Stats --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Informazioni</h3>

                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Arbitri richiesti</span>
                        <span class="text-sm font-medium">{{ $tournament->required_referees ?? 'N/A' }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Arbitri assegnati</span>
                        <span class="text-sm font-medium">{{ $tournament->assignments()->count() }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Disponibilità ricevute</span>
                        <span class="text-sm font-medium">{{ $tournament->availabilities()->count() }}</span>
                    </div>

                    @if($tournament->availability_deadline)
                    <div class="pt-3 border-t border-gray-200">
                        <span class="text-sm text-gray-600">Scadenza disponibilità</span>
                        <div class="text-sm font-medium">{{ $tournament->availability_deadline->format('d/m/Y') }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
