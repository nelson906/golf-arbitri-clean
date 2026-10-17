import React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';

vi.mock('@fullcalendar/react', () => import('../../test-support/FullCalendarStub.jsx'));

import { lastProps } from '../../test-support/FullCalendarStub.jsx';
import { isoDaysFromNow } from '../../test-support/dates.js';
import RefereeCalendar from './RefereeCalendar.jsx';

// Torneo futuro (fra 30 giorni, 3 giorni di gara), scadenza fra 10 giorni.
// `end` e' esclusivo come in FullCalendar: giorno dopo l'ultimo.
function torneo(id, extendedProps = {}, extra = {}) {
    return {
        id,
        title: `Torneo ${id}`,
        start: isoDaysFromNow(30),
        end: isoDaysFromNow(33),
        extendedProps: {
            club: `Circolo ${id}`,
            zone: 'SZR3',
            category: 'Cat. A',
            deadline: '10 giorni',
            days_until_deadline: 10,
            personal_status: 'can_apply',
            is_available: false,
            is_assigned: false,
            ...extendedProps,
        },
        ...extra,
    };
}

function dati(tournaments) {
    return { userType: 'referee', tournaments };
}

function apri(t) {
    render(<RefereeCalendar calendarData={dati([t])} />);
    fireEvent.click(screen.getByText(`apri ${t.title}`));
    return within(screen.getByRole('dialog'));
}

function dataIt(iso) {
    return new Intl.DateTimeFormat('it-IT', { year: 'numeric', month: 'long', day: 'numeric' })
        .format(new Date(iso));
}

let location;

beforeEach(() => {
    location = { href: '/user/availability/calendar', reload: vi.fn() };
    vi.stubGlobal('location', location);
    vi.spyOn(window, 'alert').mockImplementation(() => {});
});

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.head.querySelectorAll('meta[name="csrf-token"]').forEach((m) => m.remove());
});

describe('RefereeCalendar — dati mancanti', () => {
    it('senza tornei mostra il messaggio e nessun calendario', () => {
        render(<RefereeCalendar calendarData={{ userType: 'referee' }} />);

        expect(screen.getByText('Nessun torneo disponibile')).toBeTruthy();
        expect(screen.queryByTestId('fullcalendar')).toBeNull();
    });

    it('senza calendarData non va in errore', () => {
        render(<RefereeCalendar calendarData={null} />);

        expect(screen.getByText('Nessun torneo disponibile')).toBeTruthy();
    });

    it('lista vuota: calendario vuoto e "0 tornei"', () => {
        render(<RefereeCalendar calendarData={dati([])} />);

        expect(screen.getByTestId('fullcalendar')).toBeTruthy();
        expect(screen.getByText('0 tornei nella tua zona')).toBeTruthy();
    });
});

describe('RefereeCalendar — calendario e legenda', () => {
    const tornei = [
        torneo(1, { is_assigned: true, personal_status: 'assigned' }),
        torneo(2, { is_available: true, personal_status: 'available' }),
        torneo(3, { is_available: true, personal_status: 'available' }),
        torneo(4),
    ];

    it('passa i tornei a FullCalendar con vista mese e lingua italiana', () => {
        render(<RefereeCalendar calendarData={dati(tornei)} />);

        expect(lastProps.current.events).toEqual(tornei);
        expect(lastProps.current.initialView).toBe('dayGridMonth');
        expect(lastProps.current.locale.code).toBe('it');
    });

    it('conta i tornei della zona', () => {
        render(<RefereeCalendar calendarData={dati(tornei)} />);

        expect(screen.getByText('4 tornei nella tua zona')).toBeTruthy();
    });

    it('la legenda e\' chiusa e si apre e chiude col pulsante', () => {
        render(<RefereeCalendar calendarData={dati(tornei)} />);

        expect(screen.queryByText('Assegnazioni')).toBeNull();

        fireEvent.click(screen.getByText('Legenda e Statistiche'));
        expect(screen.getByText('Assegnazioni')).toBeTruthy();

        fireEvent.click(screen.getByText('Legenda e Statistiche'));
        expect(screen.queryByText('Assegnazioni')).toBeNull();
    });

    it('la legenda conta assegnazioni, disponibilita\' e tornei', () => {
        render(<RefereeCalendar calendarData={dati(tornei)} />);
        fireEvent.click(screen.getByText('Legenda e Statistiche'));

        expect(screen.getByText('Assegnazioni').previousSibling.textContent).toBe('1');
        expect(screen.getByText('Disponibilità').previousSibling.textContent).toBe('2');
        expect(screen.getByText('Tornei Zona').previousSibling.textContent).toBe('4');
    });

    it('il tooltip ha titolo, circolo e stato personale', () => {
        render(<RefereeCalendar calendarData={dati(tornei)} />);

        expect(screen.getByTestId('event-1').title).toBe('Torneo 1 - Circolo 1 (assigned)');
        expect(screen.getByTestId('event-2').title).toBe('Torneo 2 - Circolo 2 (available)');
    });

    it('il tooltip senza stato personale dice can_apply', () => {
        render(<RefereeCalendar calendarData={dati([torneo(5, { personal_status: undefined })])} />);

        expect(screen.getByTestId('event-5').title).toBe('Torneo 5 - Circolo 5 (can_apply)');
    });
});

