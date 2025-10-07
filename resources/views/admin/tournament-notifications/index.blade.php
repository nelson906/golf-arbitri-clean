@extends('layouts.admin')

@section('title', 'Gestione Notifiche Tornei')

@section('content')
    <div class="container mx-auto px-4 py-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">📧 Notifiche Tornei</h1>
            @if(!auth()->user()->is_admin && auth()->user()->zone)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                    <svg class="-ml-1 mr-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                    </svg>
                    SZR {{ auth()->user()->zone->number }}
                </span>
            @endif
        </div>

        @if(!auth()->user()->is_admin && auth()->user()->zone)
            <div class="mb-6 rounded-md bg-blue-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            Stai visualizzando le notifiche dei tornei della SZR {{ auth()->user()->zone->number }} - {{ auth()->user()->zone->name }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Torneo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data Preparazione</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destinatari</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stato</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Azioni</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Documenti</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach ($tournamentNotifications as $notification)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap w-1/4">
                                <div class="text-sm font-medium text-gray-900 truncate max-w-xs">{{ $notification->tournament->name }}</div>
                                <div class="text-sm text-gray-500">{{ $notification->tournament->start_date->format('d/m/Y') }}</div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $notification->created_at->format('d/m/Y H:i') }}
                            </td>

                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 max-w-xs">
                                    <div class="bg-gray-100 p-2 rounded text-xs break-words">
                                        {{ $notification->referee_list ?? 'Nessun arbitro' }}
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Totale: {{ $notification->total_recipients ?: ($notification->tournament->assignments->count() + 1) }} destinatari
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    {{ $notification->status === 'sent' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $notification->status === 'draft' ? 'bg-gray-100 text-gray-800' : '' }}
                                    {{ $notification->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}">
                                    {{ $notification->status === 'draft' ? 'Bozza' : ucfirst($notification->status) }}
                                </span>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <div class="flex justify-center space-x-2">
                                    @if($notification->status === 'pending')
                                        <form action="{{ route('admin.tournament-notifications.send', $notification) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-blue-600 hover:text-blue-900" 
                                                    onclick="return confirm('Inviare le notifiche?')">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif

                                    @if($notification->status === 'sent' || $notification->status === 'failed')
                                        <form action="{{ route('admin.tournament-notifications.resend', $notification->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-amber-600 hover:text-amber-900"
                                                    onclick="return confirm('Reinviare le notifiche?')">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif

                                    <a href="{{ route('admin.tournament-notifications.edit', $notification->id) }}" class="text-indigo-600 hover:text-indigo-900">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>

                                    <a href="{{ route('admin.tournament-notifications.show', $notification) }}" class="text-gray-600 hover:text-gray-900">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </a>

                                    <form action="{{ route('admin.tournament-notifications.destroy', $notification) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900" 
                                                onclick="return confirm('Eliminare questa notifica?')">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex space-x-2">
                                    <button onclick="openDocumentManager({{ $notification->id }})" 
                                            class="inline-flex items-center px-3 py-1 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Gestisci
                                        @if($notification->document_count > 0)
                                            <span class="ml-1 bg-green-100 text-green-800 rounded-full px-2 py-0.5 text-xs" title="Documenti presenti">
                                                {{ $notification->document_count }}
                                            </span>
                                        @else
                                            <span class="ml-1 bg-gray-100 text-gray-500 rounded-full px-2 py-0.5 text-xs">
                                                0
                                            </span>
                                        @endif
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $tournamentNotifications->links() }}
        </div>
    </div>

    @include('admin.tournament-notifications._document_manager_modal')
@endsection