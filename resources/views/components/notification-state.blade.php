@props(['notification' => null, 'label', 'waiting' => 'Da inviare', 'waitingTone' => 'gray'])
{{-- Stato di una notifica nazionale (decisione 2026-10-07: una notifica non
     inviata deve essere ben visibile, mai confusa con "Inviata") --}}
@php
    $status = $notification?->status;
    $isFailed = $status === 'failed';
    $isSent = in_array($status, ['sent', 'partial'], true) && $notification?->sent_at;
    $tone = $isFailed ? 'red' : ($isSent ? ($status === 'partial' ? 'yellow' : 'green') : $waitingTone);
    $classes = [
        'red' => ['bg-red-50 border border-red-300', 'bg-red-500', 'text-red-800', 'text-red-700'],
        'green' => ['bg-green-50 border border-green-200', 'bg-green-500', 'text-green-800', 'text-green-600'],
        'yellow' => ['bg-yellow-50 border border-yellow-200', 'bg-yellow-500', 'text-yellow-800', 'text-yellow-700'],
        'gray' => ['bg-gray-50 border border-gray-200', 'bg-gray-400', 'text-gray-600', 'text-gray-500'],
    ][$tone];
    $text = $isFailed ? 'NON inviata' : ($isSent ? $notification->stateLabel() : $waiting);
@endphp
<div class="p-3 rounded-lg {{ $classes[0] }}">
    <div class="flex items-center">
        <span class="w-3 h-3 {{ $classes[1] }} rounded-full mr-2"></span>
        <span class="text-sm font-medium {{ $classes[2] }}">{{ $label }}: {{ $text }}</span>
    </div>
    @if ($isFailed)
        <p class="text-xs {{ $classes[3] }} mt-1">
            Tentativo del {{ $notification->lastAttemptAt()?->format('d/m/Y H:i') ?? '—' }}: nessuna mail è partita.
            @if ($notification->lastError())
                <br>{{ $notification->lastError() }}
            @endif
        </p>
        @if ($notification->sent_at)
            <p class="text-xs text-gray-500 mt-1">Ultimo invio riuscito: {{ $notification->sent_at->format('d/m/Y H:i') }}</p>
        @endif
    @elseif ($isSent)
        <p class="text-xs {{ $classes[3] }} mt-1">{{ $notification->sent_at->format('d/m/Y H:i') }}</p>
    @endif
</div>
