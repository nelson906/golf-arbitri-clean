@component('mail::message')
@php
    $zoneId = $tournament->club->zone->id ?? 1;
    $szrEmail = "szr{$zoneId}@federgolf.it";
@endphp

# Federazione Italiana Golf
###  Sezione Zonale Regole {{ $zoneId }}
<div style="text-align: center; margin-bottom: 30px;">
    <div style="background-color: #0b74f5da; color: white; padding: 5px; margin-bottom: 10px;">
    <h2 style="font-family: Sylfaen, serif; font-style: italic; color: #1a202c; margin: 0;">
        Federazione Italiana Golf
    </h2>
    </div>
    <p style="font-size: 14px; color: #4a5568; margin: 5px 0;">
        Sezione Zonale Regole
    </p>
</div>

---
**Spett.le {{ $recipientName ?? $tournament->club->name }},**

{!! nl2br(e($messageContent)) !!}

@if($assignments->count() > 0)
## Comitato di Gara assegnato

Con la presente si comunica che per la gara in oggetto hanno dato la propria disponibilità a far parte del Comitato di gara, con il possibile ruolo a fianco indicato, gli arbitri di seguito riportati:

@component('mail::table')
| Ruolo | Arbitro |
|:------|:--------|
@foreach($assignments as $assignment)
| {{ $assignment->role }} | {{ $assignment->referee->user->name }} |
@endforeach
@endcomponent
@endif

## Indirizzi per convocazione

Il Club interessato è pertanto invitato ad inviare tramite e-mail la necessaria convocazione del Comitato di Gara nonché, per conoscenza, ai seguenti indirizzi:

- **Sezione Zonale Regole:** {{ $szrEmail }}
- **Ufficio Campionati:** campionati@federgolf.it

---

Restando a disposizione per ogni chiarimento in merito, porgiamo i più cordiali saluti.

**Sezione Zonale Regole {{ $zoneId }}**

@endcomponent
