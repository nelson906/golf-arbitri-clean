// Montaggio dei calendari (app.js): quale calendario parte, con quali dati,
// e cosa vede l'utente quando i dati non vanno bene.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act } from 'react';

vi.mock('alpinejs', () => ({ default: { start: vi.fn() } }));
vi.mock('./Components/Calendar/AdminCalendar.jsx', async () => {
    const React = await import('react');
    return {
        default: ({ calendarData }) =>
            React.createElement('div', { 'data-testid': 'admin' }, `admin:${calendarData.tournaments.length}`),
    };
});
vi.mock('./Components/Calendar/RefereeCalendar.jsx', async () => {
    const React = await import('react');
    return {
        default: ({ calendarData }) =>
            React.createElement('div', { 'data-testid': 'referee' }, `referee:${calendarData.tournaments.length}`),
    };
});

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

// Ogni test re-importa app.js: il listener DOMContentLoaded del giro prima
// va tolto, altrimenti i calendari verrebbero montati due volte.
const listeners = [];

async function avvia() {
    vi.resetModules();
    const add = vi.spyOn(document, 'addEventListener');
    await import('./app.js');
    add.mock.calls
        .filter(([type]) => type === 'DOMContentLoaded')
        .forEach(([type, fn]) => listeners.push([type, fn]));
    add.mockRestore();
    await act(async () => {
        document.dispatchEvent(new Event('DOMContentLoaded'));
    });
}

function contenitore(id) {
    const el = document.createElement('div');
    el.id = id;
    document.body.appendChild(el);
    return el;
}

beforeEach(() => {
    document.body.innerHTML = '';
    delete window.adminCalendarData;
    delete window.refereeCalendarData;
});

afterEach(() => {
    listeners.splice(0).forEach(([type, fn]) => document.removeEventListener(type, fn));
    document.body.innerHTML = '';
});

describe('app.js — calendario admin', () => {
    it.each(['admin', 'national_admin', 'super_admin'])('monta per userType %s', async (userType) => {
        const el = contenitore('admin-calendar-root');
        window.adminCalendarData = { userType, tournaments: [{}, {}] };

        await avvia();

        expect(el.textContent).toBe('admin:2');
    });

    it('monta anche se il ruolo admin e\' solo in userRoles', async () => {
        const el = contenitore('admin-calendar-root');
        window.adminCalendarData = { userType: 'referee', userRoles: ['national_admin'], tournaments: [] };

        await avvia();

        expect(el.textContent).toBe('admin:0');
    });

    it('arbitro sul calendario admin: errore con il tipo', async () => {
        const el = contenitore('admin-calendar-root');
        window.adminCalendarData = { userType: 'referee', tournaments: [] };

        await avvia();

        expect(el.textContent).toContain('Errore nel caricamento del calendario');
        expect(el.textContent).toContain('Dati calendario non validi per tipo "admin"');
        expect(el.textContent).toContain('Calendario Admin');
    });

    it('dati assenti: errore, non pagina bianca', async () => {
        const el = contenitore('admin-calendar-root');

        await avvia();

        expect(el.textContent).toContain('Dati calendario non validi per tipo "admin"');
    });

    it('tournaments non lista: errore', async () => {
        const el = contenitore('admin-calendar-root');
        window.adminCalendarData = { userType: 'admin', tournaments: {} };

        await avvia();

        expect(el.textContent).toContain('Dati calendario non validi');
    });

    it('errore dal server: mostra il suo messaggio', async () => {
        const el = contenitore('admin-calendar-root');
        window.adminCalendarData = { error_state: 'error', error: 'Database non raggiungibile' };

        await avvia();

        expect(el.textContent).toContain('Database non raggiungibile');
    });

    it('errore dal server senza messaggio: "Errore dal server"', async () => {
        const el = contenitore('admin-calendar-root');
        window.adminCalendarData = { error_state: 'error' };

        await avvia();

        expect(el.textContent).toContain('Errore dal server');
    });

    it('il riquadro d\'errore ha Riprova e Nascondi', async () => {
        const el = contenitore('admin-calendar-root');

        await avvia();

        const testi = [...el.querySelectorAll('button')].map((b) => b.textContent.trim());
        expect(testi).toEqual(['Riprova', 'Nascondi']);
    });
});

describe('app.js — calendario arbitro', () => {
    it('monta per userType referee', async () => {
        const el = contenitore('referee-calendar-root');
        window.refereeCalendarData = { userType: 'referee', tournaments: [{}] };

        await avvia();

        expect(el.textContent).toBe('referee:1');
    });

    it('admin sul calendario arbitro: errore con il tipo', async () => {
        const el = contenitore('referee-calendar-root');
        window.refereeCalendarData = { userType: 'admin', tournaments: [] };

        await avvia();

        expect(el.textContent).toContain('Dati calendario non validi per tipo "referee"');
        expect(el.textContent).toContain('Calendario Arbitro');
    });

    it('userType mancante: errore', async () => {
        const el = contenitore('referee-calendar-root');
        window.refereeCalendarData = { tournaments: [] };

        await avvia();

        expect(el.textContent).toContain('Dati calendario non validi');
    });
});

describe('app.js — pagine senza calendario', () => {
    it('senza contenitori non monta niente e non va in errore', async () => {
        document.body.innerHTML = '<p id="altro">pagina</p>';

        await avvia();

        expect(document.body.innerHTML).toBe('<p id="altro">pagina</p>');
    });

    it('con entrambi i contenitori monta tutti e due', async () => {
        const a = contenitore('admin-calendar-root');
        const r = contenitore('referee-calendar-root');
        window.adminCalendarData = { userType: 'admin', tournaments: [] };
        window.refereeCalendarData = { userType: 'referee', tournaments: [{}, {}, {}] };

        await avvia();

        expect(a.textContent).toBe('admin:0');
        expect(r.textContent).toBe('referee:3');
    });
});
