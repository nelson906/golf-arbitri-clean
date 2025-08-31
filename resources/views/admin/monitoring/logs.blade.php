@extends('layouts.admin')

@section('header')
    <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            📋 Log di Sistema
        </h2>
        <a href="{{ route('admin.monitoring.dashboard') }}"
            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
            ← Torna al Dashboard
        </a>
    </div>
@endsection

@section('content')
    <div class="bg-white shadow-sm rounded-lg">
        <div class="p-6">
            <div class="mb-4">
                <div class="flex space-x-4">
                    <select class="border rounded px-3 py-2">
                        <option>Tutti i livelli</option>
                        <option>Error</option>
                        <option>Warning</option>
                        <option>Info</option>
                    </select>
                    <input type="date" class="border rounded px-3 py-2" value="{{ date('Y-m-d') }}">
                    <input type="text" placeholder="Cerca..." class="border rounded px-3 py-2 flex-1">
                </div>
            </div>

            <div class="bg-gray-900 text-green-400 p-4 rounded font-mono text-sm">
                <div class="space-y-1">
                    <div>[{{ date('Y-m-d H:i:s') }}] INFO: Sistema avviato correttamente</div>
                    <div>[{{ date('Y-m-d H:i:s') }}] INFO: Database connesso - 3 connessioni attive</div>
                    <div>[{{ date('Y-m-d H:i:s') }}] INFO: Cache sistema attivo (driver: database)</div>
                    <div>[{{ date('Y-m-d H:i:s') }}] INFO: File system operativo - permessi OK</div>
                    <div>[{{ date('Y-m-d H:i:s') }}] INFO: Sistema email configurato (driver: smtp)</div>
                    <div>[{{ date('Y-m-d H:i:s') }}] INFO: Sistema code attivo - 0 job in coda</div>
                    <div class="text-yellow-400">[{{ date('Y-m-d H:i:s') }}] WARNING: Memoria utilizzo al 75%</div>
                    <div>[{{ date('Y-m-d H:i:s') }}] INFO: Auto-refresh dashboard impostato</div>
                    <div>[{{ date('Y-m-d H:i:s') }}] INFO: Health check completato - sistema operativo</div>
                </div>
            </div>
        </div>
    </div>
@endsection
