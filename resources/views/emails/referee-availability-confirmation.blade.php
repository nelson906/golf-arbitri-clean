<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Conferma aggiornamento disponibilità</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2c3e50;">Conferma aggiornamento disponibilità</h1>

        <p>Gentile {{ $referee_name }},</p>

        <p>La tua disponibilità è stata aggiornata con successo.</p>

        <h2 style="color: #34495e;">Riepilogo modifiche:</h2>

        @if($added_count > 0)
            <h3 style="color: #27ae60;">✅ Nuove disponibilità aggiunte ({{ $added_count }}):</h3>
            <ul>
            @foreach($added_tournaments as $tournament)
                <li>
                    <strong>{{ $tournament->name }}</strong><br>
                    Date: {{ $tournament->start_date->format('d/m/Y') }} - {{ $tournament->end_date->format('d/m/Y') }}<br>
                    Circolo: {{ $tournament->club->name }}
                </li>
            @endforeach
            </ul>
        @endif

        @if($removed_count > 0)
            <h3 style="color: #e74c3c;">❌ Disponibilità rimosse ({{ $removed_count }}):</h3>
            <ul>
            @foreach($removed_tournaments as $tournament)
                <li>{{ $tournament->name }} ({{ $tournament->start_date->format('d/m/Y') }})</li>
            @endforeach
            </ul>
        @endif

        <p><strong>Totale tornei con disponibilità: {{ $total_availabilities }}</strong></p>

        <p>Puoi sempre modificare le tue disponibilità accedendo al sistema.</p>

        <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">

        <p style="color: #666; font-size: 14px;">
            Cordiali saluti,<br>
            {{ config('app.name') }}
        </p>
    </div>
</body>
</html>
