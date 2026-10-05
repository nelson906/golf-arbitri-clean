@extends('layouts.admin')

@section('page-title', 'Carica comitati FIG')

@section('content')
    <div class="max-w-5xl mx-auto space-y-6">

        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-1">Caricamento completo da federgolf.it</h2>
            <p class="text-sm text-gray-600 mb-4">
                Per ogni gara FIG dell'anno il sistema cerca il torneo corrispondente e carica il Comitato di Gara.
                Con <strong>Sostituisci</strong> attivo, le assegnazioni del torneo (anche quelle fatte a mano) vengono
                tolte e sostituite; un torneo in cui nessun nome FIG trova un arbitro non viene toccato.
                <strong>Prova</strong> mostra cosa cambierebbe senza scrivere niente.
            </p>

            <div class="flex flex-wrap items-end gap-6">
                <div>
                    <label for="fig-anno" class="block text-sm font-medium text-gray-700 mb-1">Anno</label>
                    <select id="fig-anno" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
                        @foreach ($anni as $anno)
                            <option value="{{ $anno }}">{{ $anno }}</option>
                        @endforeach
                    </select>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" id="fig-replace" checked class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    Sostituisci le assegnazioni dei tornei trovati
                </label>

                <div class="flex gap-3 ml-auto">
                    <button type="button" id="fig-prova"
                        class="px-5 py-2 rounded-md bg-gray-700 text-white text-sm font-medium hover:bg-gray-800 disabled:opacity-40">
                        Prova
                    </button>
                    <button type="button" id="fig-esegui"
                        class="px-5 py-2 rounded-md bg-red-600 text-white text-sm font-medium hover:bg-red-700 disabled:opacity-40">
                        Esegui
                    </button>
                </div>
            </div>
        </div>

        <div id="fig-progress-box" class="bg-white shadow rounded-lg p-6 hidden">
            <div class="flex justify-between text-sm text-gray-700 mb-2">
                <span id="fig-progress-label">Avvio…</span>
                <span id="fig-progress-count"></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div id="fig-progress-bar" class="bg-indigo-600 h-3 rounded-full" style="width: 0%"></div>
            </div>
            <p id="fig-error" class="mt-3 text-sm text-red-700 hidden"></p>
        </div>

        <div id="fig-summary" class="bg-white shadow rounded-lg p-6 hidden">
            <h3 class="text-md font-semibold text-gray-900 mb-3" id="fig-summary-title">Riepilogo</h3>
            <table class="text-sm w-full max-w-lg">
                <tbody id="fig-summary-rows" class="divide-y divide-gray-100"></tbody>
            </table>

            <div id="fig-gare-box" class="mt-6 hidden">
                <h4 class="text-sm font-semibold text-gray-800 mb-2">Gare FIG senza torneo locale</h4>
                <ul id="fig-gare-list" class="text-sm text-gray-700 list-disc list-inside space-y-1"></ul>
            </div>

            <div id="fig-nomi-box" class="mt-6 hidden">
                <h4 class="text-sm font-semibold text-gray-800 mb-2">Nomi FIG senza arbitro corrispondente (da sistemare a mano)</h4>
                <table class="text-sm w-full">
                    <thead class="text-left text-gray-500">
                        <tr><th class="pr-4 py-1">Nome FIG</th><th class="pr-4 py-1">Ruolo</th><th class="pr-4 py-1">Torneo</th><th class="py-1">Miglior candidato scartato</th></tr>
                    </thead>
                    <tbody id="fig-nomi-rows" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
        </div>

        <details id="fig-log-box" class="bg-white shadow rounded-lg p-6 hidden">
            <summary class="text-sm font-semibold text-gray-800 cursor-pointer">Registro dettagliato</summary>
            <pre id="fig-log" class="mt-3 text-xs text-gray-700 whitespace-pre-wrap max-h-[32rem] overflow-y-auto"></pre>
        </details>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const url = @json(route('super-admin.fig-import.block'));
    const token = document.querySelector('meta[name=csrf-token]').content;
    const $ = (id) => document.getElementById(id);
    const buttons = [$('fig-prova'), $('fig-esegui')];

    const counters = [
        ['gare_elaborate', 'Gare FIG elaborate'],
        ['gare_senza_torneo', 'Gare FIG senza torneo locale'],
        ['gare_senza_comitato', 'Gare FIG senza comitato pubblicato'],
        ['tolte', 'Assegnazioni precedenti tolte'],
        ['create', 'Assegnazioni caricate'],
        ['gia_presenti', 'Assegnazioni già presenti (lasciate)'],
        ['nomi_senza_match', 'Nomi FIG senza arbitro'],
    ];

    function text(tag, value) {
        const el = document.createElement(tag);
        el.textContent = value;
        return el;
    }

    function render(totals, dryRun) {
        $('fig-summary').classList.remove('hidden');
        $('fig-summary-title').textContent = dryRun ? 'Riepilogo della prova (niente è stato scritto)' : 'Riepilogo';

        const rows = $('fig-summary-rows');
        rows.replaceChildren();
        counters.forEach(([key, label]) => {
            const tr = document.createElement('tr');
            tr.append(text('td', label), text('td', String(totals[key])));
            tr.children[0].className = 'py-1 pr-6 text-gray-600';
            tr.children[1].className = 'py-1 font-semibold text-gray-900';
            rows.append(tr);
        });

        const gare = $('fig-gare-list');
        gare.replaceChildren(...totals.elenco_gare_senza_torneo.map((g) => text('li', `${g.nome} (${g.data}) — ${g.club}`)));
        $('fig-gare-box').classList.toggle('hidden', totals.elenco_gare_senza_torneo.length === 0);

        const nomi = $('fig-nomi-rows');
        nomi.replaceChildren(...totals.elenco_nomi_da_creare.map((n) => {
            const tr = document.createElement('tr');
            tr.append(text('td', n.nome_fig), text('td', n.ruolo), text('td', n.torneo), text('td', n.candidato));
            [...tr.children].forEach((td) => { td.className = 'py-1 pr-4'; });
            return tr;
        }));
        $('fig-nomi-box').classList.toggle('hidden', totals.elenco_nomi_da_creare.length === 0);
    }

    async function start(dryRun) {
        const anno = parseInt($('fig-anno').value, 10);
        const replace = $('fig-replace').checked;

        if (!dryRun) {
            const msg = replace
                ? `Caricamento ${anno} da FIG: le assegnazioni dei tornei trovati verranno TOLTE e sostituite dal comitato FIG. Procedere?`
                : `Caricamento ${anno} da FIG: verranno aggiunte le assegnazioni mancanti. Procedere?`;
            if (!window.confirm(msg)) return;
        }

        const run = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Date.now()) + '-' + Math.random().toString(36).slice(2, 10);
        const totals = {
            gare_elaborate: 0, gare_senza_torneo: 0, gare_senza_comitato: 0, create: 0,
            gia_presenti: 0, tolte: 0, nomi_senza_match: 0,
            elenco_gare_senza_torneo: [], elenco_nomi_da_creare: [],
        };

        buttons.forEach((b) => { b.disabled = true; });
        $('fig-progress-box').classList.remove('hidden');
        $('fig-error').classList.add('hidden');
        $('fig-summary').classList.add('hidden');
        $('fig-log-box').classList.remove('hidden');
        $('fig-log').textContent = '';
        $('fig-progress-bar').style.width = '0%';
        $('fig-progress-label').textContent = dryRun ? 'Prova in corso…' : 'Caricamento in corso…';

        let offset = 0;
        try {
            for (;;) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ anno, offset, replace, dry_run: dryRun, run }),
                });
                const data = await res.json();
                if (data.log) $('fig-log').textContent += data.log;
                if (!res.ok || !data.success) throw new Error(data.message || ('Errore HTTP ' + res.status));

                const s = data.stats;
                counters.forEach(([key]) => { totals[key] += s[key]; });
                totals.elenco_gare_senza_torneo.push(...s.elenco_gare_senza_torneo);
                totals.elenco_nomi_da_creare.push(...s.elenco_nomi_da_creare);

                const done = Math.min(data.next_offset, s.gare_disponibili);
                const pct = s.gare_disponibili > 0 ? Math.round(done / s.gare_disponibili * 100) : 100;
                $('fig-progress-bar').style.width = pct + '%';
                $('fig-progress-count').textContent = `${done} / ${s.gare_disponibili} gare`;

                if (data.done) break;
                offset = data.next_offset;
            }
            $('fig-progress-label').textContent = dryRun ? 'Prova completata' : 'Caricamento completato';
        } catch (e) {
            $('fig-progress-label').textContent = 'Interrotto';
            $('fig-error').textContent = e.message + ' — i blocchi già elaborati restano; puoi rilanciare.';
            $('fig-error').classList.remove('hidden');
        } finally {
            render(totals, dryRun);
            buttons.forEach((b) => { b.disabled = false; });
        }
    }

    $('fig-prova').addEventListener('click', () => start(true));
    $('fig-esegui').addEventListener('click', () => start(false));
})();
</script>
@endpush
