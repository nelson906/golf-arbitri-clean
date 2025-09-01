@extends('layouts.app')

@section('title', 'Calendario Disponibilità')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Calendario Disponibilità</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Visualizza le tue disponibilità e assegnazioni nel calendario
                    </p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('user.availability.index') }}"
                       class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Vista Lista
                    </a>
                    <a href="{{ route('user.availability.tournaments') }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Dichiara Disponibilità
                    </a>
                </div>
            </div>
        </div>

        {{-- Legend --}}
        <div class="mb-4 bg-white rounded-lg shadow p-4">
            <h3 class="text-sm font-medium text-gray-700 mb-3">Legenda</h3>
            
            {{-- Stati utente --}}
            <div class="mb-3">
                <h4 class="text-xs font-medium text-gray-600 mb-2">Il tuo stato</h4>
                <div class="flex flex-wrap gap-4 text-sm">
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-green-500 rounded mr-2"></div>
                        <span>Assegnato</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-amber-500 rounded mr-2"></div>
                        <span>Disponibile</span>
                    </div>
                </div>
            </div>
            
            {{-- Tipi di torneo presenti --}}
            <div id="tournament-types-legend">
                <h4 class="text-xs font-medium text-gray-600 mb-2">Categorie tornei</h4>
                <div class="flex flex-wrap gap-3 text-xs">
                    {{-- Verrà popolato dinamicamente da JavaScript --}}
                </div>
            </div>
        </div>

        {{-- Calendar Container --}}
        <div class="bg-white rounded-lg shadow">
            <div id="user-calendar" class="p-4"></div>
        </div>
    </div>
</div>

{{-- Calendar Scripts --}}
@push('scripts')
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('user-calendar');
    var calendarData = @json($calendarData);
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'it',
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth'
        },
        events: calendarData.tournaments,
        eventDidMount: function(info) {
            // Add tooltip
            var props = info.event.extendedProps;
            var tooltipContent = `
                <div class="text-sm">
                    <div class="font-medium">${info.event.title}</div>
                    <div>Circolo: ${props.club}</div>
                    <div>Zona: ${props.zone}</div>
                    <div>Categoria: ${props.category}</div>
                    ${props.is_assigned ? '<div class="text-green-600 font-medium">✓ Assegnato</div>' : ''}
                    ${props.is_available && !props.is_assigned ? '<div class="text-amber-600 font-medium">✓ Disponibile</div>' : ''}
                </div>
            `;
            
            // You can use a tooltip library here
            info.el.setAttribute('title', info.event.title);
        },
        eventClick: function(info) {
            var props = info.event.extendedProps;
            
            // Create modal or alert with tournament details
            var message = `
                Torneo: ${info.event.title}
                Circolo: ${props.club}
                Zona: ${props.zone}
                Categoria: ${props.category}
                Stato: ${props.is_assigned ? 'Assegnato' : (props.is_available ? 'Disponibile' : 'Non disponibile')}
            `;
            
            if (props.can_declare && !props.is_available) {
                if (confirm(message + '\n\nVuoi dichiarare la tua disponibilità per questo torneo?')) {
                    // Redirect to availability declaration
                    window.location.href = '{{ route("user.availability.tournaments") }}?tournament_id=' + info.event.id;
                }
            } else {
                alert(message);
            }
        }
    });
    
    calendar.render();
    
    // Popola la legenda dei tipi di torneo
    if (calendarData.tournamentTypes && calendarData.tournamentTypes.length > 0) {
        var legendContainer = document.querySelector('#tournament-types-legend .flex');
        if (legendContainer) {
            calendarData.tournamentTypes.forEach(function(type) {
                var legendItem = document.createElement('div');
                legendItem.className = 'flex items-center';
                legendItem.innerHTML = `
                    <div class="w-3 h-3 rounded mr-1" style="background-color: ${type.color};"></div>
                    <span>${type.short_name || type.name}</span>
                `;
                legendContainer.appendChild(legendItem);
            });
        }
    }
});
</script>
@endpush

{{-- Calendar Styles --}}
@push('styles')
<style>
    .fc-event {
        cursor: pointer;
        border-width: 2px;
    }
    .fc-event:hover {
        filter: brightness(0.9);
    }
</style>
@endpush
@endsection
