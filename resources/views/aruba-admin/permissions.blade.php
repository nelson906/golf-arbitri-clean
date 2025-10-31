@extends('aruba-admin.layout')

@section('title', 'Permessi - Aruba Admin')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">
                    <i class="fas fa-lock text-blue-600"></i>
                    Verifica Permessi
                </h1>
                <p class="text-gray-600 mt-2">Controlla e correggi i permessi delle cartelle critiche</p>
            </div>
            <div>
                <form method="POST" action="{{ route('aruba.admin.permissions.fix') }}"
                      onsubmit="return confirm('Tentare di correggere i permessi? (potrebbe non funzionare su alcuni hosting)')">
                    @csrf
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-wrench"></i> Correggi Permessi
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Permissions Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <i class="fas fa-folder"></i> Cartella
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <i class="fas fa-check-circle"></i> Esiste
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <i class="fas fa-pencil-alt"></i> Scrivibile
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <i class="fas fa-key"></i> Permessi
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <i class="fas fa-info-circle"></i> Stato
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($permissions as $name => $info)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ $name }}</div>
                                <div class="text-xs text-gray-500 font-mono truncate" style="max-width: 400px;">
                                    {{ $info['path'] }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($info['exists'])
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check mr-1"></i> Sì
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <i class="fas fa-times mr-1"></i> No
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($info['writable'])
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check mr-1"></i> Sì
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <i class="fas fa-times mr-1"></i> No
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="font-mono text-sm {{ $info['permissions'] === '0775' || $info['permissions'] === '0755' ? 'text-green-600' : 'text-orange-600' }}">
                                    {{ $info['permissions'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($info['exists'] && $info['writable'])
                                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                                @elseif($info['exists'] && !$info['writable'])
                                    <i class="fas fa-exclamation-triangle text-yellow-500 text-xl"></i>
                                @else
                                    <i class="fas fa-times-circle text-red-500 text-xl"></i>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Legend -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">
            <i class="fas fa-book"></i> Legenda Permessi
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <h4 class="font-semibold text-gray-700 mb-2">Permessi consigliati:</h4>
                <ul class="space-y-1 text-gray-600">
                    <li><span class="font-mono font-bold">0775</span> - Lettura/scrittura per owner e gruppo</li>
                    <li><span class="font-mono font-bold">0755</span> - Lettura/scrittura per owner, solo lettura per altri</li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-gray-700 mb-2">Cartelle critiche:</h4>
                <ul class="space-y-1 text-gray-600">
                    <li><strong>storage/*</strong> - Cache, sessioni, log, upload</li>
                    <li><strong>bootstrap/cache</strong> - File di ottimizzazione Laravel</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Warning Info -->
    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-yellow-500 text-xl"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-yellow-800">Nota Importante su Aruba</h3>
                <div class="mt-2 text-sm text-yellow-700">
                    <p class="mb-2">Su hosting condivisi come Aruba, la correzione automatica dei permessi potrebbe non funzionare.</p>
                    <p class="mb-2"><strong>Se hai problemi di permessi:</strong></p>
                    <ol class="list-decimal list-inside space-y-1">
                        <li>Usa il File Manager di Aruba</li>
                        <li>Seleziona la cartella <code class="bg-yellow-100 px-1 rounded">storage</code></li>
                        <li>Clicca "Cambia permessi" e imposta <code class="bg-yellow-100 px-1 rounded">755</code> ricorsivamente</li>
                        <li>Ripeti per <code class="bg-yellow-100 px-1 rounded">bootstrap/cache</code></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- SSH Commands -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-terminal text-blue-500 text-xl"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Se hai accesso SSH</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <p class="mb-2">Puoi correggere i permessi manualmente con questi comandi:</p>
                    <pre class="bg-blue-100 p-2 rounded font-mono text-xs overflow-x-auto">chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache</pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
