<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Orphaned Views</title>
    <style>
        body { font-family: system-ui; margin: 0; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; padding: 30px; }
        h1 { color: #333; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0; }
        .stat { padding: 20px; background: #f8f9fa; border-radius: 6px; text-align: center; }
        .stat-number { font-size: 32px; font-weight: bold; }
        .stat-label { color: #666; font-size: 12px; text-transform: uppercase; }
        .section { margin: 30px 0; }
        .section h2 { color: #333; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; }
        .view-list { margin-top: 20px; }
        .view-item { padding: 15px; margin: 10px 0; background: #f8f9fa; border-left: 4px solid #dc3545; border-radius: 4px; }
        .view-item.used { border-left-color: #28a745; }
        .view-name { font-family: monospace; font-size: 14px; margin-bottom: 10px; }
        .btn { display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 12px; }
        .export-btn { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Orphaned Views Detector</h1>
        
        <button onclick="exportList()" class="export-btn">📄 Esporta TXT</button>
        <button onclick="window.print()" class="export-btn">🖨️ Stampa</button>

        <div class="stats">
            <div class="stat">
                <div class="stat-number">{{ $total }}</div>
                <div class="stat-label">Totale</div>
            </div>
            <div class="stat">
                <div class="stat-number">{{ $orphanedCount }}</div>
                <div class="stat-label">Orphaned</div>
            </div>
            <div class="stat">
                <div class="stat-number">{{ $usedCount }}</div>
                <div class="stat-label">In Uso</div>
            </div>
            <div class="stat">
                <div class="stat-number">{{ $orphanedCount > 0 ? round(($orphanedCount / $total) * 100, 1) : 0 }}%</div>
                <div class="stat-label">% Orphaned</div>
            </div>
        </div>

        @if($orphanedCount > 0)
        <div class="section">
            <h2>View Orphaned ({{ $orphanedCount }})</h2>
            <div class="view-list">
                @foreach($orphaned as $viewName)
                <div class="view-item">
                    <div class="view-name">{{ $viewName }}</div>
                    <a href="{{ url('/dev/view-preview/' . str_replace('.', '/', $viewName)) }}" class="btn" target="_blank">
                        Anteprima
                    </a>
                    <div style="font-size: 11px; color: #666; margin-top: 8px;">
                        Path: resources/views/{{ str_replace('.', '/', $viewName) }}.blade.php
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="section">
            <h2>View in Uso ({{ $usedCount }})</h2>
            <div class="view-list">
                @foreach($used as $viewName)
                <div class="view-item used">
                    <div class="view-name">{{ $viewName }}</div>
                    <a href="{{ url('/dev/view-preview/' . str_replace('.', '/', $viewName)) }}" class="btn" target="_blank">
                        Anteprima
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        function exportList() {
            let text = 'ORPHANED VIEWS REPORT\n';
            text += '======================\n\n';
            text += 'Total: {{ $total }}\n';
            text += 'Orphaned: {{ $orphanedCount }}\n';
            text += 'Used: {{ $usedCount }}\n\n';
            
            @if($orphanedCount > 0)
            text += 'ORPHANED:\n';
            @foreach($orphaned as $viewName)
            text += '- {{ $viewName }}\n';
            @endforeach
            @endif
            
            text += '\nUSED:\n';
            @foreach($used as $viewName)
            text += '- {{ $viewName }}\n';
            @endforeach
            
            const blob = new Blob([text], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'orphaned-views-' + new Date().toISOString().split('T')[0] + '.txt';
            a.click();
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
