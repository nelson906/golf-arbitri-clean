// ===========================
// EMAIL TEMPLATES (Blade Views)
// ===========================

/**
 * 📧 Template base per tutte le email torneo
 * File: resources/views/emails/tournament-notifications/layout.blade.php
 */
/*
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Notifica Torneo')</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c5aa0; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: white; }
        .footer { padding: 15px; background: #f8f9fa; font-size: 12px; color: #6c757d; }
        .tournament-info { background: #e3f2fd; padding: 15px; margin: 15px 0; border-left: 4px solid #2196f3; }
        .referee-list { margin: 15px 0; }
        .referee-item { padding: 8px; border-bottom: 1px solid #eee; }
        .btn { display: inline-block; padding: 10px 20px; background: #2c5aa0; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>@yield('header', 'Federazione Italiana Golf')</h1>
            <p>Sistema Gestione Arbitri</p>
        </div>

        <div class="content">
            @yield('content')

            @if($customMessage)
                <div style="background: #fff3cd; padding: 15px; margin: 15px 0; border-left: 4px solid #ffc107;">
                    <strong>Messaggio personalizzato:</strong><br>
                    {{ $customMessage }}
                </div>
            @endif
        </div>

        <div class="footer">
            <p>Questa è una comunicazione automatica del Sistema di Gestione Arbitri della Federazione Italiana Golf.</p>
            <p>Per informazioni: {{ config('tournament-notifications.email.from.address') }}</p>
        </div>
    </div>
</body>
</html>
