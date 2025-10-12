<!DOCTYPE html>
<html>
<head>
    <title>Test Tutte le View</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto py-8 px-4">
        <h1 class="text-3xl font-bold mb-6">📊 Test Risultati - Tutte le View</h1>

        {{-- Statistiche --}}
        <div class="grid grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-3xl font-bold text-gray-900">{{ $total }}</div>
                <div class="text-sm text-gray-600">Totale View</div>
            </div>

            <div class="bg-green-50 rounded-lg shadow p-6">
                <div class="text-3xl font-bold text-green-600">{{ $successCount }}</div>
                <div class="text-sm text-gray-600">✅ Funzionanti ({{ round($successCount/$total*100) }}%)</div>
            </div>

            <div class="bg-yellow-50 rounded-lg shadow p-6">
                <div class="text-3xl font-bold text-yellow-600">{{ $partialCount }}</div>
                <div class="text-sm text-gray-600">⚠️ Parziali ({{ round($partialCount/$total*100) }}%)</div>
            </div>

            <div class="bg-red-50 rounded-lg shadow p-6">
                <div class="text-3xl font-bold text-red-600">{{ $failedCount }}</div>
                <div class="text-sm text-gray-600">❌ Non Funzionanti ({{ round($failedCount/$total*100) }}%)</div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="bg-white rounded-lg shadow">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button onclick="showTab('success')" class="tab-btn px-6 py-3 border-b-2 border-green-500 font-medium text-green-600">
                        ✅ Funzionanti ({{ $successCount }})
                    </button>
                    <button onclick="showTab('partial')" class="tab-btn px-6 py-3 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700">
                        ⚠️ Parziali ({{ $partialCount }})
                    </button>
                    <button onclick="showTab('failed')" class="tab-btn px-6 py-3 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700">
                        ❌ Non Funzionanti ({{ $failedCount }})
                    </button>
                </nav>
            </div>

            {{-- Success --}}
            <div id="tab-success" class="tab-content p-6">
                <p class="text-sm text-gray-600 mb-4">Queste view si renderizzano correttamente e possono essere mantenute.</p>
                <div class="space-y-2">
                    @foreach($results['success'] as $view)
                        <div class="flex justify-between items-center p-3 bg-green-50 rounded hover:bg-green-100">
                            <div>
                                <a href="{{ route('dev.view-preview', ['view' => str_replace('.', '/', $view['name'])]) }}"
                                   class="font-medium text-green-700 hover:text-green-900" target="_blank">
                                    {{ $view['name'] }}
                                </a>
                                <div class="text-xs text-gray-500">{{ $view['path'] }}</div>
                            </div>
                            <div class="text-sm text-gray-600">{{ number_format($view['size'] / 1024, 1) }} KB</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Partial --}}
            <div id="tab-partial" class="tab-content p-6 hidden">
                <p class="text-sm text-gray-600 mb-4">Queste view si renderizzano parzialmente. Potrebbero avere errori minori.</p>
                <div class="space-y-2">
                    @foreach($results['partial'] as $view)
                        <div class="p-3 bg-yellow-50 rounded hover:bg-yellow-100">
                            <div class="flex justify-between items-center mb-2">
                                <a href="{{ route('dev.view-preview', ['view' => str_replace('.', '/', $view['name'])]) }}"
                                   class="font-medium text-yellow-700 hover:text-yellow-900" target="_blank">
                                    {{ $view['name'] }}
                                </a>
                                <div class="text-sm text-gray-600">{{ number_format($view['size'] / 1024, 1) }} KB</div>
                            </div>
                            @if($view['error'])
                                <div class="text-xs text-gray-600 bg-white p-2 rounded">{{ $view['error'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Failed --}}
            <div id="tab-failed" class="tab-content p-6 hidden">
                <p class="text-sm text-red-600 mb-4">⚠️ Queste view NON si renderizzano. Probabilmente sono inutilizzate e possono essere ELIMINATE.</p>
                <div class="space-y-2">
                    @foreach($results['failed'] as $view)
                        <div class="p-3 bg-red-50 rounded hover:bg-red-100">
                            <div class="flex justify-between items-center mb-2">
                                <div class="font-medium text-red-700">{{ $view['name'] }}</div>
                                <div class="text-sm text-gray-600">{{ number_format($view['size'] / 1024, 1) }} KB</div>
                            </div>
                            <div class="text-xs text-gray-500 mb-1">{{ $view['path'] }}</div>
                            @if($view['error'])
                                <div class="text-xs text-red-600 bg-white p-2 rounded font-mono">{{ Str::limit($view['error'], 150) }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tab) {
            // Hide all
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('border-green-500', 'border-yellow-500', 'border-red-500', 'text-green-600', 'text-yellow-600', 'text-red-600');
                el.classList.add('border-transparent', 'text-gray-500');
            });

            // Show selected
            document.getElementById('tab-' + tab).classList.remove('hidden');
            event.target.classList.remove('border-transparent', 'text-gray-500');

            if (tab === 'success') {
                event.target.classList.add('border-green-500', 'text-green-600');
            } else if (tab === 'partial') {
                event.target.classList.add('border-yellow-500', 'text-yellow-600');
            } else {
                event.target.classList.add('border-red-500', 'text-red-600');
            }
        }
    </script>
</body>
</html>
