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
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">Seleziona...</option>
                                            @foreach ($availableTournaments as $t)
                                                <option value="{{ $t->id }}">
                                                    {{ $t->start_date->format('d/m') }} - {{ $t->name }}
                                                    ({{ $t->club->name ?? 'N/A' }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Ruolo
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
            </div>
        </div>
    </div>
@endsection
