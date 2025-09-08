import React, { useState, useEffect } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import itLocale from '@fullcalendar/core/locales/it';

const AdminCalendar = ({ calendarData }) => {
    const [selectedEvent, setSelectedEvent] = useState(null);
    const [showModal, setShowModal] = useState(false);
    const [zoneFilter, setZoneFilter] = useState('');

    // Check if user is national admin
    const isNationalAdmin = calendarData?.userType === 'national_admin' ||
                           calendarData?.userRoles?.includes('national_admin');

    // Filter events by zone for national admins
    const getFilteredEvents = () => {
        if (!zoneFilter || !isNationalAdmin) {
            return calendarData?.tournaments || [];
        }

        return calendarData.tournaments.filter(event =>
            event.extendedProps?.zone === zoneFilter
        );
    };

    const handleEventClick = (info) => {
        setSelectedEvent(info.event);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setSelectedEvent(null);
    };

    // Handle edit tournament
    const handleEditTournament = (tournamentId, e) => {
        if (e) {
            e.stopPropagation();
        }
        window.location.href = `/admin/tournaments/${tournamentId}/edit`;
    };

    // Handle delete tournament
    const handleDeleteTournament = (tournamentId, e) => {
        e.stopPropagation();
        if (confirm('Sei sicuro di voler eliminare questo torneo? Questa azione è irreversibile.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/admin/tournaments/${tournamentId}`;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (csrfToken) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);
            }

            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'DELETE';
            form.appendChild(methodInput);

            document.body.appendChild(form);
            form.submit();
        }
    };

    const getPriorityBadge = (priority) => {
        const badges = {
            'urgent': 'bg-red-100 text-red-800',
            'complete': 'bg-green-100 text-green-800',
            'in_progress': 'bg-yellow-100 text-yellow-800',
            'open': 'bg-blue-100 text-blue-800'
        };

        const texts = {
            'urgent': 'URGENTE',
            'complete': 'Completo',
            'in_progress': 'In Progress',
            'open': 'Aperto'
        };

        return (
            <span className={`px-2 py-1 text-xs font-medium rounded-full ${badges[priority] || badges.open}`}>
                {texts[priority] || texts.open}
            </span>
        );
    };

    const getStatusBadge = (status) => {
        const badges = {
            'published': 'bg-green-100 text-green-800',
            'draft': 'bg-yellow-100 text-yellow-800',
            'closed': 'bg-gray-100 text-gray-800',
            'cancelled': 'bg-red-100 text-red-800'
        };

        const texts = {
            'published': 'Pubblicato',
            'draft': 'Bozza',
            'closed': 'Chiuso',
            'cancelled': 'Annullato'
        };

        return (
            <span className={`px-2 py-1 text-xs font-medium rounded-full ${badges[status] || badges.draft}`}>
                {texts[status] || status}
            </span>
        );
    };

    if (!calendarData?.tournaments) {
        return (
            <div className="text-center py-8 text-gray-600">
                <h3 className="text-lg font-medium">Nessun dato disponibile</h3>
                <p>Verifica la connessione al database.</p>
            </div>
        );
    }

    return (
        <div className="admin-calendar">
            {/* Zone Filter for National Admins */}
            {isNationalAdmin && calendarData.zones && calendarData.zones.length > 0 && (
                <div className="mb-6 bg-white rounded-lg shadow p-4">
                    <div className="flex items-center gap-4">
                        <label className="text-sm font-medium text-gray-700">Filtra per zona:</label>
                        <select
                            value={zoneFilter}
                            onChange={(e) => setZoneFilter(e.target.value)}
                            className="border-gray-300 rounded-md shadow-sm text-sm"
                        >
                            <option value="">Tutte le zone</option>
                            {calendarData.zones.map(zone => (
                                <option key={zone.id} value={zone.name}>{zone.name}</option>
                            ))}
                        </select>
                    </div>
                </div>
            )}


            {/* Calendar */}
            <div className="bg-white rounded-lg shadow">
                <div className="p-4">
                    <FullCalendar
                        plugins={[dayGridPlugin, listPlugin]}
                        initialView="dayGridMonth"
                        locale={itLocale}
                        headerToolbar={{
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,listWeek'
                        }}
                        height="auto"
                        events={getFilteredEvents()}
                        eventClick={handleEventClick}
                        eventDidMount={(info) => {
                            const props = info.event.extendedProps;
                            info.el.title = `${info.event.title} - ${props.club} (${props.management_priority})`;
                        }}
                        eventContent={(eventInfo) => {
                            // Show admin controls on hover
                            const isFirstDay = eventInfo.isStart;
                            const status = eventInfo.event.extendedProps.status;

                            return (
                                <div className="fc-event-container relative group">
                                    <div className="fc-event-title font-medium flex items-center">
                                        {eventInfo.event.title}
                                        {isFirstDay && status && (
                                            <span className="ml-1 text-xs rounded-full px-1 py-0.5 inline-block"
                                                style={{
                                                    backgroundColor: eventInfo.event.extendedProps.statusBorder || '#6B7280',
                                                    color: '#fff',
                                                    fontSize: '0.6rem'
                                                }}>
                                                {status}
                                            </span>
                                        )}
                                    </div>
                                    <div className="fc-event-text text-xs truncate">
                                        {eventInfo.event.extendedProps.club || "Club N/A"}
                                    </div>

                                    {/* Display tournament category if first day */}
                                    {isFirstDay && eventInfo.event.extendedProps.category && (
                                        <div className="fc-event-category text-xs">
                                            <span className="font-medium">
                                                {eventInfo.event.extendedProps.category}
                                            </span>
                                        </div>
                                    )}

                                    {/* Admin controls - show on hover */}
                                    {isFirstDay && (
                                        <div className="event-controls opacity-0 group-hover:opacity-100 absolute top-0 right-0 bg-white bg-opacity-90 rounded-bl-md p-1 shadow transition-opacity duration-200 z-10">
                                            <button
                                                onClick={(e) => handleEditTournament(eventInfo.event.id, e)}
                                                className="text-blue-600 hover:text-blue-800 mr-2 focus:outline-none"
                                                title="Modifica torneo"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                onClick={(e) => handleDeleteTournament(eventInfo.event.id, e)}
                                                className="text-red-600 hover:text-red-800 focus:outline-none"
                                                title="Elimina torneo"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    )}
                                </div>
                            );
                        }}
                    />
                </div>
            </div>

            {/* Management Modal */}
            {showModal && selectedEvent && (
                <div className="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div className="flex items-center justify-center min-h-screen p-4">
                        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={closeModal}></div>
                        <div className="bg-white rounded-lg shadow-xl max-w-lg w-full z-10 relative">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4" id="modal-title">
                                    {selectedEvent.title}
                                </h3>

                                <div className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4 text-sm">
                                        <div><span className="font-medium">Club:</span> {selectedEvent.extendedProps.club}</div>
                                        <div><span className="font-medium">Zona:</span> {selectedEvent.extendedProps.zone}</div>
                                        <div><span className="font-medium">Categoria:</span> {selectedEvent.extendedProps.category}</div>
                                        <div>
                                            <span className="font-medium">Status:</span>
                                            <span className="ml-2">{getStatusBadge(selectedEvent.extendedProps.status)}</span>
                                        </div>
                                        <div>
                                            <span className="font-medium">Priorità:</span>
                                            <span className="ml-2">{getPriorityBadge(selectedEvent.extendedProps.management_priority)}</span>
                                        </div>
                                    </div>

                                    <div className="border-t pt-4">
                                        <h4 className="font-medium text-gray-700 mb-2">Statistiche Gestionali</h4>
                                        <div className="grid grid-cols-3 gap-4 text-sm text-center">
                                            <div className="bg-gray-50 p-3 rounded">
                                                <div className="font-medium text-lg">{selectedEvent.extendedProps.availabilities_count || 0}</div>
                                                <div className="text-gray-600">Disponibilità</div>
                                            </div>
                                            <div className="bg-gray-50 p-3 rounded">
                                                <div className="font-medium text-lg">{selectedEvent.extendedProps.assignments_count || 0}</div>
                                                <div className="text-gray-600">Assegnati</div>
                                            </div>
                                            <div className="bg-gray-50 p-3 rounded">
                                                <div className="font-medium text-lg">{selectedEvent.extendedProps.required_referees || 0}</div>
                                                <div className="text-gray-600">Richiesti</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="border-t pt-4 text-sm">
                                        <span className="font-medium">Scadenza disponibilità:</span> {selectedEvent.extendedProps.deadline || 'N/A'}
                                        <span className="ml-2 text-xs">
                                            ({(selectedEvent.extendedProps.days_until_deadline || 0) < 0 ? 'Scaduta!' : (selectedEvent.extendedProps.days_until_deadline || 0) + ' giorni'})
                                        </span>
                                    </div>
                                </div>

                                <div className="mt-6 flex justify-end space-x-3">
                                    <button
                                        onClick={closeModal}
                                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200"
                                    >
                                        Chiudi
                                    </button>
                                    <button
                                        onClick={(e) => handleEditTournament(selectedEvent.id, e)}
                                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
                                    >
                                        Modifica Torneo
                                    </button>
                                    <a
                                        href={selectedEvent.extendedProps.tournament_url}
                                        className="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700"
                                    >
                                        Visualizza Dettagli
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Results Counter */}
            <div className="mt-4 text-sm text-gray-600">
                {getFilteredEvents().length} tornei visualizzati
                {zoneFilter && (
                    <button
                        onClick={() => setZoneFilter('')}
                        className="ml-2 text-blue-600 hover:text-blue-800"
                    >
                        Rimuovi filtro zona
                    </button>
                )}
            </div>
        </div>
    );
};

export default AdminCalendar;
