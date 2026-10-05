@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        🏌️ Le Mie Assegnazioni
    </h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @foreach ([
                ['title' => 'Prossime designazioni', 'items' => $upcoming, 'empty' => 'Nessuna designazione in programma.'],
                ['title' => 'Designazioni concluse in questa stagione', 'items' => $past, 'empty' => 'Nessuna designazione conclusa in questa stagione.'],
            ] as $section)
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ $section['title'] }} ({{ $section['items']->count() }})
                        </h3>
                    </div>

                    @if ($section['items']->isEmpty())
                        <p class="px-6 py-8 text-center text-gray-500">{{ $section['empty'] }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Torneo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Circolo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ruolo</th>
                                        <th class="px-6 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($section['items'] as $assignment)
                                        @php $t = $assignment->tournament; @endphp
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $t->start_date->format('d/m/Y') }}
                                                @if ($t->end_date && ! $t->end_date->isSameDay($t->start_date))
                                                    – {{ $t->end_date->format('d/m/Y') }}
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <div class="font-medium text-gray-900">{{ $t->name }}</div>
                                                <div class="text-gray-500">
                                                    {{ $t->tournamentType->name ?? '' }}
                                                    @if ($t->tournamentType->is_national ?? false)
                                                        <span class="ml-1 px-2 py-0.5 text-xs bg-purple-100 text-purple-800 rounded">Nazionale</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                {{ $t->club->name ?? 'N/A' }}
                                                <div class="text-gray-500">{{ $t->club->zone->name ?? '' }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-700">
                                                {{ $assignment->role }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                <a href="{{ route('tournaments.show', $t) }}" class="text-indigo-600 hover:text-indigo-900">Dettaglio</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach

            <p class="text-sm text-gray-500">
                Le designazioni delle stagioni precedenti sono nel
                <a href="{{ route('user.curriculum') }}" class="text-indigo-600 hover:text-indigo-900">Mio Curriculum</a>.
            </p>
        </div>
    </div>
@endsection
