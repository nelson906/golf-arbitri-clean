@extends(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']) ? 'layouts.admin' : 'layouts.app')

@section('title', 'Calendario Tornei')

@if(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']))
    @section('page-title', 'Calendario Tornei')
@else
    @section('header')
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            📅 Calendario Tornei
        </h2>
    @endsection
@endif

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
                    <div class="flex items-center space-x-3">
                        {{-- Zone Filter --}}
                        @if(isset($calendarData['zones']) && count($calendarData['zones']) > 0)
                            <select id="zoneFilter" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Tutte le zone</option>
                                @foreach($calendarData['zones'] as $zone)
                                    <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                                        {{ $zone->name }}
                                    </option>
                                @endforeach
                            </select>
                        @endif

                        {{-- Type Filter --}}
                        @if(isset($calendarData['types']) && count($calendarData['types']) > 0)
                            <select id="typeFilter" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Tutti i tipi</option>
                                @foreach($calendarData['types'] as $type)
                                    <option value="{{ $type->id }}" {{ request('type_id') == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                        @endif


                        @if($calendarData['userType'] === 'referee' || request('view_as') === 'user')
                            <a href="{{ route('tournaments.index') }}"
                                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Lista Tornei
                            </a>
                        @else
                            <a href="{{ route('admin.tournaments.index') }}"
                                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Lista Tornei
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Legend --}}
            <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-sm font-medium text-gray-700 mb-2">Legenda:</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    @if(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']))
                        {{-- Admin Legend - Colori basati su calendar_color del database --}}
                        @if(isset($calendarData['types']) && count($calendarData['types']) > 0)
                            @foreach($calendarData['types']->take(8) as $type)
                                <div class="flex items-center space-x-2">
                                    <span class="inline-block w-4 h-4 rounded" style="background-color: {{ $type->calendar_color ?? '#3B82F6' }}"></span>
                                    <span class="text-xs">{{ $type->name }}</span>
                                </div>
                            @endforeach
                        @endif
                    @else
                        {{-- Referee Legend --}}
                        <div class="flex items-center space-x-2">
                            <span class="inline-block w-4 h-4 rounded" style="background-color: #10B981"></span>
                            <span class="text-xs">Assegnato</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="inline-block w-4 h-4 rounded" style="background-color: #F59E0B"></span>
                            <span class="text-xs">Disponibile</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="inline-block w-4 h-4 rounded" style="background-color: #3B82F6"></span>
                            <span class="text-xs">Puoi candidarti</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Calendar Container --}}
            @if($calendarData['userType'] === 'referee')
                <div id="referee-calendar-root"></div>
            @else
                <div id="admin-calendar-root"></div>
            @endif
        </div>
    </div>

    {{-- Pass data to JavaScript --}}
    <script>
        @if($calendarData['userType'] === 'referee')
            window.refereeCalendarData = @json($calendarData);
        @else
            window.adminCalendarData = @json($calendarData);
        @endif

        // Handle filters
        document.addEventListener('DOMContentLoaded', function() {
            const zoneFilter = document.getElementById('zoneFilter');
            const typeFilter = document.getElementById('typeFilter');

            function updateFilters() {
                const url = new URL(window.location);

                if (zoneFilter && zoneFilter.value) {
                    url.searchParams.set('zone_id', zoneFilter.value);
                } else {
                    url.searchParams.delete('zone_id');
                }

                if (typeFilter && typeFilter.value) {
                    url.searchParams.set('type_id', typeFilter.value);
                } else {
                    url.searchParams.delete('type_id');
                }

                window.location.href = url.toString();
            }

            if (zoneFilter) {
                zoneFilter.addEventListener('change', updateFilters);
            }

            if (typeFilter) {
                typeFilter.addEventListener('change', updateFilters);
            }
        });
    </script>
@endsection

@vite(['resources/js/app.js'])
