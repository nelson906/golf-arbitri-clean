@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        🏠 Dashboard
    </h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium mb-4">Benvenuto, {{ auth()->user()->name }}!</h3>
                    
                    @if(auth()->user()->user_type === 'referee')
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <a href="{{ route('tournaments.index') }}" class="bg-blue-50 p-6 rounded-lg hover:bg-blue-100">
                                <h4 class="font-semibold text-blue-900">📋 Lista Tornei</h4>
                                <p class="text-blue-700 mt-2">Visualizza tutti i tornei disponibili</p>
                            </a>
                            
                            <a href="{{ route('user.availability.index') }}" class="bg-green-50 p-6 rounded-lg hover:bg-green-100">
                                <h4 class="font-semibold text-green-900">📝 Le Mie Disponibilità</h4>
                                <p class="text-green-700 mt-2">Gestisci le tue disponibilità</p>
                            </a>
                            
                            <a href="{{ route('user.availability.calendar') }}" class="bg-purple-50 p-6 rounded-lg hover:bg-purple-100">
                                <h4 class="font-semibold text-purple-900">📅 Il Mio Calendario</h4>
                                <p class="text-purple-700 mt-2">Visualizza il tuo calendario personale</p>
                            </a>
                        </div>

                        {{-- Comunicazioni Attive --}}
                        @if(isset($activeCommunications) && $activeCommunications->count() > 0)
                        <div class="mt-8">
                            <h3 class="text-lg font-semibold mb-4">📢 Comunicazioni Recenti</h3>
                            <div class="space-y-4">
                                @foreach($activeCommunications as $communication)
                                <div class="border rounded-lg p-4 {{ $communication->priority === 'urgent' ? 'border-red-300 bg-red-50' : ($communication->priority === 'high' ? 'border-yellow-300 bg-yellow-50' : 'border-gray-200') }}">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-semibold {{ $communication->priority === 'urgent' ? 'text-red-900' : ($communication->priority === 'high' ? 'text-yellow-900' : 'text-gray-900') }}">
                                            {{ $communication->title }}
                                        </h4>
                                        <div class="flex gap-2">
                                            @if($communication->priority === 'urgent')
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">URGENTE</span>
                                            @elseif($communication->priority === 'high')
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">IMPORTANTE</span>
                                            @endif
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                                @if($communication->type === 'announcement') bg-green-100 text-green-800
                                                @elseif($communication->type === 'alert') bg-red-100 text-red-800
                                                @elseif($communication->type === 'maintenance') bg-orange-100 text-orange-800
                                                @else bg-blue-100 text-blue-800
                                                @endif">
                                                {{ \App\Models\Communication::TYPES[$communication->type] ?? $communication->type }}
                                            </span>
                                        </div>
                                    </div>
                                    <p class="text-gray-600 text-sm mb-2">{{ Str::limit($communication->content, 150) }}</p>
                                    <div class="flex justify-between items-center text-xs text-gray-500">
                                        <span>{{ $communication->created_at->diffForHumans() }}</span>
                                        @if($communication->zone)
                                            <span class="font-medium">Zona: {{ $communication->zone->name }}</span>
                                        @else
                                            <span class="font-medium">Globale</span>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @elseif(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']))
                        <p>
                            Sei un amministratore. 
                            <a href="{{ route('admin.dashboard') }}" class="text-blue-600 hover:text-blue-800">
                                Vai alla Dashboard Admin →
                            </a>
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
