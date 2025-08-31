@extends('layouts.admin')

@section('title', 'Dettagli Club: ' . $club->name)

@section('content')
<div class="container mx-auto px-4 py-8 max-w-7xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $club->name }}</h1>
                <p class="mt-1 text-gray-600">
                    {{ $club->code }} - {{ $club->city }}
                    @if($club->province)
                        ({{ $club->province }})
                    @endif
                </p>
            </div>
            <div class="flex space-x-4">
                <a href="{{ route('admin.clubs.edit', $club) }}"
                   class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Modifica
                </a>
                <a href="{{ route('admin.clubs.index') }}"
                   class="text-gray-600 hover:text-gray-900 flex items-center px-4 py-2">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Torna all'elenco
                </a>
            </div>
        </div>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Successo!</p>
            <p>{{ session('success') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Club Details --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Basic Information --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Informazioni Generali</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Nome</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $club->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Codice</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $club->code }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Zona</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $club->zone->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Stato</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $club->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $club->is_active ? 'Attivo' : 'Non Attivo' }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Contact Information --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Informazioni di Contatto</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Città</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $club->city }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Provincia</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $club->province ?: 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @if($club->email)
                                <a href="mailto:{{ $club->email }}" class="text-indigo-600 hover:text-indigo-500">
                                    {{ $club->email }}
                                </a>
                            @else
                                N/A
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Telefono</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @if($club->phone)
                                <a href="tel:{{ $club->phone }}" class="text-indigo-600 hover:text-indigo-500">
                                    {{ $club->phone }}
                                </a>
                            @else
                                N/A
                            @endif
                        </dd>
                    </div>
                    @if($club->address)
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Indirizzo</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $club->address }}</dd>
                    </div>
                    @endif
                    @if($club->contact_person)
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Persona di Contatto</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $club->contact_person }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- Recent Tournaments --}}
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Tornei Recenti</h2>
                </div>
                @if($recentTournaments->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Torneo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Stato</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($recentTournaments as $tournament)
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $tournament->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $tournament->tournamentType->name }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $tournament->start_date->format('d/m/Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-{{ $tournament->status_color }}-100 text-{{ $tournament->status_color }}-800">
                                            {{ $tournament->status_label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <a href="{{ route('tournaments.show', $tournament) }}" class="text-indigo-600 hover:text-indigo-900">
                                            Visualizza
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <p class="text-gray-500">Nessun torneo registrato</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Statistics --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Statistiche</h3>
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Tornei Totali</span>
                        <span class="text-sm font-medium text-gray-900">{{ $stats['total_tournaments'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Tornei Futuri</span>
                        <span class="text-sm font-medium text-gray-900">{{ $stats['upcoming_tournaments'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Tornei Attivi</span>
                        <span class="text-sm font-medium text-gray-900">{{ $stats['active_tournaments'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Tornei Completati</span>
                        <span class="text-sm font-medium text-gray-900">{{ $stats['completed_tournaments'] }}</span>
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Azioni Rapide</h3>
                <div class="space-y-2">
                    <a href="{{ route('admin.tournaments.create', ['club_id' => $club->id]) }}"
                       class="block w-full text-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        Nuovo Torneo
                    </a>
                    <a href="{{ route('admin.tournaments.index', ['club_id' => $club->id]) }}"
                       class="block w-full text-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Visualizza Tornei
                    </a>
                    <form action="{{ route('admin.clubs.toggle-active', $club) }}" method="POST" class="w-full">
                        @csrf
                        <button type="submit"
                                class="block w-full text-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                                onclick="return confirm('Sei sicuro di voler {{ $club->is_active ? 'disattivare' : 'attivare' }} questo club?')">
                            {{ $club->is_active ? 'Disattiva' : 'Attiva' }} Club
                        </button>
                    </form>
                </div>
            </div>

            @if($club->notes)
            {{-- Notes --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Note</h3>
                <p class="text-sm text-gray-600">{{ $club->notes }}</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
