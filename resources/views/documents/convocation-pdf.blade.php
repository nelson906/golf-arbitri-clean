<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            max-height: 80px;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0;
        }

        .content {
            margin: 20px 0;
        }

        .referee-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .referee-table th,
        .referee-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .referee-table th {
            background-color: #f2f2f2;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }

        .director-row {
            background-color: #f3f4f6;
        }

        .font-bold {
            font-weight: bold;
        }
    </style>
</head>

<body>
    @if ($letterhead && $letterhead->logo_path)
        <div class="header">
            <img src="{{ storage_path('app/public/' . $letterhead->logo_path) }}" class="logo">
        </div>
    @endif

    <h1 class="title">CONVOCAZIONE UFFICIALE ARBITRI</h1>

    <div class="content">
        <p><strong>Torneo:</strong> {{ $tournament->name }}</p>
        <p><strong>Date:</strong> {{ $tournament->date_range }}</p>
        <p><strong>Circolo:</strong> {{ $tournament->club->name }}</p>
        <p><strong>Tipo:</strong> {{ $tournament->tournamentType->name ?? 'N/A' }}</p>
    </div>

    <h3>Arbitri Convocati:</h3>

    <table class="referee-table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Codice</th>
                <th>Livello</th>
                <th>Ruolo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($referees as $referee)
                <tr class="{{ $referee['is_director'] ? 'director-row' : '' }}">
                    <td class="{{ $referee['is_director'] ? 'font-bold' : '' }}">
                        {{ $referee['name'] }}
                    </td>
                    <td>{{ $referee['code'] }}</td>
                    <td>{{ $referee['level'] }}</td>
                    <td class="{{ $referee['is_director'] ? 'font-bold' : '' }}">
                        {{ $referee['role'] }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Documento generato il {{ $generated_at }}</p>
        <p>Comitato Regionale Arbitri - {{ $tournament->zone->name }}</p>
    </div>
</body>

</html>
