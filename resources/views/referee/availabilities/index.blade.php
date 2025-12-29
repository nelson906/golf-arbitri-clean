@extends('layouts.app')

@section('title', 'Le Mie Disponibilità')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Le Mie Disponibilità</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Gestisci le tue disponibilità per i tornei
                    </p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('user.availability.calendar') }}"
                       class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Vista Calendario
                    </a>
                    <a href="{{ route('user.availability.tournaments') }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Dichiara Disponibilità
                    </a>
                </div>
            </div>
        </div>

        {{-- Current Availabilities --}}
        @if($availabilities->isEmpty())
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Nessuna disponibilità dichiarata</h3>
                <p class="mt-1 text-sm text-gray-500">Inizia dichiarando la tua disponibilità per i prossimi tornei.</p>
                <div class="mt-6">
                    <a href="{{ route('user.availability.tournaments') }}"
                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        Dichiara Disponibilità
                    </a>
                </div>
            </div>
        @else
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Le tue disponibilità dichiarate ({{ $availabilities->count() }})
                    </h3>
                </div>
                <ul class="divide-y divide-gray-200">
                    @foreach($availabilities as $availability)
                        <li class="px-4 py-4 sm:px-6">
                            <div class="flex items-center justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900 truncate">
                                                {{ $availability->tournament->name }}
                                            </h4>
                                            <p class="mt-1 text-sm text-gray-500">
                                                {{ $availability->tournament->club->name ?? 'N/A' }} - 
                                                {{ $availability->tournament->club->zone->name ?? 'N/A' }}
                                            </p>
                                        </div>
                                        <div class="ml-4 flex-shrink-0">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Disponibile
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-center text-sm text-gray-500">
                                        <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>
                                            {{ $availability->tournament->start_date->format('d/m/Y') }}
                                            @if($availability->tournament->end_date && $availability->tournament->start_date->format('Y-m-d') !== $availability->tournament->end_date->format('Y-m-d'))
                                                - {{ $availability->tournament->end_date->format('d/m/Y') }}
                                            @endif
                                        </span>
                                        <span class="mx-2">•</span>
                                        <span>{{ $availability->tournament->tournamentType->name ?? 'N/A' }}</span>
                                    </div>
                                    @if($availability->notes)
                                        <p class="mt-2 text-sm text-gray-600">
                                            <span class="font-medium">Note:</span> {{ $availability->notes }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end">
                                <form method="POST" action="{{ route('user.availability.store') }}" 
                                      onsubmit="return confirm('Sei sicuro di voler rimuovere la tua disponibilità per questo torneo?');">
                                    @csrf
                                    <input type="hidden" name="tournament_id" value="{{ $availability->tournament_id }}">
                                    <input type="hidden" name="available" value="0">
                                    <button type="submit" 
                                            class="text-sm text-red-600 hover:text-red-800 font-medium">
                                        Rimuovi disponibilità
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
