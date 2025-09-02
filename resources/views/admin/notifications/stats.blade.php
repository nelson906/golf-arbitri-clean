{{-- File: resources/views/admin/notifications/stats.blade.php --}}
@extends('layouts.admin')

@section('title', ' ' )

@section('content')

    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📊 Statistiche Notifiche
            </h2>
            <div class="flex space-x-3">
                <select id="period-selector" onchange="changePeriod(this.value)"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                    <option value="7" {{ $days == 7 ? 'selected' : '' }}>Ultimi 7 giorni</option>
                    <option value="30" {{ $days == 30 ? 'selected' : '' }}>Ultimo mese</option>
                    <option value="90" {{ $days == 90 ? 'selected' : '' }}>Ultimi 3 mesi</option>
                    <option value="365" {{ $days == 365 ? 'selected' : '' }}>Ultimo anno</option>
                </select>

                <a href="{{ route('admin.notifications.export') }}?days={{ $days }}"
                   class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    📥 Esporta CSV
                </a>

                <a href="{{ route('admin.tournament-notifications.index') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    📧 Tutte le Notifiche
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- Main Stats Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm font-bold">📧</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Totale Notifiche</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ number_format($stats['total']) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <span class="text-gray-600">Ultimi {{ $days }} giorni</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm font-bold">✅</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Inviate</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ number_format($stats['sent']) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <span class="text-green-600 font-medium">
                                {{ $stats['total'] > 0 ? round(($stats['sent'] / $stats['total']) * 100, 1) : 0 }}%
                            </span>
                            <span class="text-gray-600">del totale</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm font-bold">⏳</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">In Sospeso</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ number_format($stats['pending']) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <span class="text-yellow-600 font-medium">
                                {{ $stats['total'] > 0 ? round(($stats['pending'] / $stats['total']) * 100, 1) : 0 }}%
                            </span>
                            <span class="text-gray-600">del totale</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm font-bold">❌</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Fallite</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ number_format($stats['failed']) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <span class="text-red-600 font-medium">
                                {{ $stats['total'] > 0 ? round(($stats['failed'] / $stats['total']) * 100, 1) : 0 }}%
                            </span>
                            <span class="text-gray-600">del totale</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Charts Row --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                {{-- Daily Trend Chart --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">📈 Andamento Giornaliero</h3>
                        <div class="h-80">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Type Distribution Chart --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">📊 Distribuzione per Tipo</h3>
                        <div class="h-80">
                            <canvas id="typeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Data Tables Row --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                {{-- Top Recipients --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">👥 Top Destinatari</h3>
                        @if($topRecipients->count() > 0)
                            <div class="space-y-3">
                                @foreach($topRecipients as $recipient)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <div class="flex-shrink-0">
                                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                    <span class="text-blue-600 text-xs font-bold">📧</span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ Str::limit($recipient->recipient_email, 30) }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-sm font-medium text-gray-500">
                                            {{ $recipient->count }} notifiche
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-6">
                                <div class="text-gray-500">Nessun dato disponibile</div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Template Usage --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">📝 Template Più Utilizzati</h3>
                        @if($templateUsage->count() > 0)
                            <div class="space-y-3">
                                @foreach($templateUsage as $template)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <div class="flex-shrink-0">
                                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                                    <span class="text-green-600 text-xs font-bold">📝</span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ Str::limit($template->template_used, 30) }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-sm font-medium text-gray-500">
                                            {{ $template->count }} utilizzi
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-6">
                                <div class="text-gray-500">Nessun template utilizzato</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Failed Notifications Alert --}}
            @if($failedNotifications->count() > 0)
                <div class="bg-red-50 border border-red-200 rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">
                                    ⚠️ Notifiche Fallite che Richiedono Attenzione ({{ $failedNotifications->count() }})
                                </h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <p>Le seguenti notifiche hanno superato il numero massimo di tentativi:</p>
                                </div>
                                <div class="mt-4">
                                    <div class="space-y-2">
                                        @foreach($failedNotifications->take(5) as $notification)
                                            <div class="flex items-center justify-between p-2 bg-white rounded border">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ $notification->recipient_email }}
                                                    </div>
                                                    <div class="text-xs text-gray-600">
                                                        {{ $notification->subject }} - {{ $notification->created_at->diffForHumans() }}
                                                    </div>
                                                </div>
                                                <div class="flex space-x-2">
                                                    <a href="{{ route('admin.notifications.show', $notification) }}"
                                                       class="text-xs text-indigo-600 hover:text-indigo-900">
                                                        👁️ Dettagli
                                                    </a>
                                                    @if($notification->canBeRetried())
                                                        <form method="POST" action="{{ route('admin.notifications.resend', $notification) }}" class="inline">
                                                            @csrf
                                                            <button type="submit"
                                                                    class="text-xs text-green-600 hover:text-green-900"
                                                                    onclick="return confirm('Sei sicuro di voler reinviare questa notifica?')">
                                                                🔄 Reinvia
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                        @if($failedNotifications->count() > 5)
                                            <div class="text-xs text-gray-600 text-center">
                                                ... e altre {{ $failedNotifications->count() - 5 }} notifiche
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <a href="{{ route('admin.tournament-notifications.index') }}?status=failed"
                                       class="text-sm text-red-600 hover:text-red-500 underline">
                                        Visualizza tutte le notifiche fallite →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Summary Table --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">📋 Riepilogo Dettagliato</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tipo Destinatario
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Totale
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Inviate
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        In Sospeso
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fallite
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tasso Successo
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach(['referee' => 'Arbitri', 'club' => 'Circoli', 'institutional' => 'Istituzionali'] as $type => $label)
                                    @php
                                        $typeCount = $stats['by_type'][$type] ?? 0;
                                        $typeStats = \App\Models\Notification::where('created_at', '>=', now()->subDays($days))
                                                      ->where('recipient_type', $type)
                                                      ->selectRaw('status, COUNT(*) as count')
                                                      ->groupBy('status')
                                                      ->pluck('count', 'status')
                                                      ->toArray();
                                        $sent = $typeStats['sent'] ?? 0;
                                        $pending = $typeStats['pending'] ?? 0;
                                        $failed = $typeStats['failed'] ?? 0;
                                        $successRate = $typeCount > 0 ? round(($sent / $typeCount) * 100, 1) : 0;
                                    @endphp
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $label }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ number_format($typeCount) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                                            {{ number_format($sent) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-600">
                                            {{ number_format($pending) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600">
                                            {{ number_format($failed) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-1 bg-gray-200 rounded-full h-2 mr-2">
                                                    <div class="bg-green-500 h-2 rounded-full" style="width: {{ $successRate }}%"></div>
                                                </div>
                                                <span class="text-sm text-gray-600">{{ $successRate }}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Chart.js Integration --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Change period function
        function changePeriod(days) {
            window.location.href = `{{ route('admin.notifications.stats') }}?days=${days}`;
        }

        // Daily Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyChart = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: [
                    @foreach($dailyStats as $date => $stats)
                        '{{ \Carbon\Carbon::parse($date)->format('d/m') }}',
                    @endforeach
                ],
                datasets: [{
                    label: 'Inviate',
                    data: [
                        @foreach($dailyStats as $date => $stats)
                            {{ $stats['sent'] ?? 0 }},
                        @endforeach
                    ],
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.1
                }, {
                    label: 'Fallite',
                    data: [
                        @foreach($dailyStats as $date => $stats)
                            {{ $stats['failed'] ?? 0 }},
                        @endforeach
                    ],
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Type Distribution Chart
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        const typeChart = new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Arbitri', 'Circoli', 'Istituzionali'],
                datasets: [{
                    data: [
                        {{ $stats['by_type']['referee'] ?? 0 }},
                        {{ $stats['by_type']['club'] ?? 0 }},
                        {{ $stats['by_type']['institutional'] ?? 0 }}
                    ],
                    backgroundColor: [
                        'rgb(59, 130, 246)',
                        'rgb(168, 85, 247)',
                        'rgb(234, 179, 8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
@endsection
