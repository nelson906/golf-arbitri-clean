Gentile {{ $assignment->user->name }},

È convocato come {{ $assignment->role }} per:

**{{ $tournament->name }}**
Date: {{ $tournament->start_date->format('d/m/Y') }} - {{ $tournament->end_date->format('d/m/Y') }}
Circolo: {{ $tournament->club->name }}

Si prega di confermare la presenza a:
- szr{{ $tournament->zone_id }}@federgolf.it
- {{ $tournament->club->email }}

Cordiali saluti,
Sezione Zonale Regole {{ $tournament->zone->name }}
