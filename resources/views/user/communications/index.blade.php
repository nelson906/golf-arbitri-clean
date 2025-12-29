@extends('layouts.app')

@section('title', 'Comunicazioni')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold text-gray-900">📢 Comunicazioni</h1>
        </div>

        {{-- Filtri --}}
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                    <select name="type" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Tutti</option>
                        <option value="announcement" {{ request('type') == 'announcement' ? 'selected' : '' }}>Annuncio</option>
                        <option value="alert" {{ request('type') == 'alert' ? 'selected' : '' }}>Avviso</option>
                        <option value="info" {{ request('type') == 'info' ? 'selected' : '' }}>Informazione</option>
                        <option value="maintenance" {{ request('type') == 'maintenance' ? 'selected' : '' }}>Manutenzione</option>
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                    Filtra
                </button>
                @if(request()->hasAny(['type']))
                    <a href="{{ route('user.communications.index') }}" class="px-4 py-2 text-gray-600 text-sm hover:text-gray-800">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        {{-- Lista comunicazioni --}}
        @if($communications->isEmpty())
            <div class="bg-white shadow rounded-lg p-8 text-center">
                <p class="text-gray-500">Nessuna comunicazione disponibile.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($communications as $communication)
                    <div class="bg-white shadow rounded-lg p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    {{-- Priority badge --}}
                                    @if($communication->priority === 'urgent')
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Urgente</span>
                                    @elseif($communication->priority === 'high')
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">Alta priorità</span>
                                    @endif

                                    {{-- Type badge --}}
                                    @switch($communication->type)
                                        @case('announcement')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">📣 Annuncio</span>
                                            @break
                                        @case('alert')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">⚠️ Avviso</span>
                                            @break
                                        @case('info')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">ℹ️ Info</span>
                                            @break
                                        @case('maintenance')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">🔧 Manutenzione</span>
                                            @break
                                    @endswitch

                                    {{-- Zone badge --}}
                                    @if($communication->zone)
                                        <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">{{ $communication->zone->name }}</span>
                                    @else
                                        <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">Nazionale</span>
                                    @endif
                                </div>

                                <a href="{{ route('user.communications.show', $communication) }}" class="block">
                                    <h2 class="text-lg font-semibold text-gray-900 hover:text-blue-600">
                                        {{ $communication->title }}
                                    </h2>
                                </a>

                                <p class="text-gray-600 mt-2 line-clamp-2">
                                    {{ Str::limit(strip_tags($communication->content), 200) }}
                                </p>

                                <div class="flex items-center gap-4 mt-3 text-sm text-gray-500">
                                    <span>{{ $communication->created_at->format('d/m/Y H:i') }}</span>
                                    @if($communication->author)
                                        <span>di {{ $communication->author->name }}</span>
                                    @endif
                                </div>
                            </div>

                            <a href="{{ route('user.communications.show', $communication) }}"
                               class="ml-4 px-3 py-2 text-sm text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded">
                                Leggi →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $communications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
