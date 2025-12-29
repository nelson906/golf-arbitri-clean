@extends('layouts.admin')

@section('title', 'Dettagli Assegnazione')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Dettagli Assegnazione</h1>
                <p class="mt-1 text-gray-600">
                    {{ $assignment->user->name }} → {{ $assignment->tournament->name }}
                </p>
            </div>
            <div class="flex space-x-4">
                <a href="{{ route('admin.assignments.index') }}"
                   class="text-gray-600 hover:text-gray-900 flex items-center">
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
        {{-- Assignment Details --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Referee Information --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Informazioni Arbitro</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Nome</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $assignment->user->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Codice</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $assignment->user->referee_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Livello</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($assignment->user->level) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="mailto:{{ $assignment->user->email }}" class="text-indigo-600 hover:text-indigo-500">
                                {{ $assignment->user->email }}
                            </a>
                        </dd>
                    </div>
                    @if($assignment->user->phone)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Telefono</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="tel:{{ $assignment->user->phone }}" class="text-indigo-600 hover:text-indigo-500">
                                {{ $assignment->user->phone }}
                            </a>
                        </dd>
                    </div>
                    @endif
                    @if($assignment->user->zone)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Zona</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $assignment->user->zone->name }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- Tournament Information --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Informazioni Torneo</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Nome</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $assignment->tournament->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Club</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $assignment->tournament->club->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $assignment->tournament->date_range }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Categoria</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $assignment->tournament->tournamentType->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Zona</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $assignment->tournament->zone->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Stato Torneo</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-{{ $assignment->tournament->status_color }}-100 text-{{ $assignment->tournament->status_color }}-800">
                                {{ $assignment->tournament->status_label }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Assignment Details --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Dettagli Assegnazione</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Ruolo</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $assignment->role }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Stato</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-{{ $assignment->status_color }}-100 text-{{ $assignment->status_color }}-800">
                                {{ $assignment->status_label }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Assegnato il</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $assignment->assigned_at->format('d/m/Y H:i') }}</dd>
                    </div>
                    @if($assignment->assignedBy)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Assegnato da</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $assignment->assignedBy->name }}</dd>
                    </div>
                    @endif
                    @if($assignment->confirmed_at)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Confermato il</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $assignment->confirmed_at->format('d/m/Y H:i') }}</dd>
                    </div>
                    @endif
                </dl>

                @if($assignment->notes)
                <div class="mt-4">
                    <dt class="text-sm font-medium text-gray-500">Note</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $assignment->notes }}</dd>
                </div>
                @endif
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Actions --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Azioni</h3>
                <div class="space-y-2">
                    <a href="{{ route('admin.tournaments.show', $assignment->tournament) }}"
                       class="block w-full text-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Visualizza Torneo
                    </a>

                    <a href="{{ route('admin.users.show', $assignment->user) }}"
                       class="block w-full text-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Visualizza Arbitro
                    </a>

                    @if(!$assignment->is_confirmed && $assignment->tournament->status === 'assigned')
                        <form action="{{ route('admin.assignments.confirm', $assignment) }}" method="POST" class="w-full">
                            @csrf
                            <button type="submit"
                                    class="block w-full text-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-green-600 hover:bg-green-700"
                                    onclick="return confirm('Confermare questa assegnazione?')">
                                Conferma Assegnazione
                            </button>
                        </form>
                    @endif

                    @if($assignment->tournament->status !== 'completed')
                        <form action="{{ route('admin.assignments.destroy', $assignment) }}" method="POST" class="w-full">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="block w-full text-center px-4 py-2 border border-red-300 rounded-md text-sm font-medium text-red-700 bg-white hover:bg-red-50"
                                    onclick="return confirm('Sei sicuro di voler rimuovere questa assegnazione?')">
                                Rimuovi Assegnazione
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Timeline --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Timeline</h3>
                <div class="flow-root">
                    <ul class="-mb-8">
                        <li>
                            <div class="relative pb-8">
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full bg-green-500 flex items-center justify-center ring-8 ring-white">
                                            <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div>
                                            <p class="text-sm text-gray-500">
                                                Assegnazione creata
                                                <time datetime="{{ $assignment->assigned_at->toISOString() }}">
                                                    {{ $assignment->assigned_at->format('d/m/Y H:i') }}
                                                </time>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>

                        @if($assignment->is_confirmed)
                        <li>
                            <div class="relative">
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center ring-8 ring-white">
                                            <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div>
                                            <p class="text-sm text-gray-500">
                                                Assegnazione confermata
                                                @if($assignment->confirmed_at)
                                                    <time datetime="{{ $assignment->confirmed_at->toISOString() }}">
                                                        {{ $assignment->confirmed_at->format('d/m/Y H:i') }}
                                                    </time>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
