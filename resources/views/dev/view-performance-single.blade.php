<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $result['view'] }} - Performance</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
            padding: 40px;
        }
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        .view-name {
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 18px;
            opacity: 0.95;
            background: rgba(255,255,255,0.2);
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .content {
            padding: 40px;
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        .metric-card {
            background: #f8fafc;
            padding: 25px;
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }
        .metric-label {
            color: #64748b;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .metric-value {
            font-size: 36px;
            font-weight: bold;
            color: #1e293b;
        }
        .metric-unit {
            font-size: 18px;
            color: #64748b;
            margin-left: 5px;
        }
        .rating-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        .rating-title {
            font-size: 20px;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        .rating-badge {
            display: inline-block;
            padding: 15px 40px;
            background: white;
            color: #1e293b;
            border-radius: 50px;
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .chart-section {
            margin-bottom: 40px;
        }
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #1e293b;
        }
        .chart-container {
            background: #f8fafc;
            padding: 25px;
            border-radius: 12px;
        }
        .chart-row {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        .chart-label {
            width: 120px;
            font-size: 14px;
            color: #64748b;
            font-weight: 600;
        }
        .chart-bar-container {
            flex: 1;
            height: 30px;
            background: #e2e8f0;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        .chart-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-size: 12px;
            font-weight: bold;
            transition: width 0.5s ease;
        }
        .iterations-table {
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .iterations-table th {
            background: #f8fafc;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
        }
        .iterations-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        .iterations-table tr:hover {
            background: #f8fafc;
        }
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-excellent { background: #d1fae5; color: #065f46; }
        .badge-good { background: #dbeafe; color: #1e40af; }
        .badge-acceptable { background: #fef3c7; color: #92400e; }
        .badge-needs-optimization { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚡ Performance Analysis</h1>
            <div class="view-name">{{ $result['view'] }}</div>
        </div>

        <div class="content">
            @if(isset($result['error']))
                <div style="padding: 40px; text-align: center; color: #ef4444;">
                    <h2>❌ Errore</h2>
                    <p>{{ $result['error'] }}</p>
                </div>
            @else
                <div class="rating-section">
                    <div class="rating-title">Overall Performance Rating</div>
                    <div class="rating-badge">{{ strtoupper(str_replace('-', ' ', $result['rating']['overall'])) }}</div>
                </div>

                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-label">Tempo Medio</div>
                        <div class="metric-value">
                            {{ $result['avg_time_ms'] }}
                            <span class="metric-unit">ms</span>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Memoria Media</div>
                        <div class="metric-value">
                            {{ $result['avg_memory_kb'] }}
                            <span class="metric-unit">KB</span>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Tempo Minimo</div>
                        <div class="metric-value">
                            {{ $result['min_time_ms'] }}
                            <span class="metric-unit">ms</span>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Tempo Massimo</div>
                        <div class="metric-value">
                            {{ $result['max_time_ms'] }}
                            <span class="metric-unit">ms</span>
                        </div>
                    </div>
                </div>

                <div class="chart-section">
                    <div class="chart-title">📊 Metriche Dettagliate</div>
                    <div class="chart-container">
                        <div class="chart-row">
                            <div class="chart-label">Tempo Rating:</div>
                            <span class="badge badge-{{ $result['rating']['time'] }}">
                                {{ ucfirst($result['rating']['time']) }}
                            </span>
                        </div>
                        <div class="chart-row">
                            <div class="chart-label">Memoria Rating:</div>
                            <span class="badge badge-{{ $result['rating']['memory'] }}">
                                {{ ucfirst($result['rating']['memory']) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="chart-section">
                    <div class="chart-title">📈 Analisi per Iterazione ({{ $result['iterations'] }} runs)</div>
                    <table class="iterations-table">
                        <thead>
                            <tr>
                                <th style="width: 15%">Run #</th>
                                <th style="width: 40%">Tempo (ms)</th>
                                <th style="width: 45%">Memoria (KB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($result['times_ms'] as $index => $time)
                                <tr>
                                    <td><strong>{{ $index + 1 }}</strong></td>
                                    <td>
                                        <div class="chart-row" style="margin: 0;">
                                            <span style="width: 60px; display: inline-block;">{{ round($time, 2) }} ms</span>
                                            <div class="chart-bar-container" style="max-width: 200px;">
                                                <div class="chart-bar-fill" style="width: {{ min(100, ($time / $result['max_time_ms']) * 100) }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="chart-row" style="margin: 0;">
                                            <span style="width: 60px; display: inline-block;">{{ round($result['memory_kb'][$index], 2) }} KB</span>
                                            <div class="chart-bar-container" style="max-width: 200px;">
                                                <div class="chart-bar-fill" style="width: {{ min(100, ($result['memory_kb'][$index] / max($result['memory_kb'])) * 100) }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 40px; padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 4px solid #3b82f6;">
                    <h3 style="margin-bottom: 10px; color: #1e293b;">💡 Interpretazione Risultati</h3>
                    <ul style="line-height: 1.8; color: #64748b;">
                        <li><strong>Excellent:</strong> Rendering < 10ms, Memoria < 100KB</li>
                        <li><strong>Good:</strong> Rendering < 50ms, Memoria < 500KB</li>
                        <li><strong>Acceptable:</strong> Rendering < 100ms, Memoria < 1MB</li>
                        <li><strong>Needs Optimization:</strong> Rendering > 100ms o Memoria > 1MB</li>
                    </ul>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
