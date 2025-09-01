Gentile {{ $tournament->club->name }},

Vi comunichiamo gli arbitri assegnati per il torneo:
**{{ $tournament->name }}**
Date: {{ $tournament->start_date->format('d/m/Y') }} - {{ $tournament->end_date->format('d/m/Y') }}

Comitato di Gara:
@foreach(['Direttore di Torneo', 'Arbitro', 'Osservatore'] as $role)
    @php
        $referees = $sortedAssignments->where('role', $role);
    @endphp

    @if($referees->count() > 0)
        <p><strong>{{ $role }}:</strong></p>
        <ul>
            @foreach($referees as $assignment)
                <li>{{ $assignment->user->name }}</li>
            @endforeach
        </ul>
    @endif
@endforeach

In allegato trovate il facsimile della convocazione da inviare su carta intestata.

Cordiali saluti,
Sezione Zonale Regole {{ $tournament->zone->name }}
