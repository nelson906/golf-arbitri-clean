@extends('layouts.admin')

@section('content')
<div class="p-6">
    <div class="max-w-6xl mx-auto">
        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">📧 Invia Notifiche Torneo</h1>
        </div>

        {{-- Info Torneo --}}
        <div class="bg-white border-2 border-blue-500 rounded-lg shadow mb-6 overflow-hidden">
            <div class="bg-blue-500 text-white px-6 py-4">
                <h2 class="text-xl font-semibold mb-0">🏆 {{ $tournament->name }}</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <ul class="space-y-2">
                            <li class="flex items-start gap-2">
                                <span class="font-semibold text-gray-700">📅 Date:</span>
                                <span class="text-gray-900">{{ $tournament->start_date->format('d/m/Y') }} - {{ $tournament->end_date->format('d/m/Y') }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="font-semibold text-gray-700">🏌️ Circolo:</span>
                                <span class="text-gray-900">{{ $tournament->club->name ?? 'N/A' }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="font-semibold text-gray-700">🌍 Zona:</span>
                                <span class="text-gray-900">{{ $tournament->zone->name ?? 'N/A' }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="font-semibold text-gray-700">📊 Stato:</span>
                                <span class="text-gray-900">{{ $tournament->status }}</span>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">
                            ⚖️ Arbitri Assegnati ({{ $tournament->assignments->count() ?? 0 }})
                        </h3>
                        @if($tournament->assignments && $tournament->assignments->count() > 0)
                            <ul class="space-y-2">
                                @foreach($tournament->assignments as $assignment)
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                        <span class="text-gray-900">{{ $assignment->user->name }}</span>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                            {{ $assignment->role }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-yellow-600 bg-yellow-50 px-3 py-2 rounded-md">
                                Nessun arbitro assegnato
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Invio --}}
        <form method="POST" action="{{ route('admin.tournament-notifications.store', $tournament) }}">
            @csrf

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-medium text-gray-900">📧 Configurazione Invio</h3>
                </div>
                <div class="p-6">
                    {{-- Sistema Intelligente Alert --}}
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">
                                    Sistema Intelligente
                                </h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <p>
                                        Il sistema invierà automaticamente:
                                        <strong>1 email al circolo</strong> (con elenco arbitri),
                                        <strong>{{ $tournament->assignments->count() ?? 0 }} email agli arbitri</strong> (personalizzate),
                                        <strong>2 email istituzionali</strong> (CRC + Delegato)
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Messaggio Aggiuntivo --}}
                    <div class="mb-6">
                        <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                            💬 Messaggio Aggiuntivo (Opzionale)
                        </label>
                        <textarea name="message"
                                  id="message"
                                  rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('message') border-red-500 @enderror"
                                  placeholder="Messaggio personalizzato che verrà aggiunto a tutte le email...">{{ old('message') }}</textarea>
                        @error('message')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Riepilogo e Template --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Riepilogo Invio --}}
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-3">📊 Riepilogo Invio</h4>
                            <div class="bg-gray-50 rounded-lg border border-gray-200 divide-y divide-gray-200">
                                <div class="flex justify-between items-center px-4 py-3">
                                    <span class="text-gray-900">🏌️ Circolo</span>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        1 destinatario
                                    </span>
                                </div>
                                <div class="flex justify-between items-center px-4 py-3">
                                    <span class="text-gray-900">⚖️ Arbitri</span>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        {{ $tournament->assignments->count() ?? 0 }} destinatari
                                    </span>
                                </div>
                                <div class="flex justify-between items-center px-4 py-3">
                                    <span class="text-gray-900">🏛️ Istituzionali</span>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        2 destinatari
                                    </span>
                                </div>
                                <div class="flex justify-between items-center px-4 py-3 bg-blue-50">
                                    <span class="font-semibold text-gray-900">TOTALE</span>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ 1 + ($tournament->assignments->count() ?? 0) + 2 }} destinatari
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Template Utilizzati --}}
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-3">📄 Template Utilizzati</h4>
                            <ul class="space-y-3">
                                <li class="flex items-start gap-2">
                                    <span class="text-sm text-gray-600">🏌️</span>
                                    <div>
                                        <span class="font-medium text-gray-900">Circolo:</span>
                                        <span class="text-gray-600">Standard (con elenco arbitri)</span>
                                    </div>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="text-sm text-gray-600">⚖️</span>
                                    <div>
                                        <span class="font-medium text-gray-900">Arbitri:</span>
                                        <span class="text-gray-600">Convocazione formale</span>
                                    </div>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="text-sm text-gray-600">🏛️</span>
                                    <div>
                                        <span class="font-medium text-gray-900">Istituzionali:</span>
                                        <span class="text-gray-600">Report assegnazione</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="mt-6 flex items-center gap-4">
                <a href="{{ route('admin.tournament-notifications.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Annulla
                </a>

                <button type="submit"
                        class="inline-flex items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    Invia Notifiche Torneo
                </button>
            </div>
        </form>

        {{-- Warning Footer --}}
        <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.99-.833-2.764 0L3.932 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">
                        ⚠️ Attenzione
                    </h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>
                            Una volta inviate, le notifiche non possono essere annullate.
                            Verifica attentamente i destinatari prima di procedere.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Confirmation before sending
    const sendButton = document.querySelector('button[type="submit"]');
    const form = sendButton.closest('form');

    sendButton.addEventListener('click', function(e) {
        e.preventDefault();

        const totalRecipients = {{ 1 + ($tournament->assignments->count() ?? 0) + 2 }};

        if (confirm(`Sei sicuro di voler inviare le notifiche a ${totalRecipients} destinatari?\n\nQuesta azione non può essere annullata.`)) {
            form.submit();
        }
    });

    // Character counter for message textarea
    const messageTextarea = document.getElementById('message');
    if (messageTextarea) {
        const maxLength = 1000;

        // Create counter element
        const counter = document.createElement('div');
        counter.className = 'text-sm text-gray-500 mt-1';
        counter.innerHTML = `<span id="char-count">0</span>/${maxLength} caratteri`;
        messageTextarea.parentNode.appendChild(counter);

        const charCountSpan = document.getElementById('char-count');

        messageTextarea.addEventListener('input', function() {
            const currentLength = this.value.length;
            charCountSpan.textContent = currentLength;

            if (currentLength > maxLength * 0.9) {
                counter.className = 'text-sm text-yellow-600 mt-1';
            } else {
                counter.className = 'text-sm text-gray-500 mt-1';
            }

            if (currentLength > maxLength) {
                counter.className = 'text-sm text-red-600 mt-1';
                this.value = this.value.substring(0, maxLength);
                charCountSpan.textContent = maxLength;
            }
        });

        // Trigger initial count
        messageTextarea.dispatchEvent(new Event('input'));
    }
});
</script>
@endpush
