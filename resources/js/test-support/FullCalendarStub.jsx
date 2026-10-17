// Sostituto di @fullcalendar/react per i test dei calendari.
//
// Il vero FullCalendar in jsdom non ha misure (niente layout), quindi gli
// eventi non si posizionano in modo affidabile. Questo stub rende ogni
// evento in una riga e chiama gli stessi callback che userebbe FullCalendar
// (eventContent, eventDidMount, eventClick), con un oggetto che imita
// EventApi: id stringa, start/end come Date, extendedProps.
//
// Campo solo-test: `stubIsStart: false` simula un giorno successivo al
// primo di un torneo su piu' giorni.
import React, { useEffect, useRef } from 'react';

export const lastProps = { current: null };

function toEventApi(ev) {
    return {
        id: String(ev.id),
        title: ev.title,
        start: ev.start ? new Date(ev.start) : null,
        end: ev.end ? new Date(ev.end) : null,
        extendedProps: ev.extendedProps || {},
    };
}

function StubEvent({ source, eventContent, eventDidMount, eventClick }) {
    const ref = useRef(null);
    const api = toEventApi(source);

    useEffect(() => {
        if (eventDidMount) {
            eventDidMount({ event: api, el: ref.current });
        }
        // Solo al montaggio, come FullCalendar.
    }, []);

    return (
        <div data-testid={`event-${api.id}`} ref={ref}>
            {eventContent
                ? eventContent({ event: api, isStart: source.stubIsStart !== false })
                : api.title}
            <button type="button" onClick={() => eventClick && eventClick({ event: api })}>
                {`apri ${api.title}`}
            </button>
        </div>
    );
}

export default function FullCalendarStub(props) {
    lastProps.current = props;
    const events = props.events || [];

    return (
        <div data-testid="fullcalendar">
            {events.map((ev) => (
                <StubEvent
                    key={ev.id}
                    source={ev}
                    eventContent={props.eventContent}
                    eventDidMount={props.eventDidMount}
                    eventClick={props.eventClick}
                />
            ))}
        </div>
    );
}
