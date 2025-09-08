@component('mail::message')
# Notifica Assegnazione Arbitri

Gentile {{ $recipientName ?? 'Destinatario' }},

{!! nl2br(e($messageContent)) !!}

@if($assignments->count() > 0)
## Dettagli Assegnazione

**Torneo:** {{ $tournament->name }}
**Date:** {{ $tournament->start_date->format('d/m/Y') }}{{ $tournament->start_date->format('d/m/Y') != $tournament->end_date->format('d/m/Y') ? ' - ' . $tournament->end_date->format('d/m/Y') : '' }}
**Circolo:** {{ $tournament->club->name }}

### Arbitri Assegnati

@component('mail::table')
| Ruolo | Arbitro | Qualifica |
|:------|:--------|:----------|
@foreach($assignments as $assignment)
| {{ $assignment->role }} | {{ $assignment->referee->user->name }} | {{ $assignment->referee->qualification }} |
@endforeach
@endcomponent
@endif

@component('mail::button', ['url' => route('tournaments.show', $tournament)])
Visualizza Dettagli Torneo
@endcomponent

Cordiali saluti,
**Sistema Gestione Arbitri**

@endcomponent
