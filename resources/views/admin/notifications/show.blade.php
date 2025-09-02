{{-- File: resources/views/admin/notifications/show.blade.php --}}
@extends('layouts.admin')

@section('title', ' ' )

@section('content')

    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📧 Dettagli Notifica
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('admin.tournament-notifications.index') }}"
                   class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                    ← Torna all'elenco
                </a>

                @if($notification->canBeRetried())
                    <form method="POST" action="{{ route('admin.notifications.resend', $notification) }}" class="inline">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
                                onclick="return confirm('Sei sicuro di voler reinviare questa notifica?')">
                            🔄 Reinvia
                        </button>
                    </form>
                @endif

                @if($notification->status === 'pending')
                    <form method="POST" action="{{ route('admin.notifications.cancel', $notification) }}" class="inline">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                                onclick="return confirm('Sei sicuro di voler annullare questa notifica?')">
                            ❌ Annulla
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-400 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                {{-- Main Content --}}
                <div class="lg:col-span-2">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">

                            {{-- Header Info --}}
                            <div class="border-b border-gray-200 pb-6 mb-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-medium text-gray-900">{{ $notification->subject }}</h3>
                                    <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full
                                        {{ $notification->status === 'sent' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $notification->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        {{ $notification->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                                        {{ $notification->status === 'cancelled' ? 'bg-gray-100 text-gray-800' : '' }}">
                                        {{ $notification->status_label }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 gap-4 text-sm text-gray-600">
                                    <div>
                                        <span class="font-medium">Creato:</span>
                                        {{ $notification->created_at->format('d/m/Y H:i') }}
                                    </div>
                                    @if($notification->sent_at)
                                        <div>
                                            <span class="font-medium">Inviato:</span>
                                            {{ $notification->sent_at->format('d/m/Y H:i') }}
                                        </div>
                                    @endif
                                    <div>
                                        <span class="font-medium">Template:</span>
                                        {{ $notification->template_display_name }}
                                    </div>
                                    @if($notification->retry_count > 0)
                                        <div>
                                            <span class="font-medium">Tentativi:</span>
                                            {{ $notification->retry_count }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Message Content --}}
                            <div class="mb-6">
                                <h4 class="text-md font-medium text-gray-900 mb-3">📝 Contenuto Messaggio</h4>
                                <div class="bg-gray-50 p-4 rounded-lg border">
                                    <div class="prose max-w-none text-sm">
                                        {!! nl2br(e($notification->body)) !!}
                                    </div>
                                </div>
                            </div>

                            {{-- Attachments --}}
                            @if($notification->hasAttachments())
                                <div class="mb-6">
                                    <h4 class="text-md font-medium text-gray-900 mb-3">📎 Allegati ({{ $notification->attachment_count }})</h4>
                                    <div class="space-y-2">
                                        @foreach($notification->attachment_names as $name)
                                            <div class="flex items-center p-3 bg-gray-50 rounded-lg border">
                                                <div class="flex-shrink-0">
                                                    <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                    </svg>
                                                </div>
                                                <div class="ml-3 flex-1">
                                                    <div class="text-sm font-medium text-gray-900">{{ $name }}</div>
                                                    <div class="text-xs text-gray-500">
                                                        @if($name === 'convocation')
                                                            Convocazione SZR
                                                        @elseif($name === 'club_letter')
                                                            Lettera Circolo
                                                        @else
                                                            Documento
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="flex-shrink-0">
                                                    <button class="text-indigo-600 hover:text-indigo-900 text-sm">
                                                        📥 Scarica
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Error Message if failed --}}
                            @if($notification->status === 'failed' && $notification->error_message)
                                <div class="mb-6">
                                    <h4 class="text-md font-medium text-red-900 mb-3">❌ Messaggio di Errore</h4>
                                    <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                                        <div class="text-sm text-red-800">
                                            {{ $notification->error_message }}
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Related Tournament --}}
                            @if($notification->assignment && $notification->assignment->tournament)
                                <div>
                                    <h4 class="text-md font-medium text-gray-900 mb-3">🏌️ Torneo Correlato</h4>
                                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <h5 class="font-medium text-blue-900">
                                                    {{ $notification->assignment->tournament->name }}
                                                </h5>
                                                <div class="mt-1 text-sm text-blue-700 space-y-1">
                                                    <div>
                                                        📅 {{ $notification->assignment->tournament->start_date->format('d/m/Y') }}
                                                        @if(!$notification->assignment->tournament->start_date->isSameDay($notification->assignment->tournament->end_date))
                                                            - {{ $notification->assignment->tournament->end_date->format('d/m/Y') }}
                                                        @endif
                                                    </div>
                                                    <div>🏌️ {{ $notification->assignment->tournament->club->name }}</div>
                                                    <div>🌍 {{ $notification->assignment->tournament->club->zone->name }}</div>
                                                    @if($notification->assignment->user)
                                                        <div>👨‍⚖️ {{ $notification->assignment->user->name }} ({{ $notification->assignment->role }})</div>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex-shrink-0 ml-4">
                                                <a href="{{ route('tournaments.show', $notification->assignment->tournament) }}"
                                                   class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                                    👁️ Vedi Torneo
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Sidebar --}}
                <div class="lg:col-span-1">

                    {{-- Recipient Info --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <h4 class="text-md font-medium text-gray-900 mb-4">👤 Destinatario</h4>

                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0 h-12 w-12">
                                    <div class="h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center text-xl">
                                        @if($notification->recipient_type === 'referee')
                                            👨‍⚖️
                                        @elseif($notification->recipient_type === 'club')
                                            🏌️
                                        @else
                                            🏛️
                                        @endif
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $notification->recipient_type_label }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $notification->recipient_email }}
                                    </div>
                                </div>
                            </div>

                            @if($notification->assignment && $notification->assignment->user && $notification->recipient_type === 'referee')
                                <div class="border-t border-gray-200 pt-4">
                                    <div class="space-y-2 text-sm">
                                        <div>
                                            <span class="font-medium text-gray-700">Nome:</span>
                                            <span class="text-gray-900">{{ $notification->assignment->user->name }}</span>
                                        </div>
                                        <div>
                                            <span class="font-medium text-gray-700">Ruolo:</span>
                                            <span class="text-gray-900">{{ $notification->assignment->role }}</span>
                                        </div>
                                        @if($notification->assignment->user->phone)
                                            <div>
                                                <span class="font-medium text-gray-700">Telefono:</span>
                                                <span class="text-gray-900">{{ $notification->assignment->user->phone }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Status Timeline --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <h4 class="text-md font-medium text-gray-900 mb-4">📊 Cronologia Stato</h4>

                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-3 w-3 bg-blue-400 rounded-full"></div>
                                    <div class="ml-3 text-sm">
                                        <div class="font-medium text-gray-900">Notifica Creata</div>
                                        <div class="text-gray-500">{{ $notification->created_at->format('d/m/Y H:i') }}</div>
                                    </div>
                                </div>

                                @if($notification->sent_at)
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-3 w-3 bg-green-400 rounded-full"></div>
                                        <div class="ml-3 text-sm">
                                            <div class="font-medium text-gray-900">Email Inviata</div>
                                            <div class="text-gray-500">{{ $notification->sent_at->format('d/m/Y H:i') }}</div>
                                        </div>
                                    </div>
                                @endif

                                @if($notification->status === 'failed')
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-3 w-3 bg-red-400 rounded-full"></div>
                                        <div class="ml-3 text-sm">
                                            <div class="font-medium text-gray-900">Invio Fallito</div>
                                            <div class="text-gray-500">Tentativo {{ $notification->retry_count }}</div>
                                        </div>
                                    </div>
                                @endif

                                @if($notification->status === 'cancelled')
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-3 w-3 bg-gray-400 rounded-full"></div>
                                        <div class="ml-3 text-sm">
                                            <div class="font-medium text-gray-900">Notifica Annullata</div>
                                            <div class="text-gray-500">{{ $notification->updated_at->format('d/m/Y H:i') }}</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Quick Actions --}}
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h4 class="text-md font-medium text-gray-900 mb-4">⚡ Azioni Rapide</h4>

                            <div class="space-y-3">
                                @if($notification->assignment && $notification->assignment->tournament)
                                    <a href="{{ route('tournaments.send-assignment-form', $notification->assignment->tournament) }}"
                                       class="w-full inline-flex justify-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        📧 Invia Nuova Notifica
                                    </a>
                                @endif

                                <a href="{{ route('admin.tournament-notifications.index') }}?recipient_email={{ urlencode($notification->recipient_email) }}"
                                   class="w-full inline-flex justify-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    📋 Tutte le Notifiche a Questo Destinatario
                                </a>

                                @if($notification->template_used)
                                    <a href="{{ route('letter-templates.index') }}?search={{ urlencode($notification->template_used) }}"
                                       class="w-full inline-flex justify-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        📝 Gestisci Template
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
