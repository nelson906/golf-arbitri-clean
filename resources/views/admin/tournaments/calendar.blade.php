@extends('layouts.admin')

@section('title', 'Calendario Tornei')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Calendario Tornei</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Visualizza i tornei in formato calendario
                </p>
            </div>
            <div class="flex space-x-3">
                {{-- Zone Filter --}}
                @if(isset($calendarData['zones']) && count($calendarData['zones']) > 0)
                    <select id="zoneFilter" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tutte le zone</option>
                        @foreach($calendarData['zones'] as $zone)
                            @if(is_array($zone))
                                <option value="{{ $zone['id'] }}">{{ $zone['name'] }}</option>
                            @else
                                <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                            @endif
                        @endforeach
                    </select>
                @endif

                {{-- Type Filter --}}
                @if(isset($calendarData['tournamentTypes']) && count($calendarData['tournamentTypes']) > 0)
                    <select id="typeFilter" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tutti i tipi</option>
                        @foreach($calendarData['tournamentTypes'] as $type)
                            @if(is_array($type))
                                <option value="{{ $type['id'] }}">{{ $type['name'] }}</option>
                            @else
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endif
                        @endforeach
                    </select>
                @endif

                <a href="{{ route('admin.tournaments.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Lista Tornei
                </a>
            </div>
        </div>
    </div>

    {{-- Legend --}}
    <div class="mb-4 p-4 bg-gray-50 rounded-lg">
        <h3 class="text-sm font-medium text-gray-700 mb-2">Legenda (per categoria):</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2">
            @if(isset($calendarData['tournamentTypes']))
                @foreach($calendarData['tournamentTypes']->take(12) as $type)
                    <div class="flex items-center space-x-2">
                        @if(is_array($type))
                            <span class="inline-block w-4 h-4 rounded" style="background-color: {{ $type['color'] ?? '#3B82F6' }}"></span>
                            <span class="text-xs">{{ $type['short_name'] ?? $type['name'] }}</span>
                        @else
                            <span class="inline-block w-4 h-4 rounded" style="background-color: {{ $type->calendar_color ?? '#3B82F6' }}"></span>
                            <span class="text-xs">{{ $type->short_name ?? $type->name }}</span>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- Calendar Container --}}
    <div class="bg-white rounded-lg shadow p-4">
        <div id="calendar"></div>
    </div>
</div>

@push('styles')
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
<style>
    .fc-event {
        cursor: pointer;
        border-radius: 4px;
        padding: 2px 4px;
    }
    .fc-event:hover {
        opacity: 0.9;
    }
    .fc-daygrid-event {
        white-space: normal;
    }
</style>
@endpush

@push('scripts')
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/it.global.min.js'></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const events = @json($calendarData['tournaments'] ?? []);

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'it',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: events,
        eventClick: function(info) {
            const props = info.event.extendedProps;
            if (props.tournament_url) {
                window.location.href = props.tournament_url;
            }
        },
        eventDidMount: function(info) {
            // Tooltip con dettagli
            const props = info.event.extendedProps;
            info.el.title = `${info.event.title}\n${props.club || ''}\nStato: ${props.status || 'N/A'}`;
        }
    });

    calendar.render();

    // Filtri
    const zoneFilter = document.getElementById('zoneFilter');
    const typeFilter = document.getElementById('typeFilter');

    function applyFilters() {
        const zoneId = zoneFilter ? zoneFilter.value : '';
        const typeId = typeFilter ? typeFilter.value : '';

        const filteredEvents = events.filter(event => {
            const props = event.extendedProps || {};
            const matchZone = !zoneId || props.zone_id == zoneId;
            const matchType = !typeId || props.type_id == typeId;
            return matchZone && matchType;
        });

        calendar.removeAllEvents();
        calendar.addEventSource(filteredEvents);
    }

    if (zoneFilter) zoneFilter.addEventListener('change', applyFilters);
    if (typeFilter) typeFilter.addEventListener('change', applyFilters);
});
</script>
@endpush
@endsection
