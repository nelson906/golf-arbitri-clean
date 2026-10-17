import React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';

vi.mock('@fullcalendar/react', () => import('../../test-support/FullCalendarStub.jsx'));

import { lastProps } from '../../test-support/FullCalendarStub.jsx';
import AdminCalendar from './AdminCalendar.jsx';

function torneo(id, extendedProps = {}, extra = {}) {
    return {
        id,
        title: `Torneo ${id}`,
        start: '2030-05-10',
        end: '2030-05-12',
        extendedProps: {
            club: `Circolo ${id}`,
            zone: 'SZR1',
            tournament_type: 'Gara 36 buche',
            tournament_url: `/admin/tournaments/${id}`,
            ...extendedProps,
        },
        ...extra,
    };
}

function dati(overrides = {}) {
    return {
        userType: 'admin',
        tournaments: [torneo(1), torneo(2)],
        zones: [],
        ...overrides,
    };
}

let location;

beforeEach(() => {
    location = { href: '/admin/calendar', reload: vi.fn() };
    vi.stubGlobal('location', location);
});

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.querySelectorAll('form').forEach((f) => f.remove());
    document.head.querySelectorAll('meta[name="csrf-token"]').forEach((m) => m.remove());
});

describe('AdminCalendar — dati mancanti', () => {
    it('senza tornei mostra il messaggio e nessun calendario', () => {
        render(<AdminCalendar calendarData={{ userType: 'admin' }} />);

        expect(screen.getByText('Nessun dato disponibile')).toBeTruthy();
        expect(screen.queryByTestId('fullcalendar')).toBeNull();
    });

    it('senza calendarData non va in errore', () => {
        render(<AdminCalendar calendarData={undefined} />);

        expect(screen.getByText('Nessun dato disponibile')).toBeTruthy();
    });
});

describe('AdminCalendar — calendario', () => {
    it('passa i tornei a FullCalendar con vista mese e lingua italiana', () => {
        const data = dati();
        render(<AdminCalendar calendarData={data} />);

        expect(lastProps.current.events).toEqual(data.tournaments);
        expect(lastProps.current.initialView).toBe('dayGridMonth');
        expect(lastProps.current.locale.code).toBe('it');
        expect(lastProps.current.headerToolbar.right).toBe('dayGridMonth,listWeek');
    });

    it('conta i tornei visualizzati', () => {
        render(<AdminCalendar calendarData={dati()} />);

        expect(screen.getByText(/2 tornei visualizzati/)).toBeTruthy();
    });

    it('il tooltip dell\'evento ha titolo, circolo e tipo', () => {
        render(<AdminCalendar calendarData={dati()} />);

        expect(screen.getByTestId('event-1').title).toBe('Torneo 1 - Circolo 1 (Gara 36 buche)');
    });

    it('il riquadro evento mostra circolo e tipo torneo', () => {
        render(<AdminCalendar calendarData={dati()} />);
        const ev = within(screen.getByTestId('event-1'));

        expect(ev.getByText('Circolo 1')).toBeTruthy();
        expect(ev.getByText('Gara 36 buche')).toBeTruthy();
    });

    it('senza circolo mostra "Club N/A"', () => {
        render(<AdminCalendar calendarData={dati({ tournaments: [torneo(1, { club: null })] })} />);

        expect(within(screen.getByTestId('event-1')).getByText('Club N/A')).toBeTruthy();
    });

    it('nei giorni dopo il primo non ripete tipo torneo e pulsanti', () => {
        const t = torneo(1, {}, { stubIsStart: false });
        render(<AdminCalendar calendarData={dati({ tournaments: [t] })} />);
        const ev = within(screen.getByTestId('event-1'));

        expect(ev.queryByText('Gara 36 buche')).toBeNull();
        expect(ev.queryByTitle('Modifica torneo')).toBeNull();
        expect(ev.queryByTitle('Elimina torneo')).toBeNull();
    });
});

