@extends('layouts.admin')

@section('title', 'Gestione Notifiche Tornei')

@section('content')
    <div class="container mx-auto px-4 py-6">

        {{-- Intestazione --}}
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-2xl font-bold">📧 Notifiche Tornei</h1>
            @if(!auth()->user()->is_admin && auth()->user()->zone)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                    SZR {{ auth()->user()->zone->number }}
                </span>
            @endif
        </div>

        {{-- Filtri --}}
        <form method="GET" action="{{ route('admin.tournament-notifications.index') }}"
              class="flex flex-wrap gap-3 mb-4 items-end">
            {{-- Filtro anno --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Anno torneo</label>
                <select name="anno"
                        class="rounded border-gray-300 text-sm py-1.5 pr-8 focus:ring-indigo-500 focus:border-indigo-500"
                        onchange="this.form.submit()">
                    <option value="">Tutti gli anni</option>
                    @foreach($anniDisponibili as $a)
                        <option value="{{ $a }}" {{ request('anno') == $a ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            {{-- Ricerca nome --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Cerca torneo</label>
                <div class="flex">
                    <input type="text" name="cerca" value="{{ request('cerca') }}"
                           placeholder="Nome torneo…"
                           class="rounded-l border-gray-300 text-sm py-1.5 focus:ring-indigo-500 focus:border-indigo-500">
                    <button type="submit"
                            class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-r hover:bg-indigo-700">
                        Cerca
                    </button>
                </div>
            </div>
            @if(request('anno') || request('cerca'))
                <a href="{{ route('admin.tournament-notifications.index') }}"
                   class="self-end text-sm text-gray-500 hover:text-gray-700 underline">✕ Azzera</a>
            @endif
        </form>

        {{-- Tabella --}}
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-8">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            Data torneo ↓
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-1/3">Torneo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Arbitri</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stato</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Azioni</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($tournamentNotifications as $i => $group)
                        @php
                            $primaryNotification = $group->primary;
                            $tournament          = $group->tournament;
                            // Fonte di verità: is_national dal tipo torneo (già calcolato nel controller)
                            $isNational          = $group->is_national;
                        @endphp
                        <tr class="hover:bg-gray-50">

                            {{-- Numero riga (paginato) --}}
                            <td class="px-4 py-3 text-xs text-gray-400">
                                {{ ($tournamentNotifications->currentPage() - 1) * $tournamentNotifications->perPage() + $loop->iteration }}
                            </td>

                            {{-- Data torneo --}}
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-700">
                                {{ $tournament->start_date->format('d/m/Y') }}
                                @if($tournament->end_date && $tournament->end_date->ne($tournament->start_date))
                                    <span class="text-gray-400 text-xs">– {{ $tournament->end_date->format('d/m') }}</span>
                                @endif
                            </td>

                            {{-- Nome torneo + link diretto --}}
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.tournaments.show', $tournament) }}"
                                   class="text-sm font-medium text-indigo-700 hover:text-indigo-900 hover:underline"
                                   title="Vai al torneo — gestisci arbitri">
                                    {{ $tournament->name }}
                                </a>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    {{ $tournament->club->name ?? '—' }}
                                    @if($isNational)
                                        <span class="ml-1 px-1 py-0.5 bg-purple-100 text-purple-700 rounded">Nazionale</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Lista arbitri --}}
                            <td class="px-4 py-3 text-xs text-gray-600 max-w-xs">
                                <div class="truncate" title="{{ $primaryNotification->referee_list }}">
                                    {{ $primaryNotification->referee_list ?: '—' }}
                                </div>
                            </td>

                            {{-- Stato --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($isNational)
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-1">
                                            <span class="px-1.5 py-0.5 text-xs rounded bg-purple-100 text-purple-800">CRC</span>
                                            @if($group->crc?->status === 'sent')
                                                <span class="text-xs text-green-700 font-semibold">✓</span>
                                            @elseif($group->crc?->status === 'failed')
                                                <span class="text-xs text-red-700 font-semibold">✗</span>
                                            @elseif($group->crc)
                                                <span class="text-xs text-blue-600">bozza</span>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="px-1.5 py-0.5 text-xs rounded bg-indigo-100 text-indigo-800">Zona</span>
                                            @if($group->zone?->status === 'sent')
                                                <span class="text-xs text-green-700 font-semibold">✓</span>
                                            @elseif($group->zone?->status === 'failed')
                                                <span class="text-xs text-red-700 font-semibold">✗</span>
                                            @elseif($group->zone)
                                                <span class="text-xs text-blue-600">bozza</span>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    @php
                                        $s = $primaryNotification->status;
                                        $badge = match($s) {
                                            'sent'    => ['Inviata',  'bg-green-100 text-green-800'],
                                            'partial' => ['Parziale', 'bg-yellow-100 text-yellow-800'],
                                            'failed'  => ['Fallita',  'bg-red-100 text-red-800'],
                                            default   => [$primaryNotification->is_prepared ? 'Pronta' : 'Bozza',
                                                          $primaryNotification->is_prepared ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600'],
                                        };
                                    @endphp
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $badge[1] }}">
                                        {{ $badge[0] }}
                                    </span>
                                @endif
                            </td>

                            {{-- Azioni --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center justify-center gap-2">

                                    {{-- Modifica notifica (apre il form completo per modificare arbitri, CRC, SZR, messaggio) --}}
                                    <a href="{{ route('admin.tournaments.show-assignment-form', $tournament) }}"
                                       class="text-blue-600 hover:text-blue-800"
                                       title="Modifica notifica — arbitri, destinatari, messaggio">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>

                                    {{-- Reinvia (solo se già inviata) --}}
                                    @if($primaryNotification->status === 'sent' || $primaryNotification->status === 'failed')
                                        <form action="{{ route('admin.tournament-notifications.resend', $primaryNotification) }}"
                                              method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="text-amber-600 hover:text-amber-800"
                                                    title="Reinvia"
                                                    onclick="return confirm('Reinviare la notifica?')">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Vai al torneo (gestisci arbitri) --}}
                                    <a href="{{ route('admin.tournaments.show', $tournament) }}"
                                       class="text-indigo-600 hover:text-indigo-800"
                                       title="Gestisci arbitri del torneo">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </a>

                                    {{-- Dettaglio notifica --}}
                                    <a href="{{ route('admin.tournament-notifications.show', $primaryNotification) }}"
                                       class="text-gray-500 hover:text-gray-700"
                                       title="Dettaglio notifica">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>

                                    {{-- Elimina TUTTE le notifiche del torneo --}}
                                    <form action="{{ route('admin.tournament-notifications.destroy-tournament', $tournament) }}"
                                          method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-red-500 hover:text-red-700"
                                                title="Elimina tutte le notifiche di questo torneo"
                                                onclick="return confirm('Eliminare tutte le notifiche di «{{ addslashes($tournament->name) }}»?')">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>

                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-gray-400 text-sm">
                                Nessuna notifica trovata.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-4 py-3 border-t border-gray-200">
                {{ $tournamentNotifications->appends(request()->query())->links() }}
            </div>
        </div>
    </div>

    @include('admin.tournament-notifications._document_manager_modal')
@endsection
