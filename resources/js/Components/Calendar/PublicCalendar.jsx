import React, { useState, useEffect } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import itLocale from '@fullcalendar/core/locales/it';

const PublicCalendar = ({ calendarData }) => {
    const [filteredTournaments, setFilteredTournaments] = useState(calendarData?.tournaments || []);
    const [selectedZone, setSelectedZone] = useState('all');
    const [selectedType, setSelectedType] = useState('all');
    const [selectedEvent, setSelectedEvent] = useState(null);
    const [showModal, setShowModal] = useState(false);

    // Apply filters whenever selectedZone or selectedType changes
    useEffect(() => {
        filterTournaments();
    }, [selectedZone, selectedType, calendarData]);

    const filterTournaments = () => {
        if (!calendarData?.tournaments || calendarData.tournaments.length === 0) {
            setFilteredTournaments([]);
            return;
        }

        let filtered = [...calendarData.tournaments];

        // Filter by zone
        if (selectedZone !== 'all') {
            filtered = filtered.filter(tournament => {
                const zoneId = tournament.extendedProps?.zone_id || tournament.club?.zone_id;
                return zoneId !== undefined && zoneId !== null && zoneId.toString() === selectedZone;
            });
        }

        // Filter by type
        if (selectedType !== 'all') {
            filtered = filtered.filter(tournament => {
                const typeId = tournament.extendedProps?.type_id || tournament.type?.id;
                return typeId !== undefined && typeId !== null && typeId.toString() === selectedType;
            });
        }

        setFilteredTournaments(filtered);
    };

    const handleEventClick = (info) => {
        // Redirect to tournament detail page instead of modal
        if (info.event.extendedProps?.tournament_url) {
            window.location.href = info.event.extendedProps.tournament_url;
        } else {
            // Fallback: show modal for basic info
            setSelectedEvent(info.event);
            setShowModal(true);
        }
    };

    const closeModal = () => {
        setShowModal(false);
        setSelectedEvent(null);
    };

    const getStatusBadge = (status) => {
        const badges = {
            'published': 'bg-green-100 text-green-800',
            'draft': 'bg-yellow-100 text-yellow-800',
            'closed': 'bg-gray-100 text-gray-800',
            'cancelled': 'bg-red-100 text-red-800',
            'in_progress': 'bg-blue-100 text-blue-800'
        };

        const texts = {
            'published': 'Pubblicato',
            'draft': 'Bozza',
            'closed': 'Chiuso',
            'cancelled': 'Annullato',
            'in_progress': 'In Corso'
        };

        return (
            <span className={`px-2 py-1 text-xs font-medium rounded-full ${badges[status] || badges.published}`}>
                {texts[status] || status}
            </span>
        );
    };

    if (!calendarData?.tournaments) {
        return (
            <div className="text-center py-8 text-gray-600">
                <h3 className="text-lg font-medium">Nessun torneo disponibile</h3>
                <p>Non ci sono tornei programmati al momento.</p>
            </div>
        );
    }

    return (
        <div className="public-calendar">
            {/* Filter Controls */}
            <div className="mb-6 bg-white rounded-lg shadow p-4">
                <h3 className="text-sm font-medium text-gray-700 mb-3">Filtri</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {/* Zone Filter */}
                    {calendarData.zones && calendarData.zones.length > 0 && (
                        <div>
                            <label htmlFor="zone-select" className="block text-sm font-medium text-gray-700 mb-1">
                                Zona
                            </label>
                            <select
                                id="zone-select"
                                className="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                                value={selectedZone}
                                onChange={(e) => setSelectedZone(e.target.value)}
                            >
                                <option value="all">Tutte le zone</option>
                                {calendarData.zones.map(zone => (
                                    <option key={zone.id} value={zone.id.toString()}>
                                        {zone.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {/* Type Filter */}
                    {calendarData.types && calendarData.types.length > 0 && (
                        <div>
                            <label htmlFor="type-select" className="block text-sm font-medium text-gray-700 mb-1">
                                Tipo Torneo
                            </label>
                            <select
                                id="type-select"
                                className="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                                value={selectedType}
                                onChange={(e) => setSelectedType(e.target.value)}
                            >
                                <option value="all">Tutti i tipi</option>
                                {calendarData.types.map(type => (
                                    <option key={type.id} value={type.id.toString()}>
                                        {type.name} {type.is_national ? '(Nazionale)' : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>
            </div>

            {/* Public Legend */}
            <div className="mb-6 bg-white rounded-lg shadow p-4">
                <h3 className="text-sm font-medium text-gray-700 mb-3">Legenda</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <div>
                        <h4 className="font-medium text-gray-600 mb-2">Colori (Categoria Torneo):</h4>
                        <div className="space-y-1">
                            <div className="flex items-center">
                                <div className="w-4 h-4 rounded mr-2" style={{backgroundColor: '#FF6B6B'}}></div>
                                <span>Categoria A</span>
                            </div>
                            <div className="flex items-center">
                                <div className="w-4 h-4 rounded mr-2" style={{backgroundColor: '#4ECDC4'}}></div>
                                <span>Categoria B</span>
                            </div>
                            <div className="flex items-center">
                                <div className="w-4 h-4 rounded mr-2" style={{backgroundColor: '#45B7D1'}}></div>
                                <span>Categoria C</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h4 className="font-medium text-gray-600 mb-2">Status Torneo:</h4>
                        <div className="space-y-1">
                            <div className="flex items-center">
                                <div className="w-4 h-4 rounded mr-2 bg-green-100"></div>
                                <span>Pubblicato</span>
                            </div>
                            <div className="flex items-center">
                                <div className="w-4 h-4 rounded mr-2 bg-blue-100"></div>
                                <span>In Corso</span>
                            </div>
                            <div className="flex items-center">
                                <div className="w-4 h-4 rounded mr-2 bg-gray-100"></div>
                                <span>Chiuso</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                        events={filteredTournaments}
                        eventClick={handleEventClick}
                        eventDidMount={(info) => {
                            const props = info.event.extendedProps;
                            info.el.title = `${info.event.title} - ${props.club || 'Club N/A'}`;
                            // Add click cursor
                            info.el.style.cursor = 'pointer';
                        }}
                        eventContent={(eventInfo) => {
                            const status = eventInfo.event.extendedProps.status;

                            return (
                                <div className="fc-event-container">
                                    <div className="fc-event-title font-medium">
                                        {eventInfo.event.title}
                                        {status && (
                                            <span className="ml-1 text-xs rounded-full px-1 py-0.5 inline-block bg-white bg-opacity-30">
                                                {status}
                                            </span>
                                        )}
                                    </div>
                                    <div className="fc-event-text text-xs truncate">
                                        {eventInfo.event.extendedProps.club || "Club N/A"}
                                    </div>
                                    {eventInfo.event.extendedProps.category && (
                                        <div className="fc-event-category text-xs">
                                            {eventInfo.event.extendedProps.category}
                                        </div>
                                    )}
                                </div>
                            );
                        }}
                    />
                </div>
            </div>

            {/* Basic Info Modal (Fallback) */}
            {showModal && selectedEvent && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex items-center justify-center min-h-screen p-4">
                        <div className="fixed inset-0 bg-gray-500 bg-opacity-75" onClick={closeModal}></div>
                        <div className="bg-white rounded-lg shadow-xl max-w-lg w-full z-10">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">
                                    {selectedEvent.title}
                                </h3>

                                <div className="space-y-3">
                                    <div className="grid grid-cols-2 gap-4 text-sm">
                                        <div><span className="font-medium">Club:</span> {selectedEvent.extendedProps.club || 'N/A'}</div>
                                        <div><span className="font-medium">Categoria:</span> {selectedEvent.extendedProps.category || 'N/A'}</div>
                                        <div>
                                            <span className="font-medium">Status:</span>
                                            <span className="ml-2">{getStatusBadge(selectedEvent.extendedProps.status)}</span>
                                        </div>
                                    </div>

                                    <div className="border-t pt-3 text-sm">
                                        <span className="font-medium">Date:</span>
                                        <span className="ml-2">
                                            {new Intl.DateTimeFormat('it-IT', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric'
                                            }).format(new Date(selectedEvent.start))}
                                            {selectedEvent.end && (
                                                ` - ${new Intl.DateTimeFormat('it-IT', {
                                                    year: 'numeric',
                                                    month: 'long',
                                                    day: 'numeric'
                                                }).format(new Date(new Date(selectedEvent.end).getTime() - 86400000))}`
                                            )}
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
                                    {selectedEvent.extendedProps.tournament_url && (
                                        <a
                                            href={selectedEvent.extendedProps.tournament_url}
                                            className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
                                        >
                                            Dettagli Torneo
                                        </a>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Results Counter */}
            <div className="mt-4 text-sm text-gray-600">
                {filteredTournaments.length} tornei visualizzati
                {(selectedZone !== 'all' || selectedType !== 'all') && (
                    <button
                        onClick={() => {
                            setSelectedZone('all');
                            setSelectedType('all');
                        }}
                        className="ml-2 text-blue-600 hover:text-blue-800"
                    >
                        Rimuovi filtri
                    </button>
                )}
            </div>
        </div>
    );
};

export default PublicCalendar;