describe('RefereeCalendar — finestra di dettaglio', () => {
    it('mostra circolo, categoria, zona e scadenza', () => {
        const d = apri(torneo(1));

        expect(d.getByRole('heading', { name: 'Torneo 1' })).toBeTruthy();
        expect(d.getByText('Club:').parentElement.textContent).toBe('Club: Circolo 1');
        expect(d.getByText('Categoria:').parentElement.textContent).toBe('Categoria: Cat. A');
        expect(d.getByText('Zona:').parentElement.textContent).toBe('Zona: SZR3');
        expect(d.getByText('Scadenza:').parentElement.textContent).toBe('Scadenza: 10 giorni');
    });

    it('senza scadenza mostra N/A', () => {
        const d = apri(torneo(1, { deadline: null }));

        expect(d.getByText('Scadenza:').parentElement.textContent).toBe('Scadenza: N/A');
    });

    it.each([
        ['assigned', 'Assegnato'],
        ['available', 'Disponibile'],
        ['can_apply', 'Posso candidarmi'],
        ['closed', 'Chiuso'],
    ])('stato %s: etichetta "%s"', (status, testo) => {
        const d = apri(torneo(1, { personal_status: status }));
        const stato = d.getByText('Il mio stato:').parentElement;

        expect(within(stato).getByText(testo)).toBeTruthy();
    });

    it('stato sconosciuto: mostra il valore com\'e\', col colore di "chiuso"', () => {
        const d = apri(torneo(1, { personal_status: 'boh' }));
        const badge = within(d.getByText('Il mio stato:').parentElement).getByText('boh');

        expect(badge.className).toContain('bg-gray-100');
    });

    it('date: dal primo all\'ultimo giorno (end di FullCalendar e\' il giorno dopo)', () => {
        const t = torneo(1);
        const d = apri(t);

        expect(d.getByText('Date:').nextSibling.textContent)
            .toBe(`${dataIt(isoDaysFromNow(30))} - ${dataIt(isoDaysFromNow(32))}`);
    });

    it('date: torneo di un giorno senza end mostra solo l\'inizio', () => {
        const d = apri(torneo(1, {}, { end: null }));

        expect(d.getByText('Date:').nextSibling.textContent).toBe(dataIt(isoDaysFromNow(30)));
    });

    it('tempo rimanente: giorni alla scadenza', () => {
        const d = apri(torneo(1, { days_until_deadline: 4 }));

        expect(d.getByText('4 giorni').className).toContain('text-gray-600');
    });

    it('senza giorni alla scadenza non mostra "Tempo rimanente"', () => {
        const d = apri(torneo(1, { days_until_deadline: null }));

        expect(d.queryByText('Tempo rimanente:')).toBeNull();
    });

    it('Chiudi e clic sullo sfondo chiudono la finestra', () => {
        let d = apri(torneo(1));
        fireEvent.click(d.getByText('Chiudi'));
        expect(screen.queryByRole('dialog')).toBeNull();

        fireEvent.click(screen.getByText('apri Torneo 1'));
        fireEvent.click(screen.getByRole('dialog').querySelector('.bg-gray-500'));
        expect(screen.queryByRole('dialog')).toBeNull();
    });
});

describe('RefereeCalendar — pulsante disponibilita\'', () => {
    it('assegnato: pulsante "Assegnato" disabilitato, anche a torneo passato', () => {
        const d = apri(torneo(1, { personal_status: 'assigned' }, { start: isoDaysFromNow(-3), end: null }));
        const btn = d.getByRole('button', { name: 'Assegnato' });

        expect(btn.disabled).toBe(true);
        expect(d.queryByText('Data Passata')).toBeNull();
    });

    it('torneo passato: avviso e pulsante "Data Passata" disabilitato', () => {
        const d = apri(torneo(1, { days_until_deadline: 5 }, { start: isoDaysFromNow(-1), end: null }));

        expect(d.getByText(/Torneo passato:/)).toBeTruthy();
        expect(d.getByRole('button', { name: 'Data Passata' }).disabled).toBe(true);
    });

    it('torneo oggi: non e\' passato', () => {
        const d = apri(torneo(1, {}, { start: isoDaysFromNow(0), end: null }));

        expect(d.queryByText(/Torneo passato:/)).toBeNull();
        expect(d.getByRole('button', { name: 'Aggiungi Disponibilità' })).toBeTruthy();
    });

    it('scadenza passata: pulsante "Scadenza passata" disabilitato e testo rosso', () => {
        const d = apri(torneo(1, { days_until_deadline: -1, is_available: true }));
        const btn = d.getByRole('button', { name: 'Scadenza passata' });

        expect(btn.disabled).toBe(true);
        expect(d.queryByText('Rimuovi Disponibilità')).toBeNull();
        expect(d.getByText('Tempo rimanente:').nextSibling.className).toContain('text-red-600');
    });

    it('giorno di scadenza (0 giorni): si puo\' ancora dichiarare', () => {
        const d = apri(torneo(1, { days_until_deadline: 0 }));

        expect(d.getByRole('button', { name: 'Aggiungi Disponibilità' }).disabled).toBe(false);
    });

    it('senza giorni alla scadenza: si puo\' dichiarare', () => {
        const d = apri(torneo(1, { days_until_deadline: null }));

        expect(d.getByRole('button', { name: 'Aggiungi Disponibilità' })).toBeTruthy();
    });

    it('gia\' disponibile: pulsante rosso "Rimuovi Disponibilità"', () => {
        const d = apri(torneo(1, { is_available: true, personal_status: 'available' }));

        expect(d.getByRole('button', { name: 'Rimuovi Disponibilità' }).className).toContain('bg-red-600');
    });
});

