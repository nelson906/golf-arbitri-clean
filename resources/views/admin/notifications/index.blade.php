{{-- File: resources/views/admin/notifications/index.blade.php - RIDISEGNATA --}}
@extends('layouts.admin')

@section('content')
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📧 Notifiche Tornei Inviate
            </h2>

            <div class="flex space-x-3">
                <a href="{{ route('admin.tournaments.index') }}"
                   class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    ➕ Invia Nuove Notifiche
                </a>
                <a href="{{ route('admin.notifications.stats') }}"
                   class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                    📊 Statistiche
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-400 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    {{-- Stats Header --}}
                    @php
                        // Raggruppa notifiche per torneo/sessione
                        $groupedNotifications = $notifications->groupBy(function($notification) {
                            // Prova a trovare il torneo
                            if ($notification->assignment && $notification->assignment->tournament) {
                                return 'tournament_' . $notification->assignment->tournament->id;
                            }

                            // Fallback: raggruppa per subject + data (stesso batch di invio)
                            $baseSubject = preg_replace('/\s*-\s*\d{2}\/\d{2}\/\d{4}.*$/', '', $notification->subject);
                            $date = $notification->created_at->format('Y-m-d H:i');
                            return 'batch_' . md5($baseSubject . $date);
                        });

                        $totalGroups = $groupedNotifications->count();
                        $totalNotifications = $notifications->count();
                        $totalSent = $notifications->where('status', 'sent')->count();
                        $totalFailed = $notifications->where('status', 'failed')->count();
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ $totalGroups }}</div>
                            <div class="text-sm text-blue-600">Tornei Notificati</div>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-purple-600">{{ $totalNotifications }}</div>
                            <div class="text-sm text-purple-600">Email Totali</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">{{ $totalSent }}</div>
                            <div class="text-sm text-green-600">Inviate</div>
                        </div>
                        <div class="bg-red-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-red-600">{{ $totalFailed }}</div>
                            <div class="text-sm text-red-600">Fallite</div>
                        </div>
                    </div>

                    {{-- Filter Bar --}}
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <form method="GET" class="flex flex-wrap gap-4 items-end">
                            <div class="flex-1 min-w-48">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Periodo</label>
                                <select name="period" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Tutto il periodo</option>
                                    <option value="today" {{ request('period') === 'today' ? 'selected' : '' }}>Oggi</option>
                                    <option value="week" {{ request('period') === 'week' ? 'selected' : '' }}>Ultima settimana</option>
                                    <option value="month" {{ request('period') === 'month' ? 'selected' : '' }}>Ultimo mese</option>
                                </select>
                            </div>

                            <div class="flex-1 min-w-48">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                                <select name="status" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Tutti gli stati</option>
                                    <option value="all_sent" {{ request('status') === 'all_sent' ? 'selected' : '' }}>Tutti Inviati</option>
                                    <option value="has_failed" {{ request('status') === 'has_failed' ? 'selected' : '' }}>Con Errori</option>
                                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>In Sospeso</option>
                                </select>
                            </div>

                            <div class="flex space-x-2">
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                    🔍 Filtra
                                </button>
                                <a href="{{ route('admin.tournament-notifications.index') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    🗑️ Reset
                                </a>
                            </div>
                        </form>
                    </div>

                    @if($groupedNotifications->count() > 0)
                        {{-- Grouped Notifications Table --}}
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Torneo
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Destinatari
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Data Invio
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Stato
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Azioni
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($groupedNotifications as $groupKey => $groupNotifications)
                                        @php
                                            $firstNotification = $groupNotifications->first();
                                            $tournament = $firstNotification->assignment?->tournament;

                                            // Calcola statistiche del gruppo
                                            $totalInGroup = $groupNotifications->count();
                                            $sentInGroup = $groupNotifications->where('status', 'sent')->count();
                                            $failedInGroup = $groupNotifications->where('status', 'failed')->count();
                                            $pendingInGroup = $groupNotifications->where('status', 'pending')->count();

                                            // Determina status del gruppo
                                            $groupStatus = 'mixed';
                                            if ($sentInGroup === $totalInGroup) {
                                                $groupStatus = 'all_sent';
                                            } elseif ($failedInGroup > 0) {
                                                $groupStatus = 'has_failed';
                                            } elseif ($pendingInGroup > 0) {
                                                $groupStatus = 'pending';
                                            }

                                            // Estrai nome torneo
                                            $tournamentName = $tournament ? $tournament->name : 'Torneo non specificato';
                                            if (!$tournament && $firstNotification->subject) {
                                                // Estrai dal subject se possibile
                                                if (preg_match('/(?:Assegnazione.*?-\s*|torneo\s+)(.+?)(?:\s*che\s|\s*del\s|\s*-\s*\d{2}\/\d{2}\/\d{4}|$)/i', $firstNotification->subject, $matches)) {
                                                    $tournamentName = trim($matches[1]);
                                                }
                                            }

                                            // Raggruppa per tipo di destinatario
                                            $recipientTypes = $groupNotifications->groupBy('recipient_type');
                                            $recipientSummary = [];
                                            foreach ($recipientTypes as $type => $notifications) {
                                                $count = $notifications->count();
                                                $typeName = match($type) {
                                                    'referee' => 'Arbitri',
                                                    'club' => 'Circolo',
                                                    'institutional' => 'Istituzionali',
                                                    default => ucfirst($type)
                                                };
                                                $recipientSummary[] = "{$count} {$typeName}";
                                            }
                                        @endphp

                                        <tr class="hover:bg-gray-50" id="group-{{ $loop->index }}">
                                            <td class="px-6 py-4">
                                                <div class="flex items-start">
                                                    <button onclick="toggleDetails({{ $loop->index }})"
                                                            class="flex-shrink-0 mr-3 mt-1 text-gray-400 hover:text-gray-600">
                                                        <svg class="w-4 h-4 transform transition-transform" id="icon-{{ $loop->index }}">
                                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                        </svg>
                                                    </button>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">
                                                            {{ Str::limit($tournamentName, 50) }}
                                                        </div>
                                                        @if($tournament)
                                                            <div class="text-xs text-gray-500">
                                                                {{ $tournament->start_date->format('d/m/Y') }}
                                                                @if($tournament->club)
                                                                    • {{ $tournament->club->name }}
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900">
                                                    <strong>{{ $totalInGroup }}</strong> destinatari
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    {{ implode(' • ', $recipientSummary) }}
                                                </div>
                                                @if($groupNotifications->whereNotNull('attachments')->count() > 0)
                                                    <div class="text-xs text-blue-600 mt-1">
                                                        📎 Con allegati
                                                    </div>
                                                @endif
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    {{ $firstNotification->created_at->format('d/m/Y H:i') }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    {{ $firstNotification->created_at->diffForHumans() }}
                                                </div>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($groupStatus === 'all_sent')
                                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                                        ✅ Tutte Inviate
                                                    </span>
                                                @elseif($groupStatus === 'has_failed')
                                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                                                        ❌ {{ $failedInGroup }}/{{ $totalInGroup }} Fallite
                                                    </span>
                                                @elseif($groupStatus === 'pending')
                                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                                                        ⏳ {{ $pendingInGroup }} In Sospeso
                                                    </span>
                                                @else
                                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                                        🔄 {{ $sentInGroup }}/{{ $totalInGroup }} Inviate
                                                    </span>
                                                @endif

                                                <div class="text-xs text-gray-500 mt-1">
                                                    ✅ {{ $sentInGroup }} • ❌ {{ $failedInGroup }} • ⏳ {{ $pendingInGroup }}
                                                </div>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex flex-col space-y-1">
                                                    <button onclick="toggleDetails({{ $loop->index }})"
                                                            class="text-indigo-600 hover:text-indigo-900 text-xs">
                                                        👁️ Dettagli
                                                    </button>

                                                    @if($tournament)
                                                        <a href="{{ route('admin.tournaments.show-assignment-form', $tournament) }}"
                                                           class="text-blue-600 hover:text-blue-900 text-xs">
                                                            📧 Reinvia
                                                        </a>
                                                    @endif

                                                    @if($failedInGroup > 0)
                                                        <button onclick="retryFailedInGroup({{ $loop->index }})"
                                                                class="text-green-600 hover:text-green-900 text-xs">
                                                            🔄 Riprova Fallite
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>

                                        {{-- Dettagli espandibili --}}
                                        <tr id="details-{{ $loop->index }}" class="hidden bg-gray-50">
                                            <td colspan="5" class="px-6 py-4">
                                                <div class="space-y-3">
                                                    <h4 class="text-sm font-medium text-gray-900">Dettaglio Invii:</h4>
                                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                                        @foreach($groupNotifications as $notification)
                                                            <div class="bg-white p-3 rounded border-l-4
                                                                @if($notification->status === 'sent') border-green-500
                                                                @elseif($notification->status === 'failed') border-red-500
                                                                @else border-yellow-500 @endif">
                                                                <div class="text-sm font-medium">
                                                                    @php
                                                                        $recipientName = $notification->recipient_name ?? explode('@', $notification->recipient_email)[0];
                                                                    @endphp
                                                                    {{ $recipientName }}
                                                                </div>
                                                                <div class="text-xs text-gray-500">
                                                                    {{ $notification->recipient_email }}
                                                                </div>
                                                                <div class="text-xs text-gray-500">
                                                                    {{ ucfirst($notification->recipient_type) }}
                                                                    @if($notification->status === 'sent')
                                                                        • {{ $notification->sent_at?->format('H:i') }}
                                                                    @elseif($notification->status === 'failed')
                                                                        • {{ Str::limit($notification->error_message, 30) }}
                                                                    @endif
                                                                </div>
                                                                @if($notification->attachments && count($notification->attachments) > 0)
                                                                    <div class="text-xs text-blue-600">
                                                                        📎 {{ count($notification->attachments) }} allegati
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    @else
                        {{-- Empty State --}}
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">📭</div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Nessuna notifica di torneo trovata</h3>
                            <p class="text-gray-500 mb-6">Inizia inviando le notifiche per un torneo.</p>
                            <a href="{{ route('admin.tournaments.index') }}"
                               class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                📝 Vai ai Tornei
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript per gestire dettagli espandibili --}}
    <script>
        function toggleDetails(index) {
            const details = document.getElementById(`details-${index}`);
            const icon = document.getElementById(`icon-${index}`);

            if (details.classList.contains('hidden')) {
                details.classList.remove('hidden');
                icon.classList.add('rotate-90');
            } else {
                details.classList.add('hidden');
                icon.classList.remove('rotate-90');
            }
        }

        function retryFailedInGroup(index) {
            if (confirm('Vuoi riprovare l\'invio delle notifiche fallite per questo torneo?')) {
                // TODO: Implementare retry delle notifiche fallite
                alert('Funzionalità in implementazione');
            }
        }
    </script>

    <style>
        .rotate-90 {
            transform: rotate(90deg);
        }
    </style>
@endsection
