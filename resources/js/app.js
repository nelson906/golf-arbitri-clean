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

// =================================================================
// Calendario — mounting helper centralizzato
// =================================================================

function createErrorDisplay(container, error, label) {
    container.innerHTML = `
        <div class="calendar-error-container p-6 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Errore nel caricamento del calendario</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p>${error.message || 'Si è verificato un errore imprevisto.'}</p>
                        ${label ? `<p class="mt-1"><strong>Tipo:</strong> ${label}</p>` : ''}
                    </div>
                    <div class="mt-4">
                        <div class="-mx-2 -my-1.5 flex">
                            <button onclick="window.location.reload()" class="bg-red-100 px-2 py-1.5 rounded-md text-sm font-medium text-red-800 hover:bg-red-200">
                                Riprova
                            </button>
                            <button onclick="this.closest('.calendar-error-container').style.display='none'" class="ml-3 bg-red-100 px-2 py-1.5 rounded-md text-sm font-medium text-red-800 hover:bg-red-200">
                                Nascondi
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function createLoadingState(container) {
    container.innerHTML = `
        <div class="flex items-center justify-center h-64">
            <div class="text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                <p class="mt-4 text-gray-600">Caricamento calendario...</p>
            </div>
        </div>
    `;
}

function validateCalendarData(data, type) {
    if (!data || !Array.isArray(data.tournaments) || !data.userType) {
        return false;
    }
    if (type === 'admin') {
        return ['admin', 'national_admin', 'super_admin'].some(r =>
            data.userType === r || data.userRoles?.includes(r)
        );
    }
    if (type === 'referee') {
        return data.userType === 'referee';
    }
    return true; // public
}

function mountCalendar(containerId, Component, calendarDataKey, type, label) {
    const container = document.getElementById(containerId);
    if (!container) return;

    createLoadingState(container);

    try {
        const calendarData = window[calendarDataKey] || {};

        if (calendarData.error_state === 'error') {
            throw new Error(calendarData.error || 'Errore dal server');
        }
        if (!validateCalendarData(calendarData, type)) {
            throw new Error(`Dati calendario non validi per tipo "${type}"`);
        }

        createRoot(container).render(React.createElement(Component, { calendarData }));
    } catch (error) {
        createErrorDisplay(container, error, label);
    }
}

// =================================================================
// Mount calendari
// =================================================================

document.addEventListener('DOMContentLoaded', () => {
    mountCalendar('admin-calendar-root',   AdminCalendar,   'adminCalendarData',   'admin',   'Calendario Admin');
    mountCalendar('referee-calendar-root', RefereeCalendar, 'refereeCalendarData', 'referee', 'Calendario Arbitro');
    mountCalendar('public-calendar-root',  PublicCalendar,  'publicCalendarData',  'public',  'Calendario Pubblico');
});