describe('RefereeCalendar — invio disponibilita\'', () => {
    function rispostaJson(body) {
        return vi.fn().mockResolvedValue({ json: () => Promise.resolve(body) });
    }

    function csrf(token) {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = token;
        document.head.appendChild(meta);
    }

    it('Aggiungi: POST a /user/availability/store con available 1 e token', async () => {
        csrf('tok-abc');
        const fetch = rispostaJson({ success: true, message: 'Disponibilità dichiarata con successo.' });
        vi.stubGlobal('fetch', fetch);
        const d = apri(torneo(7));

        fireEvent.click(d.getByRole('button', { name: 'Aggiungi Disponibilità' }));

        expect(screen.queryByRole('dialog')).toBeNull();
        await waitFor(() => expect(location.reload).toHaveBeenCalledOnce());

        const [url, opts] = fetch.mock.calls[0];
        expect(url).toBe('/user/availability/store');
        expect(opts.method).toBe('POST');
        expect(opts.headers['X-CSRF-TOKEN']).toBe('tok-abc');
        expect(opts.headers.Accept).toBe('application/json');
        expect(opts.headers['X-Requested-With']).toBe('XMLHttpRequest');
        expect(JSON.parse(opts.body)).toEqual({ tournament_id: '7', available: 1, notes: '' });
        expect(window.alert).toHaveBeenCalledWith('Disponibilità dichiarata con successo.');
    });

    it('Rimuovi: invia available 0', async () => {
        const fetch = rispostaJson({ success: true, message: 'Disponibilità rimossa.' });
        vi.stubGlobal('fetch', fetch);
        const d = apri(torneo(8, { is_available: true }));

        fireEvent.click(d.getByRole('button', { name: 'Rimuovi Disponibilità' }));

        await waitFor(() => expect(location.reload).toHaveBeenCalledOnce());
        expect(JSON.parse(fetch.mock.calls[0][1].body).available).toBe(0);
    });

    it('senza meta CSRF manda token vuoto', async () => {
        const fetch = rispostaJson({ success: true, message: 'ok' });
        vi.stubGlobal('fetch', fetch);
        const d = apri(torneo(1));

        fireEvent.click(d.getByRole('button', { name: 'Aggiungi Disponibilità' }));

        await waitFor(() => expect(fetch).toHaveBeenCalledOnce());
        expect(fetch.mock.calls[0][1].headers['X-CSRF-TOKEN']).toBe('');
    });

    it('rifiuto del server: mostra l\'errore e non ricarica', async () => {
        vi.stubGlobal('fetch', rispostaJson({
            success: false,
            error: 'Il termine per dichiarare disponibilità per questo torneo è scaduto.',
        }));
        const d = apri(torneo(1));

        fireEvent.click(d.getByRole('button', { name: 'Aggiungi Disponibilità' }));

        await waitFor(() => expect(window.alert)
            .toHaveBeenCalledWith('Il termine per dichiarare disponibilità per questo torneo è scaduto.'));
        expect(location.reload).not.toHaveBeenCalled();
    });

    it('rifiuto senza messaggio: errore generico', async () => {
        vi.stubGlobal('fetch', rispostaJson({ success: false }));
        const d = apri(torneo(1));

        fireEvent.click(d.getByRole('button', { name: 'Aggiungi Disponibilità' }));

        await waitFor(() => expect(window.alert).toHaveBeenCalledWith('Errore durante il salvataggio'));
        expect(location.reload).not.toHaveBeenCalled();
    });

    it('rete giu\': "Errore di connessione" e nessun ricaricamento', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
        const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
        const d = apri(torneo(1));

        fireEvent.click(d.getByRole('button', { name: 'Aggiungi Disponibilità' }));

        await waitFor(() => expect(window.alert).toHaveBeenCalledWith('Errore di connessione'));
        expect(consoleError).toHaveBeenCalled();
        expect(location.reload).not.toHaveBeenCalled();
    });
});
