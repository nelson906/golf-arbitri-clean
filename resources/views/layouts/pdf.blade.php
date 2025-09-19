<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #333;
            padding: 15mm; /* Padding semplice invece di margin auto */
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #0066cc;
        }

        .logo {
            max-height: 80px;
            width: auto;
            margin-bottom: 15px;
        }

        .zone-title {
            font-size: 12pt;
            color: #666;
            font-style: italic;
        }

        .document-title {
            font-size: 18pt;
            font-weight: bold;
            text-align: center;
            margin: 30px 0;
            text-transform: uppercase;
        }

        .info-section {
            margin: 25px 0;
        }

        .info-item {
            margin-bottom: 8px;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            min-width: 80px;
        }

        .referees-section {
            margin: 30px 0;
        }

        .role-group {
            margin-bottom: 20px;
        }

        .role-title {
            font-weight: bold;
            color: #0066cc;
            font-size: 12pt;
            margin-bottom: 8px;
        }

        .referee-item {
            margin-left: 20px;
            margin-bottom: 4px;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 9pt;
            color: #888;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
