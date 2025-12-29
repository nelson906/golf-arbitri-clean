{{-- FILE: resources/views/admin/communications/show.blade.php --}}
@extends('layouts.admin')

@section('title', $communication->title)

@section('content')
<div class="p-6">
    <div class="max-w-4xl mx-auto">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex items-center gap-4 mb-2">
                <a href="{{ route('admin.communications.index') }}"
                   class="text-gray-600 hover:text-gray-900 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $communication->title }}</h1>
                    <div class="flex items-center gap-4 mt-1 text-sm text-gray-500">
                        <span>📢 Comunicazione</span>
                        <span>•</span>
                        <span>Creato da {{ $communication->author->name }}</span>
                        <span>•</span>
                        <span>{{ $communication->created_at->format('d/m/Y \a\l\l\e H:i') }}</span>
                    </div>
                </div>

                {{-- Status Badges --}}
                <div class="flex items-center gap-2">
                    @php
                        $typeColors = [
                            'announcement' => 'bg-green-100 text-green-800',
                            'alert' => 'bg-red-100 text-red-800',
                            'maintenance' => 'bg-orange-100 text-orange-800',
                            'info' => 'bg-blue-100 text-blue-800'
                        ];
                    @endphp
                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $typeColors[$communication->type] ?? 'bg-gray-100 text-gray-800' }}">
                        {{ \App\Models\Communication::TYPES[$communication->type] }}
                    </span>

                    @php
                        $priorityColors = [
                            'low' => 'bg-gray-100 text-gray-800',
                            'normal' => 'bg-blue-100 text-blue-800',
                            'high' => 'bg-yellow-100 text-yellow-800',
                            'urgent' => 'bg-red-100 text-red-800'
                        ];
                    @endphp
                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $priorityColors[$communication->priority] ?? 'bg-gray-100 text-gray-800' }}">
                        {{ \App\Models\Communication::PRIORITIES[$communication->priority] }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            {{-- Metadata Header --}}
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            @if($communication->status === 'published')
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            @elseif($communication->status === 'draft')
                                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-900">Stato</p>
                            @if($communication->status === 'published')
                                <p class="text-sm text-green-600">Pubblicato</p>
                            @elseif($communication->status === 'draft')
                                <p class="text-sm text-yellow-600">Bozza</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                @if($communication->zone)
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                @endif
                            </div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-900">Destinazione</p>
                            @if($communication->zone)
                                <p class="text-sm text-gray-600">{{ $communication->zone->name }}</p>
                            @else
                                <p class="text-sm text-blue-600">Globale (tutte le zone)</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                @if($communication->isActive())
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                @endif
                            </div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-900">Visibilità</p>
                            @if($communication->isActive())
                                <p class="text-sm text-purple-600">Attiva</p>
                            @else
                                <p class="text-sm text-gray-500">Non attiva</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Scheduling Info (if exists) --}}
            @if($communication->scheduled_at || $communication->expires_at)
            <div class="bg-blue-50 px-6 py-4 border-b border-gray-200">
                <div class="flex items-center gap-6">
                    @if($communication->scheduled_at)
                    <div class="flex items-center text-sm">
                        <svg class="w-4 h-4 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-blue-900">
                            <strong>Programmazione:</strong> {{ $communication->scheduled_at->format('d/m/Y H:i') }}
                        </span>
                    </div>
                    @endif

                    @if($communication->expires_at)
                    <div class="flex items-center text-sm">
                        <svg class="w-4 h-4 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-blue-900">
                            <strong>Scadenza:</strong> {{ $communication->expires_at->format('d/m/Y H:i') }}
                        </span>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Content --}}
            <div class="px-6 py-8">
                <div class="prose prose-gray max-w-none">
                    <div class="text-gray-900 leading-relaxed whitespace-pre-line">{{ $communication->content }}</div>
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center text-sm text-gray-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>
                        Creato il {{ $communication->created_at->format('d/m/Y \a\l\l\e H:i') }}
                        @if($communication->updated_at && $communication->updated_at != $communication->created_at)
                            • Modificato il {{ $communication->updated_at->format('d/m/Y \a\l\l\e H:i') }}
                        @endif
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    {{-- Status Indicator --}}
                    @if($communication->isActive())
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Comunicazione Attiva
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Non Attiva
                        </span>
                    @endif

                    {{-- Action Buttons --}}
                    <a href="{{ route('admin.communications.index') }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Torna alla Lista
                    </a>
                </div>
            </div>
        </div>

        {{-- Additional Info Card --}}
        @if($communication->status === 'draft' || !$communication->isActive())
        <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.99-.833-2.764 0L3.932 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">
                        ℹ️ Informazioni Visibilità
                    </h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        @if($communication->status === 'draft')
                            <p>Questa comunicazione è in <strong>bozza</strong> e non è visibile agli utenti del sistema.</p>
                        @elseif($communication->scheduled_at && $communication->scheduled_at > now())
                            <p>Questa comunicazione è programmata per essere pubblicata il <strong>{{ $communication->scheduled_at->format('d/m/Y \a\l\l\e H:i') }}</strong>.</p>
                        @elseif($communication->expires_at && $communication->expires_at <= now())
                            <p>Questa comunicazione è <strong>scaduta</strong> il {{ $communication->expires_at->format('d/m/Y \a\l\l\e H:i') }} e non è più visibile.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
