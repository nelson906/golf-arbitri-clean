<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assegnazione Arbitri - {{ $tournament_name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #2c5234;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .logo {
            max-width: 200px;
            margin-bottom: 10px;
        }

        .title {
            color: #2c5234;
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        .subtitle {
            color: #666;
            font-size: 16px;
            margin: 5px 0 0 0;
        }

        .content {
            margin: 30px 0;
        }

        .greeting {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .tournament-info {
            background-color: #f8f9fa;
            border-left: 4px solid #2c5234;
            padding: 15px;
            margin: 20px 0;
        }

        .referees-list {
            background-color: #fff;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }

        .referee-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .referee-item:last-child {
            border-bottom: none;
        }

        .referee-name {
            font-weight: bold;
            color: #2c5234;
        }

        .referee-role {
            color: #666;
            font-style: italic;
        }

        .referee-email {
            color: #007bff;
            font-size: 14px;
        }

        .attachments-info {
            background-color: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px 0;
        }

        .attachments-title {
            font-weight: bold;
            color: #004085;
            margin-bottom: 10px;
        }

        .attachment-item {
            margin: 5px 0;
            padding-left: 15px;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            text-align: center;
            color: #666;
            font-size: 14px;
        }

        .signature {
            margin-top: 30px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 class="title">FEDERAZIONE ITALIANA GOLF</h1>
        <p class="subtitle">Sistema Gestione Arbitri e Tornei</p>
    </div>

    <div class="content">
        <div class="greeting">
            Gentile {{ $recipient_name }},
        </div>

        <p>Vi comunichiamo gli arbitri assegnati per il torneo <strong>{{ $tournament_name }}</strong>:</p>

        <div class="tournament-info">
            <strong>📅 Date:</strong> {{ $tournament_dates }}<br>
            <strong>🏌️ Circolo:</strong> {{ $club_name }}
        </div>

        @if (!empty($referees) && count($referees) > 0)
            <div class="referees-list">
                <h3 style="margin-top: 0; color: #2c5234;">⚖️ Comitato di Gara:</h3>

                @php
                    // Ordina per gerarchia
                    $roleOrder = ['Direttore di Torneo' => 1, 'Arbitro' => 2, 'Osservatore' => 3];
                    $sortedReferees = collect($referees)->sort(function ($a, $b) use ($roleOrder) {
                        $orderA = $roleOrder[$a['role']] ?? 999;
                        $orderB = $roleOrder[$b['role']] ?? 999;
                        return $orderA <=> $orderB;
                    });
                @endphp

                @foreach ($sortedReferees as $referee)
                    <div class="referee-item">
                        <div>
                            <div class="referee-name"
                                style="{{ $referee['role'] === 'Direttore di Torneo' ? 'color: #1a3d26; font-size: 18px;' : '' }}">
                                {{ $referee['name'] }}
                            </div>
                            <div class="referee-role">{{ $referee['role'] }}</div>
                        </div>
                        @if (!empty($referee['email']))
                            <div class="referee-email">{{ $referee['email'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <p>Il Comitato e gli Osservatori sono tenuti a presenziare dalle ore 9.00 del giorno precedente l'inizio della
            manifestazione sino al termine della stessa o secondo le decisioni che verranno direttamente comunicate dal
            Direttore di Torneo.</p>

        <p>Si ricorda che le eventuali spese di viaggio, vitto e alloggio, saranno rimborsate così come previsto dalla
            Normativa Tecnica in vigore. Il rimborso sarà effettuato sulla base della nota spese emessa dal singolo
            soggetto. Tutte le spese sono rimborsate nei limiti previsti dalla FIG e indicati nelle "Linee guida
            trasferte e rimborsi spese" annualmente pubblicate.</p>

        @if (isset($zone_email) || isset($club_email))
            <p>Si prega di confermare la propria presenza sia alla Sezione Zonale Regole di competenza
                @if (isset($zone_email))
                    (<a href="mailto:{{ $zone_email }}">{{ $zone_email }}</a>)
                @endif
                @if (isset($club_email))
                    sia al Circolo Organizzatore (<a href="mailto:{{ $club_email }}">{{ $club_email }}</a>)
                @endif
                .
            </p>
        @endif

        {{-- Info allegati se presenti --}}
        @if (isset($attachments_info))
            <div class="attachments-info">
                <div class="attachments-title">📎 Documenti allegati:</div>
                @if (is_array($attachments_info))
                    @foreach ($attachments_info as $attachment)
                        <div class="attachment-item">• {{ $attachment }}</div>
                    @endforeach
                @else
                    <div class="attachment-item">{{ $attachments_info }}</div>
                @endif
            </div>
        @endif
    </div>

    <div class="footer">
        <div class="signature">
            Cordiali saluti<br>
            <strong>Federazione Italiana Golf</strong><br>
            Sistema Gestione Arbitri
        </div>

        <hr style="margin: 20px 0; border: none; border-top: 1px solid #e9ecef;">

        <p style="font-size: 12px; color: #999;">
            Questa email è stata generata automaticamente dal Sistema di Gestione Tornei FIG.<br>
            Per informazioni: <a href="mailto:arbitri@federgolf.it">arbitri@federgolf.it</a>
        </p>
    </div>
</body>

</html>
