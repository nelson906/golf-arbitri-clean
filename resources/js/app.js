// Enhanced app.js with comprehensive error handling
import './bootstrap.js';
import Alpine from 'alpinejs'

// Initialize Alpine
Alpine.start()
window.Alpine = Alpine

// Import React and calendar components
import React from 'react';
import { createRoot } from 'react-dom/client';
import AdminCalendar from './Components/Calendar/AdminCalendar.jsx';
import RefereeCalendar from './Components/Calendar/RefereeCalendar.jsx';
import PublicCalendar from './Components/Calendar/PublicCalendar.jsx';

// Error handling utilities
const CalendarErrorHandler = {
    /**
     * Log error to console and optionally to server
     */
    logError: (error, context = {}) => {
        console.error('Calendar Error:', error);
        console.error('Context:', context);

        // Optional: Send to server logging endpoint
        if (window.reportError) {
            window.reportError({
                error: error.message,
                stack: error.stack,
                context: context,
                url: window.location.href,
                userAgent: navigator.userAgent,
                timestamp: new Date().toISOString()
            });
        }
    },

    /**
     * Create error display component
     */
    createErrorDisplay: (container, error, context = {}) => {
        const errorHtml = `
            <div class="calendar-error-container p-6 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">
                            Errore nel caricamento del calendario
                        </h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p>${error.message || 'Si è verificato un errore imprevisto.'}</p>
                            ${context.type ? `<p class="mt-1"><strong>Tipo:</strong> ${context.type}</p>` : ''}
                        </div>
                        <div class="mt-4">
                            <div class="-mx-2 -my-1.5 flex">
                                <button onclick="window.location.reload()" class="bg-red-100 px-2 py-1.5 rounded-md text-sm font-medium text-red-800 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-red-50 focus:ring-red-600">
                                    Riprova
                                </button>
                                <button onclick="this.closest('.calendar-error-container').style.display='none'" class="ml-3 bg-red-100 px-2 py-1.5 rounded-md text-sm font-medium text-red-800 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-red-50 focus:ring-red-600">
                                    Nascondi
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = errorHtml;
    },

    /**
     * Validate calendar data
     */
    validateCalendarData: (data, type) => {
        const errors = [];

        if (!data) {
            errors.push('Dati calendario mancanti');
            return errors;
        }

        if (!Array.isArray(data.tournaments)) {
            errors.push('Dati tornei non validi');
        }

        if (!data.userType) {
            errors.push('Tipo utente non specificato');
        }

        // Type-specific validations
        switch (type) {
            case 'admin':
                if (data.userType !== 'admin' && !data.userRoles?.includes('admin')) {
                    errors.push('Dati admin non validi');
                }
                break;
            case 'referee':
                if (data.userType !== 'referee') {
                    errors.push('Dati referee non validi');
                }
                break;
            case 'public':
                // Public calendar is less strict
                break;
        }

        return errors;
    },

    /**
     * Create loading state
     */
    createLoadingState: (container) => {
        container.innerHTML = `
            <div class="calendar-loading-container flex items-center justify-center h-64">
                <div class="text-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                    <p class="mt-4 text-gray-600">Caricamento calendario...</p>
                </div>
            </div>
        `;
    }
};

// Global error handler for unhandled React errors
window.addEventListener('error', (event) => {
    if (event.filename && event.filename.includes('calendar')) {
        CalendarErrorHandler.logError(event.error, {
            type: 'global_error',
            filename: event.filename,
            lineno: event.lineno,
            colno: event.colno
        });
    }
});

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', () => {

    // =================================================================
    // ADMIN CALENDAR - Management Focus with Error Handling
    // =================================================================
    const adminContainer = document.getElementById('admin-calendar-root');
    if (adminContainer) {
        try {
            CalendarErrorHandler.createLoadingState(adminContainer);

            const calendarData = window.adminCalendarData || {};

            console.log('🔧 Initializing Admin Calendar with data:', calendarData);

            // Validate data
            const validationErrors = CalendarErrorHandler.validateCalendarData(calendarData, 'admin');
            if (validationErrors.length > 0) {
                throw new Error(`Errori validazione dati: ${validationErrors.join(', ')}`);
            }

            // Check for backend error state
            if (calendarData.error_state === 'error') {
                throw new Error(calendarData.error || 'Errore dal server');
            }

            const root = createRoot(adminContainer);
            root.render(React.createElement(AdminCalendar, {
                calendarData: calendarData
            }));

            console.log('✅ Admin Calendar initialized successfully');

        } catch (error) {
            console.error('❌ Error initializing Admin Calendar:', error);
            CalendarErrorHandler.logError(error, { type: 'admin_calendar_init' });
            CalendarErrorHandler.createErrorDisplay(adminContainer, error, { type: 'Calendario Admin' });
        }
    }

    // =================================================================
    // REFEREE CALENDAR - Personal Focus with Error Handling
    // =================================================================
    const refereeContainer = document.getElementById('referee-calendar-root');
    if (refereeContainer) {
        try {
            CalendarErrorHandler.createLoadingState(refereeContainer);

            const calendarData = window.refereeCalendarData || {};

            console.log('👨‍⚖️ Initializing Referee Calendar with data:', calendarData);

            // Validate data
            const validationErrors = CalendarErrorHandler.validateCalendarData(calendarData, 'referee');
            if (validationErrors.length > 0) {
                throw new Error(`Errori validazione dati: ${validationErrors.join(', ')}`);
            }

            // Check for backend error state
            if (calendarData.error_state === 'error') {
                throw new Error(calendarData.error || 'Errore dal server');
            }

            const root = createRoot(refereeContainer);
            root.render(React.createElement(RefereeCalendar, {
                calendarData: calendarData
            }));

            console.log('✅ Referee Calendar initialized successfully');

        } catch (error) {
            console.error('❌ Error initializing Referee Calendar:', error);
            CalendarErrorHandler.logError(error, { type: 'referee_calendar_init' });
            CalendarErrorHandler.createErrorDisplay(refereeContainer, error, { type: 'Calendario Arbitro' });
        }
    }

    // =================================================================
    // PUBLIC CALENDAR - View Only Focus with Error Handling
    // =================================================================
    const publicContainer = document.getElementById('public-calendar-root');
    if (publicContainer) {
        try {
            CalendarErrorHandler.createLoadingState(publicContainer);

            const calendarData = window.publicCalendarData || {};

            console.log('🌐 Initializing Public Calendar with data:', calendarData);

            // Validate data
            const validationErrors = CalendarErrorHandler.validateCalendarData(calendarData, 'public');
            if (validationErrors.length > 0) {
                throw new Error(`Errori validazione dati: ${validationErrors.join(', ')}`);
            }

            // Check for backend error state
            if (calendarData.error_state === 'error') {
                throw new Error(calendarData.error || 'Errore dal server');
            }

            const root = createRoot(publicContainer);
            root.render(React.createElement(PublicCalendar, {
                calendarData: calendarData
            }));

            console.log('✅ Public Calendar initialized successfully');

        } catch (error) {
            console.error('❌ Error initializing Public Calendar:', error);
            CalendarErrorHandler.logError(error, { type: 'public_calendar_init' });
            CalendarErrorHandler.createErrorDisplay(publicContainer, error, { type: 'Calendario Pubblico' });
        }
    }

    // =================================================================
    // LEGACY SUPPORT with Error Handling
    // =================================================================

    // Support for old tournament-calendar-root
    const legacyTournamentContainer = document.getElementById('tournament-calendar-root');
    if (legacyTournamentContainer && !publicContainer) {
        console.log('🔄 Legacy tournament calendar detected, converting to public calendar');

        try {
            CalendarErrorHandler.createLoadingState(legacyTournamentContainer);

            const calendarData = window.calendarData || window.publicCalendarData || {};

            // Validate legacy data
            const validationErrors = CalendarErrorHandler.validateCalendarData(calendarData, 'public');
            if (validationErrors.length > 0) {
                throw new Error(`Errori validazione dati legacy: ${validationErrors.join(', ')}`);
            }

            legacyTournamentContainer.id = 'public-calendar-root-legacy';

            const root = createRoot(legacyTournamentContainer);
            root.render(React.createElement(PublicCalendar, {
                calendarData: calendarData
            }));

            console.log('✅ Legacy tournament calendar converted successfully');

        } catch (error) {
            console.error('❌ Error converting legacy tournament calendar:', error);
            CalendarErrorHandler.logError(error, { type: 'legacy_tournament_calendar' });
            CalendarErrorHandler.createErrorDisplay(legacyTournamentContainer, error, { type: 'Calendario Legacy' });
        }
    }

    // Support for old availability-calendar-root
    const legacyAvailabilityContainer = document.getElementById('availability-calendar-root');
    if (legacyAvailabilityContainer && !refereeContainer) {
        console.log('🔄 Legacy availability calendar detected, converting to referee calendar');

        try {
            CalendarErrorHandler.createLoadingState(legacyAvailabilityContainer);

            const calendarData = window.availabilityCalendarData || window.refereeCalendarData || {};

            // Validate legacy data
            const validationErrors = CalendarErrorHandler.validateCalendarData(calendarData, 'referee');
            if (validationErrors.length > 0) {
                throw new Error(`Errori validazione dati availability legacy: ${validationErrors.join(', ')}`);
            }

            legacyAvailabilityContainer.id = 'referee-calendar-root-legacy';

            const root = createRoot(legacyAvailabilityContainer);
            root.render(React.createElement(RefereeCalendar, {
                calendarData: calendarData
            }));

            console.log('✅ Legacy availability calendar converted successfully');

        } catch (error) {
            console.error('❌ Error converting legacy availability calendar:', error);
            CalendarErrorHandler.logError(error, { type: 'legacy_availability_calendar' });
            CalendarErrorHandler.createErrorDisplay(legacyAvailabilityContainer, error, { type: 'Calendario Availability Legacy' });
        }
    }

    // =================================================================
    // DEBUG INFO & FINAL CHECKS
    // =================================================================
    const calendarContainers = [
        'admin-calendar-root',
        'referee-calendar-root',
        'public-calendar-root',
        'tournament-calendar-root',
        'availability-calendar-root'
    ];

    const foundContainers = calendarContainers.filter(id => document.getElementById(id));

    if (foundContainers.length > 0) {
        console.log('📅 Calendar containers found:', foundContainers);
    } else {
        console.log('ℹ️ No calendar containers found on this page');
    }

    // Global calendar data debug
    const availableData = [];
    if (window.adminCalendarData) availableData.push('adminCalendarData');
    if (window.refereeCalendarData) availableData.push('refereeCalendarData');
    if (window.publicCalendarData) availableData.push('publicCalendarData');
    if (window.calendarData) availableData.push('calendarData (legacy)');
    if (window.availabilityCalendarData) availableData.push('availabilityCalendarData (legacy)');

    console.log('📊 Available calendar data:', availableData);
});
