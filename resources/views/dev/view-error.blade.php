<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Errore - View Preview</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-red-50 border-l-4 border-red-500 p-6 mb-6">
            <div class="flex items-center mb-2">
                <span class="text-2xl mr-2">❌</span>
                <h1 class="text-xl font-bold text-red-900">Errore nel Rendering della View</h1>
            </div>
            <p class="text-red-700">La view <code class="bg-red-100 px-2 py-1 rounded">{{ $view }}</code> ha
                generato un errore</p>
        </div>

        {{-- Messaggio Errore --}}
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">💬 Messaggio Errore</h2>
            <div class="bg-red-50 border border-red-200 rounded p-4">
                <pre class="text-sm text-red-800 whitespace-pre-wrap">{{ $error }}</pre>
            </div>
        </div>

        {{-- Variables using ->user --}}
        @if (isset($suspectVariables) && !empty($suspectVariables))
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-3">🎯 Variabili che usano ->user</h2>
                <div class="bg-gray-50 border border-gray-200 rounded p-4">
                    <ul class="list-disc list-inside text-sm text-gray-700">
                        @foreach ($suspectVariables as $var)
                            <li><code class="bg-gray-200 px-2 py-1 rounded">${{ $var }}</code></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Variable Types Analysis --}}
        @if (isset($variableTypes) && !empty($variableTypes))
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-lg font-semibold text-red-900 mb-3">🔍 Analisi Tipi Variabili</h2>
                <div class="bg-red-50 border border-red-200 rounded p-4 overflow-x-auto">
                    <pre class="text-xs text-gray-700">{{ print_r($variableTypes, true) }}</pre>
                </div>
            </div>
        @endif

        {{-- Context --}}
        @if (isset($context) && !empty($context))
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-3">🔍 Contesto (Linee con ->user)</h2>
                <div class="bg-gray-50 border border-gray-200 rounded p-4 overflow-x-auto">
                    <pre class="text-xs text-gray-700">{{ $context }}</pre>
                </div>
            </div>
        @endif
        @if (isset($errorContext) && !empty($errorContext))
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-lg font-semibold text-red-900 mb-3">🎯 Codice Esatto dell'Errore</h2>
                <div class="bg-red-50 border border-red-200 rounded p-4 overflow-x-auto">
                    <pre class="text-xs font-mono text-gray-700">{{ $errorContext }}</pre>
                </div>
            </div>
        @endif

        {{-- Stack Trace --}}
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">🔍 Stack Trace</h2>
            <div class="bg-gray-50 border border-gray-200 rounded p-4 overflow-x-auto">
                <pre class="text-xs text-gray-700">{{ $trace }}</pre>
            </div>
        </div>

{{-- File Info --}}
<div class="bg-white rounded-lg shadow-lg p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-3">📄 Dettagli File</h2>
    <div class="space-y-2 text-sm">
        @php
            $originalFile = $file;
            // Se è un file compilato, cerca la view originale
            if (str_contains($file, 'storage/framework/views')) {
                try {
                    $compiled = file_get_contents($file);
                    if (preg_match('/\/\* (.+\.blade\.php) \*\//', $compiled, $matches)) {
                        $originalFile = $matches[1];
                    }
                } catch (\Throwable $e) {}
            }
        @endphp

        @if($originalFile !== $file)
            <p><strong>View Originale:</strong> <code class="bg-gray-100 px-2 py-1 rounded text-xs">{{ $originalFile }}</code></p>
        @endif

        <p><strong>File Compilato:</strong> <code class="bg-gray-100 px-2 py-1 rounded text-xs">{{ $file }}</code></p>
        <p><strong>Linea:</strong> <code class="bg-gray-100 px-2 py-1 rounded">{{ $line }}</code></p>

        {{-- Mostra il codice intorno alla linea --}}
        @if(file_exists($file))
            @php
                $lines = file($file);
                $start = max(0, $line - 3);
                $end = min(count($lines), $line + 2);
                $codeSnippet = '';
                for ($i = $start; $i < $end; $i++) {
                    $lineNum = $i + 1;
                    $prefix = ($lineNum == $line) ? '>>> ' : '    ';
                    $codeSnippet .= $prefix . str_pad($lineNum, 4) . ': ' . htmlspecialchars($lines[$i]);
                }
            @endphp

            @if(!empty($codeSnippet))
                <div class="mt-3">
                    <strong>Codice:</strong>
                    <pre class="bg-gray-900 text-green-400 p-3 rounded text-xs overflow-x-auto mt-2">{{ $codeSnippet }}</pre>
                </div>
            @endif
        @endif
    </div>
</div>

        {{-- Back Button --}}
        <div class="text-center">
            <a href="{{ route('dev.view-preview') }}"
                class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-semibold px-6 py-3 rounded-lg transition">
                ← Torna alla Lista View
            </a>
        </div>
    </div>
</body>

</html>
