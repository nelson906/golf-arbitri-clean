<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Aggiornamento Disponibilità Arbitro</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2c3e50;">Aggiornamento Disponibilità Arbitro</h1>

        <table style="width: 100%; margin-bottom: 20px;">
            <tr>
                <td style="padding: 5px 0;"><strong>Arbitro:</strong></td>
                <td>{{ $referee_name }} ({{ $referee_code }})</td>
            </tr>
            <tr>
                <td style="padding: 5px 0;"><strong>Livello:</strong></td>
                <td>{{ $referee_level }}</td>
            </tr>
            <tr>
                <td style="padding: 5px 0;"><strong>Zona:</strong></td>
                <td>{{ $zone }}</td>
            </tr>
            <tr>
                <td style="padding: 5px 0;"><strong>Data aggiornamento:</strong></td>
                <td>{{ $updated_at }}</td>
            </tr>
        </table>

        @if($added_tournaments->count() > 0)
            <h2 style="color: #27ae60;">✅ NUOVE DISPONIBILITÀ:</h2>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <thead>
                    <tr style="background-color: #f5f5f5;">
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Torneo</th>
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Date</th>
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Circolo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($added_tournaments as $tournament)
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 8px;">{{ $tournament->name }}</td>
                        <td style="border: 1px solid #ddd; padding: 8px;">{{ $tournament->start_date->format('d/m') }}-{{ $tournament->end_date->format('d/m/Y') }}</td>
                        <td style="border: 1px solid #ddd; padding: 8px;">{{ $tournament->club->name }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if($removed_tournaments->count() > 0)
            <h2 style="color: #e74c3c;">❌ DISPONIBILITÀ RIMOSSE:</h2>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <thead>
                    <tr style="background-color: #f5f5f5;">
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Torneo</th>
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Date</th>
                        <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Circolo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($removed_tournaments as $tournament)
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 8px;">{{ $tournament->name }}</td>
                        <td style="border: 1px solid #ddd; padding: 8px;">{{ $tournament->start_date->format('d/m') }}-{{ $tournament->end_date->format('d/m/Y') }}</td>
                        <td style="border: 1px solid #ddd; padding: 8px;">{{ $tournament->club->name }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- RIMUOVI IL BOTTONE CHE CAUSA L'ERRORE --}}
        <div style="margin-top: 30px; text-align: center; color: #666;">
            <p>Accedi al sistema per visualizzare tutte le disponibilità</p>
        </div>
    </div>
</body>
</html>
