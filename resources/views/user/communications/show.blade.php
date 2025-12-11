@extends('layouts.app')

@section('title', $communication->title)

@section('content')
<div class="py-6">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Back link --}}
        <div class="mb-4">
            <a href="{{ route('user.communications.index') }}" class="text-blue-600 hover:text-blue-800">
                ← Torna alle comunicazioni
            </a>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            {{-- Header --}}
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center gap-2 mb-3">
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

                <h1 class="text-2xl font-bold text-gray-900">{{ $communication->title }}</h1>

                <div class="flex items-center gap-4 mt-3 text-sm text-gray-500">
                    <span>Pubblicato il {{ $communication->created_at->format('d/m/Y H:i') }}</span>
                    @if($communication->author)
                        <span>da {{ $communication->author->name }}</span>
                    @endif
                </div>
            </div>

            {{-- Content --}}
            <div class="p-6">
                <div class="prose max-w-none">
                    {!! nl2br(e($communication->content)) !!}
                </div>
            </div>

            {{-- Footer --}}
            @if($communication->expires_at)
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <p class="text-sm text-gray-500">
                        Questa comunicazione scadrà il {{ $communication->expires_at->format('d/m/Y H:i') }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
