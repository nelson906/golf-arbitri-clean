@extends('layouts.admin')

@section('title', 'Calendario Tornei')

@section('content')
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Header --}}
            <div class="mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Calendario Tornei</h1>
                        <p class="mt-1 text-sm text-gray-600">
                            Visualizza i tornei in formato calendario
                        </p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="{{ route('tournaments.index') }}"
                            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            Lista Tornei
                        </a>
                    </div>
                </div>
            </div>

            {{-- Public Calendar Container --}}
            <div id="admin-calendar-root"></div>
        </div>
    </div>

    {{-- Pass data to JavaScript --}}
    <script>
        window.adminCalendarData = @json($calendarData);
    </script>
@endsection

@vite(['resources/js/app.js'])
