<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Performance Benchmark</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 40px;
        }
        .header h1 {
            font-size: 36px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header p {
            font-size: 18px;
            opacity: 0.9;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 30px 40px;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        .summary-card {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .summary-number {
            font-size: 42px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .summary-label {
            color: #64748b;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .content {
            padding: 40px;
        }
        .section {
            margin-bottom: 40px;
        }
        .section-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .perf-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .perf-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .perf-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        .perf-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        .perf-table tbody tr:hover {
            background: #f8fafc;
        }
        .view-name {
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 13px;
            color: #1e293b;
        }
        .metric {
            font-weight: 600;
            font-size: 15px;
        }
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-excellent {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-good {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-acceptable {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-needs-optimization {
            background: #fee2e2;
            color: #991b1b;
        }
        .chart-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        .chart-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }
        .chart-fill-excellent { background: linear-gradient(90deg, #10b981, #059669); }
        .chart-fill-good { background: linear-gradient(90deg, #3b82f6, #2563eb); }
        .chart-fill-acceptable { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .chart-fill-slow { background: linear-gradient(90deg, #ef4444, #dc2626); }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
            font-weight: 600;
            color: #64748b;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <span>⚡</span>
                View Performance Benchmark
            </h1>
            <p>Analisi prestazioni rendering di {{ $total }} views</p>
        </div>

        <div class="summary">
            <div class="summary-card">
                <div class="summary-number">{{ $total }}</div>
                <div class="summary-label">Views Testate</div>
            </div>
            <div class="summary-card">
                <div class="summary-number">{{ $summary['avg_time_ms'] }} ms</div>
                <div class="summary-label">Tempo Medio</div>
            </div>
            <div class="summary-card">
                <div class="summary-number">{{ $summary['avg_memory_kb'] }} KB</div>
                <div class="summary-label">Memoria Media</div>
            </div>
        </div>

        <div class="content">
            <div class="tabs">
                <button class="tab active" onclick="showTab('all')">Tutte ({{ $total }})</button>
                <button class="tab" onclick="showTab('slowest')">🐌 Le più Lente ({{ count($slowest) }})</button>
                <button class="tab" onclick="showTab('fastest')">🚀 Le più Veloci ({{ count($fastest) }})</button>
            </div>

            <!-- Tab: All Views -->
            <div id="tab-all" class="tab-content active">
                <div class="section">
                    <div class="section-title">
                        <span>📊</span>
                        Tutte le Views
                    </div>
                    <table class="perf-table">
                        <thead>
                            <tr>
                                <th style="width: 40%">View</th>
                                <th style="width: 15%">Tempo Medio</th>
                                <th style="width: 15%">Memoria</th>
                                <th style="width: 15%">Rating</th>
                                <th style="width: 15%">Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($results as $result)
                                <tr>
                                    <td>
                                        <div class="view-name">{{ $result['view'] }}</div>
                                    </td>
                                    <td>
                                        <div class="metric">{{ $result['avg_time_ms'] }} ms</div>
                                        <div class="chart-bar">
                                            <div class="chart-fill chart-fill-{{ $result['rating']['time'] }}" 
                                                 style="width: {{ min(100, ($result['avg_time_ms'] / 200) * 100) }}%"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="metric">{{ $result['avg_memory_kb'] }} KB</div>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $result['rating']['overall'] }}">
                                            {{ ucfirst(str_replace('-', ' ', $result['rating']['overall'])) }}
                                        </span>
                                    </td>
                                    <td>
                                        <small>Min: {{ $result['min_time_ms'] }}ms</small><br>
                                        <small>Max: {{ $result['max_time_ms'] }}ms</small>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Slowest -->
            <div id="tab-slowest" class="tab-content">
                <div class="section">
                    <div class="section-title">
                        <span>🐌</span>
                        Le 10 View più Lente
                    </div>
                    <table class="perf-table">
                        <thead>
                            <tr>
                                <th style="width: 5%">#</th>
                                <th style="width: 45%">View</th>
                                <th style="width: 20%">Tempo Medio</th>
                                <th style="width: 15%">Memoria</th>
                                <th style="width: 15%">Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($slowest as $index => $result)
                                <tr>
                                    <td><strong>{{ $index + 1 }}</strong></td>
                                    <td>
                                        <div class="view-name">{{ $result['view'] }}</div>
                                    </td>
                                    <td>
                                        <div class="metric" style="color: #ef4444;">{{ $result['avg_time_ms'] }} ms</div>
                                        <div class="chart-bar">
                                            <div class="chart-fill chart-fill-slow" 
                                                 style="width: 100%"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="metric">{{ $result['avg_memory_kb'] }} KB</div>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $result['rating']['overall'] }}">
                                            {{ ucfirst(str_replace('-', ' ', $result['rating']['overall'])) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Fastest -->
            <div id="tab-fastest" class="tab-content">
                <div class="section">
                    <div class="section-title">
                        <span>🚀</span>
                        Le 10 View più Veloci
                    </div>
                    <table class="perf-table">
                        <thead>
                            <tr>
                                <th style="width: 5%">#</th>
                                <th style="width: 45%">View</th>
                                <th style="width: 20%">Tempo Medio</th>
                                <th style="width: 15%">Memoria</th>
                                <th style="width: 15%">Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fastest as $index => $result)
                                <tr>
                                    <td><strong>{{ $index + 1 }}</strong></td>
                                    <td>
                                        <div class="view-name">{{ $result['view'] }}</div>
                                    </td>
                                    <td>
                                        <div class="metric" style="color: #10b981;">{{ $result['avg_time_ms'] }} ms</div>
                                        <div class="chart-bar">
                                            <div class="chart-fill chart-fill-excellent" 
                                                 style="width: {{ min(100, ($result['avg_time_ms'] / 10) * 100) }}%"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="metric">{{ $result['avg_memory_kb'] }} KB</div>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $result['rating']['overall'] }}">
                                            {{ ucfirst(str_replace('-', ' ', $result['rating']['overall'])) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
