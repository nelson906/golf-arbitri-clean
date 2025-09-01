<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Pagina Non Trovata</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px;display:flex;align-items:center;justify-content:center;min-height:100vh}
        .error{background:white;padding:40px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);text-align:center;max-width:500px}
        .error h1{color:#f39c12;margin-bottom:20px;font-size:3em}
        .error p{color:#666;margin-bottom:30px}
        .btn{display:inline-block;padding:10px 20px;background:#3498db;color:white;text-decoration:none;border-radius:5px;margin:5px}
        .btn:hover{background:#2980b9}
    </style>
</head>
<body>
    <div class="error">
        <h1>404</h1>
        <p>🔍 Non hai le autorizzazioni per visitare la pagina.</p>
        <a href="/" class="btn">🏠 Vai alla Home</a>
        <button onclick="history.back()" class="btn">⬅️ Indietro</button>
    </div>
</body>
</html>
