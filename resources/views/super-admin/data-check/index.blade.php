@extends('layouts.admin')

@section('title', 'Controllo dati')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900">Controllo dati</h1>
    <p class="mt-2 mb-6 text-sm text-gray-600">
        Anomalie nei dati del database in uso. La pagina non corregge niente: ogni riga porta al torneo o all'arbitro da sistemare.
        <span class="ml-2 inline-block px-2 rounded bg-red-100 text-red-800">errore</span>
        <span class="ml-1 inline-block px-2 rounded bg-amber-100 text-amber-800">da verificare</span>
    </p>

    <div id="controllo-risultati" data-url="{{ route('super-admin.data-check.index', ['parziale' => 1]) }}">
        <div class="bg-white shadow rounded-lg px-4 py-10 flex flex-col items-center gap-3 text-gray-600">
            <svg class="animate-spin h-10 w-10 text-blue-600" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <p class="text-sm">Controllo in corso: può richiedere qualche secondo…</p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const box = document.getElementById('controllo-risultati');
        if (!box) {
            return;
        }
        fetch(box.dataset.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                box.innerHTML = html;
            })
            .catch(function (error) {
                box.innerHTML = '<div class="bg-red-50 text-red-800 rounded-lg p-4 text-sm">'
                    + 'Il controllo non è riuscito (' + error.message + '). Ricarica la pagina per riprovare.</div>';
            });
    })();
</script>
@endpush
