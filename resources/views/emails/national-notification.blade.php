<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>{{ $subjectLine ?? 'Notifica' }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #333; max-width: 800px; margin: 0 auto; padding: 20px;">
    <div>
        {!! nl2br(e($body)) !!}
    </div>
</body>
</html>
