@extends('layouts.admin')

@section('title', 'Dettaglio Arbitro')

@section('content')
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.users.index') }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900">{{ $user->name }}</h1>
            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                {{ $user->is_active ? 'Attivo' : 'Non Attivo' }}
            </span>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('admin.users.edit', $user) }}"
               class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                Modifica
            </a>
            <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}" class="inline">
                @csrf
                @method('PATCH')
                <button type="submit"
                        class="bg-{{ $user->is_active ? 'red' : 'green' }}-600 text-white px-4 py-2 rounded-lg hover:bg-{{ $user->is_active ? 'red' : 'green' }}-700 transition"
                        onclick="return confirm('Confermi di voler {{ $user->is_active ? 'disattivare' : 'attivare' }} questo utente?')">
                    {{ $user->is_active ? 'Disattiva' : 'Attiva' }}
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Info Principali --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Informazioni Generali</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Nome</label>
                        <p class="text-sm text-gray-900">{{ $user->name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Email</label>
                        <p class="text-sm text-gray-900">{{ $user->email }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Codice Arbitro</label>
                        <p class="text-sm text-gray-900">{{ $user->referee_code ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Telefono</label>
                        <p class="text-sm text-gray-900">{{ $user->phone ?: 'Non specificato' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Livello</label>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            {{ \App\Models\User::LEVELS[$user->level] ?? $user->level }}
                        </span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Zona</label>
                        <p class="text-sm text-gray-900">{{ $user->zone->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Città</label>
                        <p class="text-sm text-gray-900">{{ $user->city ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Circolo</label>
                        <p class="text-sm text-gray-900">{{ $user->club_member ?? 'N/A' }}</p>
                    </div>
                </div>

                @if($user->notes)
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-500">Note</label>
                        <p class="text-sm text-gray-900">{{ $user->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Assegnazioni Recenti --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Assegnazioni Recenti</h2>
                @if($user->assignments->count() > 0)
                    <div class="space-y-3">
                        @foreach($user->assignments->take(5) as $assignment)
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $assignment->tournament->name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $assignment->tournament->start_date ? $assignment->tournament->start_date->format('d/m/Y') : 'Data N/A' }}
                                        - {{ $assignment->tournament->club->name ?? 'Club N/A' }}
                                    </p>
                                </div>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $assignment->is_confirmed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                    {{ $assignment->is_confirmed ? 'Confermato' : 'In attesa' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-sm">Nessuna assegnazione</p>
                @endif
            </div>
        </div>

        {{-- Statistiche --}}
        {{-- <div class="space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Statistiche</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Assegnazioni Totali</span>
                        <span class="text-sm font-semibold text-gray-900">{{ $stats['total_assignments'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Confermate</span>
                        <span class="text-sm font-semibold text-green-600">{{ $stats['confirmed_assignments'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Quest'Anno</span>
                        <span class="text-sm font-semibold text-blue-600">{{ $stats['current_year_assignments'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Disponibilità</span>
                        <span class="text-sm font-semibold text-purple-600">{{ $stats['total_availabilities'] }}</span>
                    </div>
                </div>
            </div>
 --}}

         <div class="space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Statistiche</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Assegnazioni Totali</span>
                        <span class="text-sm font-semibold text-gray-900">{{ $user->assignments->count() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Confermate</span>
                        <span class="text-sm font-semibold text-green-600">{{ $user->assignments->count() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Quest'Anno</span>
                        <span class="text-sm font-semibold text-blue-600">{{ $user->assignments->count() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Disponibilità</span>
                        <span class="text-sm font-semibold text-purple-600">{{ $user->assignments->count() }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Informazioni Account</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Registrato</span>
                        <span class="text-sm text-gray-900">{{ $user->created_at->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Ultimo aggiornamento</span>
                        <span class="text-sm text-gray-900">{{ $user->updated_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
