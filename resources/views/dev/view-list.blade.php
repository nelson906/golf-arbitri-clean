{{-- resources/views/dev/view-list.blade.php --}}
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Preview System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Header --}}
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">🔍 View Preview System</h1>
                <p class="text-gray-600">Clicca su una view per visualizzarla nel browser con dati mock</p>
                <div class="mt-4 p-4 bg-yellow-50 border-l-4 border-yellow-400">
                    <p class="text-sm text-yellow-700">
                        ⚠️ <strong>Solo ambiente DEV!</strong> Questo sistema è disponibile solo in local/staging.
                    </p>
                </div>
            </div>

            {{-- Search --}}
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <input type="text"
                       id="search"
                       placeholder="🔍 Cerca view... (es: emails, admin, components)"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500">Totale View</div>
                    <div class="text-2xl font-bold text-gray-900">{{ count($views) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500">Email</div>
                    <div class="text-2xl font-bold text-blue-600">
                        {{ collect($views)->filter(fn($v) => str_starts_with($v['name'], 'emails.'))->count() }}
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500">Admin</div>
                    <div class="text-2xl font-bold text-purple-600">
                        {{ collect($views)->filter(fn($v) => str_starts_with($v['name'], 'admin.'))->count() }}
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500">Components</div>
                    <div class="text-2xl font-bold text-green-600">
                        {{ collect($views)->filter(fn($v) => str_starts_with($v['name'], 'components.'))->count() }}
                    </div>
                </div>
            </div>

            {{-- View List --}}
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    View Name
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Path
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Size
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="view-tbody">
                            @foreach($views as $view)
                                <tr class="hover:bg-gray-50 view-row" data-name="{{ $view['name'] }}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            @if(str_starts_with($view['name'], 'emails.'))
                                                <span class="text-blue-500 mr-2">📧</span>
                                            @elseif(str_starts_with($view['name'], 'admin.'))
                                                <span class="text-purple-500 mr-2">👨‍💼</span>
                                            @elseif(str_starts_with($view['name'], 'components.'))
                                                <span class="text-green-500 mr-2">🧩</span>
                                            @elseif(str_starts_with($view['name'], 'user.'))
                                                <span class="text-yellow-500 mr-2">👤</span>
                                            @else
                                                <span class="text-gray-500 mr-2">📄</span>
                                            @endif
                                            <code class="text-sm font-mono text-gray-900">{{ $view['name'] }}</code>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $view['path'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ number_format($view['size'] / 1024, 2) }} KB
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <a href="{{ route('dev.view-preview', ['view' => str_replace('.', '/', $view['name'])]) }}"
                                           target="_blank"
                                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            👁️ Preview
                                        </a>
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
        // Search functionality
        document.getElementById('search').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.view-row');

            rows.forEach(row => {
                const viewName = row.getAttribute('data-name').toLowerCase();
                if (viewName.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>