describe('AdminCalendar — filtro zona', () => {
    const zone = [{ id: 1, name: 'SZR1' }, { id: 2, name: 'SZR2' }];
    const tornei = [
        torneo(1, { zone: 'SZR1' }),
        torneo(2, { zone: 'SZR2' }),
        torneo(3, { zone: 'SZR2' }),
    ];

    it('l\'admin di zona non vede il filtro', () => {
        render(<AdminCalendar calendarData={dati({ userType: 'admin', zones: zone, tournaments: tornei })} />);

        expect(screen.queryByText('Filtra per zona:')).toBeNull();
    });

    it.each(['national_admin', 'super_admin'])('%s vede il filtro con tutte le zone', (userType) => {
        render(<AdminCalendar calendarData={dati({ userType, zones: zone, tournaments: tornei })} />);
        const select = screen.getByRole('combobox');

        expect(screen.getByText('Filtra per zona:')).toBeTruthy();
        expect(within(select).getAllByRole('option').map((o) => o.textContent))
            .toEqual(['Tutte le zone', 'SZR1', 'SZR2']);
    });

    it('CRC senza zone non vede il filtro', () => {
        render(<AdminCalendar calendarData={dati({ userType: 'national_admin', zones: [], tournaments: tornei })} />);

        expect(screen.queryByRole('combobox')).toBeNull();
    });

    it('scegliendo una zona restano solo i suoi tornei, e il filtro si toglie', () => {
        render(<AdminCalendar calendarData={dati({ userType: 'national_admin', zones: zone, tournaments: tornei })} />);

        fireEvent.change(screen.getByRole('combobox'), { target: { value: 'SZR2' } });

        expect(lastProps.current.events.map((e) => e.id)).toEqual([2, 3]);
        expect(screen.getByText(/2 tornei visualizzati/)).toBeTruthy();

        fireEvent.click(screen.getByText('Rimuovi filtro zona'));

        expect(lastProps.current.events.map((e) => e.id)).toEqual([1, 2, 3]);
        expect(screen.getByText(/3 tornei visualizzati/)).toBeTruthy();
        expect(screen.queryByText('Rimuovi filtro zona')).toBeNull();
    });
});

describe('AdminCalendar — modifica ed eliminazione dal riquadro', () => {
    it('i pulsanti ci sono se can_edit non e\' false', () => {
        render(<AdminCalendar calendarData={dati()} />);
        const ev = within(screen.getByTestId('event-1'));

        expect(ev.getByTitle('Modifica torneo')).toBeTruthy();
        expect(ev.getByTitle('Elimina torneo')).toBeTruthy();
    });

    it('sui tornei con can_edit false (nazionali per la zona) i pulsanti non ci sono', () => {
        render(<AdminCalendar calendarData={dati({ tournaments: [torneo(1, { can_edit: false })] })} />);
        const ev = within(screen.getByTestId('event-1'));

        expect(ev.queryByTitle('Modifica torneo')).toBeNull();
        expect(ev.queryByTitle('Elimina torneo')).toBeNull();
    });

    it('Modifica porta alla pagina di modifica del torneo senza aprire il dettaglio', () => {
        render(<AdminCalendar calendarData={dati()} />);

        fireEvent.click(within(screen.getByTestId('event-1')).getByTitle('Modifica torneo'));

        expect(location.href).toBe('/admin/tournaments/1/edit');
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('Elimina, se annullato, non invia niente', () => {
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        const submit = vi.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {});
        render(<AdminCalendar calendarData={dati()} />);

        fireEvent.click(within(screen.getByTestId('event-1')).getByTitle('Elimina torneo'));

        expect(window.confirm).toHaveBeenCalledOnce();
        expect(submit).not.toHaveBeenCalled();
        expect(document.querySelector('form')).toBeNull();
    });

    it('Elimina, se confermato, invia DELETE con il token CSRF', () => {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = 'tok-123';
        document.head.appendChild(meta);
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        const submit = vi.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {});
        render(<AdminCalendar calendarData={dati()} />);

        fireEvent.click(within(screen.getByTestId('event-2')).getByTitle('Elimina torneo'));

        const form = document.querySelector('form');
        expect(submit).toHaveBeenCalledOnce();
        expect(form.method.toUpperCase()).toBe('POST');
        expect(form.getAttribute('action')).toBe('/admin/tournaments/2');
        expect(form.querySelector('input[name="_token"]').value).toBe('tok-123');
        expect(form.querySelector('input[name="_method"]').value).toBe('DELETE');
    });

    it('Elimina senza meta CSRF invia comunque, senza _token', () => {
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        vi.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {});
        render(<AdminCalendar calendarData={dati()} />);

        fireEvent.click(within(screen.getByTestId('event-1')).getByTitle('Elimina torneo'));

        const form = document.querySelector('form');
        expect(form.querySelector('input[name="_token"]')).toBeNull();
        expect(form.querySelector('input[name="_method"]').value).toBe('DELETE');
    });
});

