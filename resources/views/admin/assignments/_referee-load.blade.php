{{-- P7 (2026-10-03): altre designazioni della stagione e conflitti di date --}}
@php $load = $refereeLoad[$referee->id] ?? null; @endphp
<span class="block mt-1">
    @if ($load)
        <span class="text-xs text-gray-600"
              title="@foreach ($load['others'] as $o){{ $o['dates'] }} · {{ $o['name'] }} ({{ $o['role'] }})&#10;@endforeach">
            📋 {{ $load['count'] }} {{ $load['count'] === 1 ? 'altra designazione' : 'altre designazioni' }} nella stagione
        </span>
        @foreach ($load['conflicts'] as $c)
            <span class="ml-2 px-2 py-0.5 text-xs bg-red-100 text-red-800 rounded">
                ⚠️ Date sovrapposte: {{ $c['name'] }} ({{ $c['dates'] }}, {{ $c['role'] }})
            </span>
        @endforeach
    @else
        <span class="text-xs text-gray-400">Nessuna altra designazione nella stagione</span>
    @endif
</span>
