@extends('layouts.pdf')

@section('title', 'Convocazione Arbitri - ' . $tournament->name)

@section('content')
    {{-- Header corretto --}}
    <header class="header">
        @if($zone_logo)
            <img src="data:image/png;base64,{{ $zone_logo }}" class="logo" alt="Logo {{ $zone_name }}" style="max-height: 120px;">
        @endif
        <div class="zone-title">{{ $zone_name }}</div>
    </header>

    <h1 class="document-title">Convocazione Arbitri</h1>

    {{-- Informazioni torneo --}}
    <section class="info-section">
        <div class="info-item">
            <span class="info-label">Torneo:</span>
            <strong>{{ $tournament_name }}</strong>
        </div>
        <div class="info-item">
            <span class="info-label">Date:</span>
            {{ $tournament_dates }}
        </div>
        <div class="info-item">
            <span class="info-label">Circolo:</span>
            {{ $club_name }}
        </div>
    </section>

    {{-- Testo introduttivo --}}
    <p>Con la presente si comunica che per la gara in oggetto hanno dato la propria disponibilità a far parte del Comitato di gara, con il possibile ruolo a fianco indicato, gli arbitri di seguito riportati:</p>

    {{-- Lista arbitri (TORNA ALLA VERSIONE ORIGINALE) --}}
    <section class="referees-section">
        <h3>ARBITRI CONVOCATI:</h3>

        @foreach(['Direttore di Torneo', 'Arbitro', 'Osservatore'] as $role)
            @php $roleReferees = $referees->where('role', $role); @endphp

            @if($roleReferees->isNotEmpty())
                <div class="role-group">
                    <div class="role-title">{{ $role }}</div>
                    @foreach($roleReferees as $assignment)
                        <div class="referee-item">
                            • {{ $assignment->user->name }}
                            @if($assignment->user->referee_code)
                                <small>({{ $assignment->user->referee_code }})</small>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach
    </section>

    {{-- Testo contatti --}}
    <p>Si prega cortesemente di inviare tramite e-mail la necessaria convocazione del Comitato di Gara nonché, per conoscenza, ai seguenti indirizzi:</p>
    <p>Sezione Zonale Regole 6: szr6@federgolf.it</p>
    <p>Ufficio Campionati: campionati@federgolf.it</p>

    <footer class="footer">
        <p>{{ $zone_name }}</p>
        <p>Documento generato il {{ $current_date }}</p>
    </footer>
@endsection