describe('AdminCalendar — finestra di dettaglio', () => {
    function apri(t) {
        render(<AdminCalendar calendarData={dati({ tournaments: [t] })} />);
        fireEvent.click(screen.getByText(`apri ${t.title}`));
        return within(screen.getByRole('dialog'));
    }

    it('mostra circolo, zona, tipo e contatori', () => {
        const d = apri(torneo(1, {
            availabilities_count: 7,
            assignments_count: 3,
            required_referees: 4,
            deadline: '01/05/2030',
            days_until_deadline: 5,
        }));

        expect(d.getByRole('heading', { name: 'Torneo 1' })).toBeTruthy();
        expect(d.getByText('Club:').parentElement.textContent).toBe('Club: Circolo 1');
        expect(d.getByText('Zona:').parentElement.textContent).toBe('Zona: SZR1');
        expect(d.getByText('Tipo:').parentElement.textContent).toBe('Tipo: Gara 36 buche');
        expect(d.getByText('Disponibilità').previousSibling.textContent).toBe('7');
        expect(d.getByText('Assegnati').previousSibling.textContent).toBe('3');
        expect(d.getByText('Richiesti').previousSibling.textContent).toBe('4');
        expect(d.getByText(/Scadenza disponibilità:/).parentElement.textContent)
            .toContain('01/05/2030');
        expect(d.getByText('(5 giorni)')).toBeTruthy();
    });

    it('senza contatori e scadenza mostra 0 e N/A', () => {
        const d = apri(torneo(1));

        expect(d.getByText('Disponibilità').previousSibling.textContent).toBe('0');
        expect(d.getByText('Assegnati').previousSibling.textContent).toBe('0');
        expect(d.getByText('Richiesti').previousSibling.textContent).toBe('0');
        expect(d.getByText(/Scadenza disponibilità:/).parentElement.textContent).toContain('N/A');
        expect(d.queryByText(/giorni\)/)).toBeNull();
    });

    it('scadenza passata: "Scaduta!"', () => {
        const d = apri(torneo(1, { days_until_deadline: -1 }));

        expect(d.getByText('(Scaduta!)')).toBeTruthy();
    });

    it('scadenza oggi: "0 giorni", non scaduta', () => {
        const d = apri(torneo(1, { days_until_deadline: 0 }));

        expect(d.getByText('(0 giorni)')).toBeTruthy();
    });

    it('Visualizza Dettagli punta alla pagina del torneo', () => {
        const d = apri(torneo(1));

        expect(d.getByText('Visualizza Dettagli').getAttribute('href')).toBe('/admin/tournaments/1');
    });

    it('Modifica Torneo porta alla modifica', () => {
        const d = apri(torneo(9));

        fireEvent.click(d.getByText('Modifica Torneo'));

        expect(location.href).toBe('/admin/tournaments/9/edit');
    });

    it('con can_edit false non c\'e\' Modifica Torneo ma c\'e\' Visualizza Dettagli', () => {
        const d = apri(torneo(1, { can_edit: false }));

        expect(d.queryByText('Modifica Torneo')).toBeNull();
        expect(d.getByText('Visualizza Dettagli')).toBeTruthy();
    });

    it('Chiudi chiude la finestra', () => {
        const d = apri(torneo(1));

        fireEvent.click(d.getByText('Chiudi'));

        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('clic sullo sfondo chiude la finestra', () => {
        apri(torneo(1));

        fireEvent.click(screen.getByRole('dialog').querySelector('.bg-gray-500'));

        expect(screen.queryByRole('dialog')).toBeNull();
    });
});
