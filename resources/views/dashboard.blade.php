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
