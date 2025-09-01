@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <h2 class="text-2xl font-semibold mb-6">Benvenuto, {{ auth()->user()->name }}!</h2>
                
                {{-- Stats Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    {{-- Disponibilità Totali --}}
                    <div class="bg-blue-50 p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <p class="text-sm text-blue-600 font-medium">Disponibilità Dichiarate</p>
                                <p class="text-2xl font-bold text-blue-900">{{ $stats['availabilities_count'] }}</p>
                            </div>
                            <svg class="w-12 h-12 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    {{-- Assegnazioni Totali --}}
                    <div class="bg-green-50 p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <p class="text-sm text-green-600 font-medium">Tornei Assegnati</p>
                                <p class="text-2xl font-bold text-green-900">{{ $stats['assignments_count'] }}</p>
                            </div>
                            <svg class="w-12 h-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    {{-- Livello Arbitro --}}
                    <div class="bg-purple-50 p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <p class="text-sm text-purple-600 font-medium">Livello</p>
                                <p class="text-lg font-bold text-purple-900">{{ auth()->user()->level ?? 'Non specificato' }}</p>
                            </div>
                            <svg class="w-12 h-12 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    {{-- Zona --}}
                    <div class="bg-yellow-50 p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <p class="text-sm text-yellow-600 font-medium">Zona</p>
                                <p class="text-lg font-bold text-yellow-900">{{ auth()->user()->zone->name ?? 'Non assegnata' }}</p>
                            </div>
                            <svg class="w-12 h-12 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                {{-- Quick Actions --}}
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4">Azioni Rapide</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <a href="{{ route('user.availability.tournaments') }}" class="block bg-blue-600 text-white text-center py-3 px-6 rounded-lg hover:bg-blue-700 transition">
                            Dichiara Disponibilità
                        </a>
                        <a href="{{ route('user.availability.index') }}" class="block bg-gray-600 text-white text-center py-3 px-6 rounded-lg hover:bg-gray-700 transition">
                            Le Mie Disponibilità
                        </a>
                        <a href="{{ route('user.availability.calendar') }}" class="block bg-green-600 text-white text-center py-3 px-6 rounded-lg hover:bg-green-700 transition">
                            Calendario
                        </a>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {{-- Prossimi Tornei Assegnati --}}
                    <div>
                        <h3 class="text-lg font-semibold mb-4">Prossimi Tornei Assegnati</h3>
                        @if($stats['upcoming_tournaments']->isEmpty())
                            <p class="text-gray-500">Nessun torneo assegnato in programma.</p>
                        @else
                            <div class="space-y-3">
                                @foreach($stats['upcoming_tournaments'] as $assignment)
                                    <div class="border rounded-lg p-4 hover:bg-gray-50">
                                        <h4 class="font-medium">{{ $assignment->tournament->name }}</h4>
                                        <p class="text-sm text-gray-600">
                                            {{ $assignment->tournament->start_date->format('d/m/Y') }}
                                            @if($assignment->tournament->end_date && $assignment->tournament->start_date->format('Y-m-d') !== $assignment->tournament->end_date->format('Y-m-d'))
                                                - {{ $assignment->tournament->end_date->format('d/m/Y') }}
                                            @endif
                                        </p>
                                        <p class="text-sm text-gray-500">{{ $assignment->tournament->club->name ?? 'N/A' }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    
                    {{-- Disponibilità Recenti --}}
                    <div>
                        <h3 class="text-lg font-semibold mb-4">Disponibilità Recenti</h3>
                        @if($stats['recent_availabilities']->isEmpty())
                            <p class="text-gray-500">Nessuna disponibilità dichiarata di recente.</p>
                        @else
                            <div class="space-y-3">
                                @foreach($stats['recent_availabilities'] as $availability)
                                    <div class="border rounded-lg p-4 hover:bg-gray-50">
                                        <h4 class="font-medium">{{ $availability->tournament->name }}</h4>
                                        <p class="text-sm text-gray-600">
                                            {{ $availability->tournament->start_date->format('d/m/Y') }}
                                            @if($availability->tournament->end_date && $availability->tournament->start_date->format('Y-m-d') !== $availability->tournament->end_date->format('Y-m-d'))
                                                - {{ $availability->tournament->end_date->format('d/m/Y') }}
                                            @endif
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            Dichiarata il {{ $availability->created_at->format('d/m/Y') }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
