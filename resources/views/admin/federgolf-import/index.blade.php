{{-- ============================================================
     Wizard: Import Comitato di Gara da federgolf.it
     Route: admin.federgolf-import.index
     Nessuna scrittura fino alla conferma esplicita dell'admin.
     ============================================================ --}}
@extends('layouts.admin')

@section('title', 'Import Comitato FIG')

@section('content')
<div
    class="py-6 max-w-5xl mx-auto px-4"
    x-data="figImport()"
    x-init="init()"
>

    {{-- ── INTESTAZIONE ──────────────────────────────────────────────── --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">🏌️ Import Comitato da federgolf.it</h1>
            <p class="text-sm text-gray-500 mt-1">
                Strumento saltuario · nessuna modifica finché non confermi esplicitamente
            </p>
        </div>
        <a href="{{ route('admin.assignments.index') }}"
           class="text-sm text-gray-500 hover:text-gray-800 underline">
            ← Assegnazioni
        </a>
    </div>

    {{-- ── BARRA PROGRESSIONE STEP ────────────────────────────────────── --}}
    <div class="flex items-center mb-8 space-x-2 text-sm">
        <template x-for="(label, i) in steps" :key="i">
            <div class="flex items-center">
                <div class="flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold transition-colors"
                     :class="step > i+1 ? 'bg-green-500 text-white' :
                             step === i+1 ? 'bg-blue-600 text-white' :
                             'bg-gray-200 text-gray-500'">
                    <template x-if="step > i+1">✓</template>
                    <template x-if="step <= i+1"><span x-text="i+1"></span></template>
                </div>
                <span class="ml-1 hidden sm:inline"
                      :class="step === i+1 ? 'font-semibold text-blue-700' : 'text-gray-400'"
                      x-text="label"></span>
                <div x-show="i < steps.length - 1" class="w-8 h-px bg-gray-300 mx-2"></div>
            </div>
        </template>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         STEP 1 — Seleziona gara FIG
    ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="step === 1" x-cloak>
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                1 — Seleziona la gara su federgolf.it
            </h2>

            {{-- Carica lista --}}
            <div class="flex flex-wrap items-center gap-3 mb-5">
                {{-- Selettore anno FIG --}}
                <select x-model="anno"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none">
                    <template x-for="y in anniDisponibili" :key="y">
                        <option :value="y" x-text="y"></option>
                    </template>
                </select>

                <button
                    @click="loadFigList()"
                    :disabled="loadingFig"
                    class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                    <svg x-show="loadingFig" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    <span x-text="loadingFig ? 'Caricamento…' : '📡 Carica gare ' + anno + ' da FIG'"></span>
                </button>

                <input
                    x-show="figList.length > 0"
                    x-model="searchFig"
                    type="text"
                    placeholder="Filtra per nome o circolo…"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64 focus:ring-2 focus:ring-blue-300 outline-none">
            </div>

            {{-- Errore --}}
            <div x-show="figError" class="text-red-600 text-sm mb-4 p-3 bg-red-50 rounded-lg" x-text="figError"></div>

            {{-- Lista gare --}}
            <div x-show="figList.length > 0" class="border rounded-lg overflow-hidden">
                <div class="max-h-96 overflow-y-auto divide-y divide-gray-100">
                    <template x-for="gara in filteredFigList" :key="gara.id">
                        <div
                            @click="selectFigGara(gara)"
                            class="px-4 py-3 cursor-pointer flex items-center justify-between hover:bg-blue-50 transition-colors"
                            :class="selectedFig?.id === gara.id ? 'bg-blue-100 border-l-4 border-blue-600' : ''">
                            <div>
                                <p class="font-medium text-gray-800 text-sm" x-text="gara.nome"></p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    <span x-text="gara.club ?? '—'"></span>
                                    <span class="mx-1">·</span>
                                    <span x-text="gara.data ?? '—'"></span>
                                    <span class="ml-2 px-1.5 py-0.5 rounded text-xs font-medium"
                                          :class="gara.tipo==='M' ? 'bg-blue-100 text-blue-700' :
                                                  gara.tipo==='F' ? 'bg-pink-100 text-pink-700' :
                                                  'bg-gray-100 text-gray-600'"
                                          x-text="gara.tipo"></span>
                                </p>
                            </div>
                            <svg x-show="selectedFig?.id === gara.id" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                    </template>
                    <div x-show="filteredFigList.length === 0 && searchFig" class="px-4 py-6 text-center text-gray-400 text-sm">
                        Nessuna gara corrisponde alla ricerca.
                    </div>
                </div>
                <div class="px-4 py-2 bg-gray-50 text-xs text-gray-400 border-t">
                    <span x-text="filteredFigList.length"></span> gare mostrate
                </div>
            </div>

            {{-- Bottone avanti --}}
            <div class="mt-5 flex justify-end">
                <button
                    @click="fetchCommittee()"
                    :disabled="!selectedFig || loadingComitato"
                    class="bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed text-white px-6 py-2 rounded-lg font-medium text-sm transition-colors flex items-center gap-2">
                    <svg x-show="loadingComitato" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    <span x-text="loadingComitato ? 'Recupero comitato…' : 'Carica Comitato di Gara →'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         STEP 2 — Revisione match + selezione torneo locale
    ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="step === 2" x-cloak>
        <div class="bg-white rounded-xl shadow p-6 mb-4">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">
                        2 — Revisione corrispondenze
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Gara FIG: <strong x-text="selectedFig?.nome"></strong>
                        <span class="mx-1">·</span>
                        <span x-text="selectedFig?.data"></span>
                        <span class="mx-1">·</span>
                        <span x-text="selectedFig?.club ?? '—'"></span>
                    </p>
                </div>
                <button @click="step=1" class="text-xs text-gray-400 hover:text-gray-700 underline">← Cambia gara</button>
            </div>
        </div>

        {{-- Errore fetchCommittee --}}
        <div x-show="committatoError" class="text-red-600 text-sm mb-4 p-4 bg-red-50 rounded-xl border border-red-200" x-text="committatoError"></div>

        {{-- Tabella corrispondenze --}}
        <div class="bg-white rounded-xl shadow overflow-hidden mb-4">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-1/4">Nome FIG</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-1/5">Ruolo FIG</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-1/4">Arbitro locale</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-1/5">Ruolo da assegnare</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-10">✓</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="(row, i) in comitato" :key="i">
                        <tr :class="row.includi ? 'bg-white' : 'bg-gray-50 opacity-60'">

                            {{-- Nome FIG --}}
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800" x-text="row.fig.nome_completo"></p>
                            </td>

                            {{-- Ruolo FIG --}}
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-1 rounded-full"
                                      :class="row.fig.ruolo_normalizzato === 'Direttore di Torneo'
                                              ? 'bg-purple-100 text-purple-700'
                                              : row.fig.ruolo_normalizzato === 'Osservatore'
                                              ? 'bg-yellow-100 text-yellow-700'
                                              : 'bg-blue-100 text-blue-700'"
                                      x-text="row.fig.ruolo"></span>
                            </td>

                            {{-- Arbitro locale (select con candidati) --}}
                            <td class="px-4 py-3">
                                <div class="space-y-1">
                                    {{-- Badge confidenza match --}}
                                    <div x-show="row.match" class="flex items-center gap-1 mb-1">
                                        <span class="text-xs px-1.5 py-0.5 rounded font-medium"
                                              :class="row.match?.score >= 85 ? 'bg-green-100 text-green-700' :
                                                      row.match?.score >= 60 ? 'bg-yellow-100 text-yellow-700' :
                                                      'bg-red-100 text-red-700'"
                                              x-text="row.match?.score + '%'"></span>
                                        <span class="text-xs text-gray-400">confidenza</span>
                                    </div>

                                    <select
                                        x-model="row.user_id_selezionato"
                                        class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-300 outline-none"
                                        :class="!row.user_id_selezionato ? 'border-orange-300 bg-orange-50' : 'border-gray-300'">
                                        <option value="">— non assegnare —</option>
                                        {{-- Candidati dal matching (ordinati per score) --}}
                                        <template x-if="row.candidati && row.candidati.length">
                                            <optgroup label="Corrispondenze suggerite">
                                                <template x-for="c in row.candidati" :key="c.user_id">
                                                    <option :value="c.user_id" x-text="c.name + ' (' + c.score + '%)'"></option>
                                                </template>
                                            </optgroup>
                                        </template>
                                        {{-- Tutti gli altri arbitri --}}
                                        <optgroup label="Tutti gli arbitri">
                                            @foreach($arbitriLocali as $arb)
                                            <option value="{{ $arb->id }}">{{ $arb->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    </select>
                                </div>
                            </td>

                            {{-- Ruolo da assegnare (editabile) --}}
                            <td class="px-4 py-3">
                                <select
                                    x-model="row.ruolo_selezionato"
                                    class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-300 outline-none">
                                    @foreach($ruoli as $ruolo)
                                    <option value="{{ $ruolo->value }}">{{ $ruolo->value }}</option>
                                    @endforeach
                                </select>
                            </td>

                            {{-- Includi/escludi --}}
                            <td class="px-4 py-3 text-center">
                                <input
                                    type="checkbox"
                                    x-model="row.includi"
                                    class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-400 cursor-pointer">
                            </td>
                        </tr>
                    </template>

                    <tr x-show="comitato.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">
                            Nessun membro del comitato trovato per questa gara.
                        </td>
                    </tr>
                </tbody>
            </table>

            {{-- Footer tabella: riepilogo --}}
            <div class="px-4 py-3 bg-gray-50 border-t flex items-center gap-4 text-sm text-gray-500">
                <span><strong x-text="comitato.filter(r=>r.includi && r.user_id_selezionato).length"></strong> da importare</span>
                <span>·</span>
                <span><strong x-text="comitato.filter(r=>!r.includi || !r.user_id_selezionato).length"></strong> escluse</span>
                <span>·</span>
                <span>Match ≥85%: <strong x-text="comitato.filter(r=>r.match?.score>=85).length" class="text-green-600"></strong></span>
                <span>·</span>
                <span>Match incerto: <strong x-text="comitato.filter(r=>r.match && r.match.score<85 && r.match.score>=60).length" class="text-yellow-600"></strong></span>
                <span>·</span>
                <span>Nessun match: <strong x-text="comitato.filter(r=>!r.match).length" class="text-red-500"></strong></span>
            </div>
        </div>

        {{-- Selezione torneo locale --}}
        <div class="bg-white rounded-xl shadow p-6 mb-4">
            <h3 class="font-semibold text-gray-800 mb-1">Torneo locale di destinazione</h3>
            <p class="text-sm text-gray-500 mb-3">
                Seleziona il torneo del sistema a cui agganciare queste assegnazioni.
                I tornei con assegnazioni già presenti sono segnalati.
            </p>

            {{-- Filtro anno tornei locali --}}
            <div class="flex items-center gap-3 mb-3">
                <label class="text-xs text-gray-500">Filtra anno:</label>
                <div class="flex gap-1">
                    <button @click="filtroAnnoLocale = ''"
                            :class="filtroAnnoLocale === '' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-3 py-1 rounded text-xs font-medium transition-colors">Tutti</button>
                    <template x-for="y in anniTorneiLocali" :key="y">
                        <button @click="filtroAnnoLocale = y"
                                :class="filtroAnnoLocale === y ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                class="px-3 py-1 rounded text-xs font-medium transition-colors"
                                x-text="y"></button>
                    </template>
                </div>
            </div>

            <select
                x-model="torneoLocaleId"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none"
                :class="!torneoLocaleId ? 'border-orange-300' : ''">
                <option value="">— scegli il torneo locale —</option>
                <template x-for="t in torneiLocaliFiltrati" :key="t.id">
                    <option :value="t.id"
                            x-text="t.label + (t.n_assegnazioni > 0 ? ' [' + t.n_assegnazioni + ' assegnaz.]' : '')">
                    </option>
                </template>
            </select>

            {{-- Avviso torneo già assegnato --}}
            <div x-show="torneoLocaleSelezionato?.n_assegnazioni > 0"
                 class="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2">
                ⚠️ Questo torneo ha già <strong x-text="torneoLocaleSelezionato?.n_assegnazioni"></strong> assegnazioni.
                Le nuove verranno aggiunte solo se non già presenti (nessuna sovrascrittura).
            </div>
            <p x-show="!torneoLocaleId" class="text-xs text-orange-600 mt-1">
                ⚠️ Devi selezionare un torneo prima di poter importare.
            </p>
        </div>

        {{-- Avviso chiarezza --}}
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5 text-sm text-amber-800">
            <strong>Nota:</strong> le righe con spunta ✓ e arbitro selezionato verranno importate come assegnazioni.
            Le assegnazioni già esistenti su quel torneo non verranno sovrascritte (saltate).
        </div>

        {{-- Bottoni navigazione --}}
        <div class="flex justify-between">
            <button @click="step=1" class="text-gray-500 hover:text-gray-800 text-sm underline">← Indietro</button>
            <button
                @click="goToConfirm()"
                :disabled="!canConfirm()"
                class="bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed text-white px-6 py-2 rounded-lg font-medium text-sm transition-colors">
                Vai alla conferma →
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         STEP 3 — Riepilogo e conferma
    ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="step === 3" x-cloak>
        <div class="bg-white rounded-xl shadow p-6 mb-4">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                3 — Conferma importazione
            </h2>

            <div class="bg-gray-50 rounded-lg p-4 mb-5 text-sm">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <span class="text-gray-500">Gara FIG:</span>
                        <span class="ml-2 font-medium" x-text="selectedFig?.nome"></span>
                    </div>
                    <div>
                        <span class="text-gray-500">Torneo locale:</span>
                        <span class="ml-2 font-medium" x-text="torneoLocaleLabel"></span>
                    </div>
                    <div>
                        <span class="text-gray-500">Assegnazioni da creare:</span>
                        <span class="ml-2 font-bold text-blue-700" x-text="righeValide.length"></span>
                    </div>
                </div>
            </div>

            {{-- Riepilogo righe valide --}}
            <div class="border rounded-lg overflow-hidden mb-5">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Arbitro locale</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Ruolo</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Origine FIG</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(riga, i) in righeValide" :key="i">
                            <tr>
                                <td class="px-4 py-2 font-medium" x-text="nomeArbitroById(riga.user_id)"></td>
                                <td class="px-4 py-2">
                                    <span class="text-xs px-2 py-1 rounded-full"
                                          :class="riga.ruolo === 'Direttore di Torneo' ? 'bg-purple-100 text-purple-700' :
                                                  riga.ruolo === 'Osservatore' ? 'bg-yellow-100 text-yellow-700' :
                                                  'bg-blue-100 text-blue-700'"
                                          x-text="riga.ruolo"></span>
                                </td>
                                <td class="px-4 py-2 text-gray-500 text-xs" x-text="riga.nome_fig"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Risultato importazione --}}
            <div x-show="importResult" class="mb-5 space-y-3">

                {{-- Messaggio principale --}}
                <div class="p-4 rounded-xl text-sm"
                     :class="importResult?.creati > 0
                             ? 'bg-green-50 border border-green-200 text-green-800'
                             : importResult?.saltati > 0
                             ? 'bg-blue-50 border border-blue-200 text-blue-800'
                             : 'bg-yellow-50 border border-yellow-200 text-yellow-800'">
                    <p class="font-medium" x-text="importResult?.messaggio"></p>
                    <template x-if="importResult?.errori?.length">
                        <ul class="mt-2 list-disc list-inside text-xs">
                            <template x-for="err in importResult.errori" :key="err">
                                <li x-text="err"></li>
                            </template>
                        </ul>
                    </template>
                    <div x-show="importResult?.creati > 0" class="mt-3">
                        <a :href="'/admin/assignments?tournament_id=' + torneoLocaleId"
                           class="text-green-700 underline font-medium text-sm">
                            → Vai alle assegnazioni del torneo
                        </a>
                    </div>
                </div>

                {{-- Diagnostica DB (sempre visibile dopo il result) --}}
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-xs text-gray-600 font-mono">
                    <p class="font-semibold text-gray-700 mb-2 font-sans text-sm">🔍 Diagnostica connessione</p>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 mb-2">
                        <span class="text-gray-400">Database:</span>
                        <span x-text="importResult?.debug?.database ?? '—'"></span>
                        <span class="text-gray-400">Torneo (ID <span x-text="importResult?.debug?.tournament_id"></span>):</span>
                        <span x-text="importResult?.debug?.tournament_nome ?? '—'"
                              :class="importResult?.debug?.tournament_nome?.startsWith('⚠️') ? 'text-red-600 font-bold' : ''"></span>
                    </div>

                    {{-- Dettaglio assegnazioni già presenti --}}
                    <template x-if="importResult?.debug?.assegnazioni_gia_presenti?.length">
                        <div class="mt-2">
                            <p class="text-gray-500 mb-1">Assegnazioni già presenti nel DB:</p>
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-gray-400">
                                        <th class="text-left pr-3">User ID</th>
                                        <th class="text-left pr-3">Assegn. ID</th>
                                        <th class="text-left pr-3">Ruolo attuale</th>
                                        <th class="text-left pr-3">Assegnato il</th>
                                        <th class="text-left">Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="d in importResult.debug.assegnazioni_gia_presenti" :key="d.assignment_id">
                                        <tr class="border-t border-gray-100">
                                            <td class="pr-3 py-0.5" x-text="d.user_id"></td>
                                            <td class="pr-3 py-0.5" x-text="d.assignment_id"></td>
                                            <td class="pr-3 py-0.5" x-text="d.ruolo_attuale"></td>
                                            <td class="pr-3 py-0.5" x-text="d.assegnato_il ?? '—'"></td>
                                            <td class="py-0.5 text-gray-400" x-text="d.note ?? '—'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Bottoni --}}
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <button @click="step=2" :disabled="importing"
                            class="text-gray-500 hover:text-gray-800 text-sm underline disabled:opacity-40">
                        ← Rivedi
                    </button>
                    {{-- Reset completo: visibile solo dopo l'import --}}
                    <button x-show="importResult" @click="resetAll()"
                            class="text-blue-600 hover:text-blue-800 text-sm underline">
                        ↺ Nuova importazione
                    </button>
                </div>
                <button
                    @click="executeImport()"
                    :disabled="importing || !!importResult"
                    class="bg-green-600 hover:bg-green-700 disabled:opacity-40 disabled:cursor-not-allowed text-white px-8 py-2.5 rounded-lg font-semibold text-sm transition-colors flex items-center gap-2">
                    <svg x-show="importing" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    <span x-text="importing ? 'Importazione…' : '✅ Conferma e importa'"></span>
                </button>
            </div>
        </div>
    </div>

</div>

{{-- ── SCRIPT Alpine.js ────────────────────────────────────────────────── --}}
@push('scripts')
<script>
function figImport() {
    return {
        // ── stato ────────────────────────────────────────────────────
        step: 1,
        steps: ['Gara FIG', 'Revisione match', 'Conferma'],
        anno: new Date().getFullYear(),
        anniDisponibili: [new Date().getFullYear(), new Date().getFullYear() - 1],

        // Step 1
        figList: [],
        searchFig: '',
        selectedFig: null,
        loadingFig: false,
        figError: '',
        loadingComitato: false,

        // Step 2
        comitato: [],
        committatoError: '',
        torneoLocaleId: '',
        filtroAnnoLocale: '',  // inizializzato in init() sull'anno più recente disponibile

        // Dati passati da Blade
        arbitriLocali: @json($arbitriLocali),
        torneiLocali: @json($torneiLocali),

        // Step 3
        importing: false,
        importResult: null,

        // ── computed ─────────────────────────────────────────────────
        get filteredFigList() {
            if (!this.searchFig) return this.figList;
            const q = this.searchFig.toLowerCase();
            return this.figList.filter(g =>
                (g.nome || '').toLowerCase().includes(q) ||
                (g.club || '').toLowerCase().includes(q)
            );
        },

        get anniTorneiLocali() {
            // Anni disponibili crescenti (es. 2025, 2026)
            return [...new Set(this.torneiLocali.map(t => t.anno))].sort();
        },

        get torneiLocaliFiltrati() {
            if (!this.filtroAnnoLocale) return this.torneiLocali;
            return this.torneiLocali.filter(t => t.anno === this.filtroAnnoLocale);
        },

        get torneoLocaleSelezionato() {
            return this.torneiLocali.find(t => t.id == this.torneoLocaleId) ?? null;
        },

        get righeValide() {
            return this.comitato
                .filter(r => r.includi && r.user_id_selezionato)
                .map(r => ({
                    user_id:   parseInt(r.user_id_selezionato),
                    ruolo:     r.ruolo_selezionato,
                    nome_fig:  r.fig.nome_completo,
                }));
        },

        get torneoLocaleLabel() {
            const t = this.torneiLocali.find(t => t.id == this.torneoLocaleId);
            return t ? t.label : '—';
        },

        // ── metodi ───────────────────────────────────────────────────
        init() {
            // Imposta il filtro anno locale sull'anno più recente presente tra i tornei
            const anni = this.anniTorneiLocali;
            if (anni.length > 0) {
                this.filtroAnnoLocale = anni[anni.length - 1]; // il più recente
            }
        },

        async loadFigList() {
            this.loadingFig = true;
            this.figError   = '';
            this.figList    = [];
            this.selectedFig = null;
            try {
                const res = await fetch('{{ route("admin.federgolf-import.fig-competitions") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ anno: this.anno }),
                });
                const data = await res.json();
                if (data.success) {
                    this.figList = data.gare;
                } else {
                    this.figError = data.message || 'Errore nel caricamento.';
                }
            } catch (e) {
                this.figError = 'Errore di rete: ' + e.message;
            } finally {
                this.loadingFig = false;
            }
        },

        selectFigGara(gara) {
            this.selectedFig = gara;
        },

        async fetchCommittee() {
            if (!this.selectedFig) return;
            this.loadingComitato = true;
            this.committatoError = '';
            this.comitato = [];

            try {
                const res = await fetch('{{ route("admin.federgolf-import.fetch-committee") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ competition_id: this.selectedFig.id }),
                });
                const data = await res.json();

                if (data.success) {
                    this.comitato = data.comitato.map(row => ({
                        fig:                  row.fig,
                        match:                row.match,
                        candidati:            row.candidati || [],
                        user_id_selezionato:  row.match ? String(row.match.user_id) : '',
                        ruolo_selezionato:    row.fig.ruolo_normalizzato,
                        includi:              !!row.match,
                    }));
                    this.step = 2;
                } else {
                    this.committatoError = data.message;
                    this.step = 2; // Vai comunque allo step 2 per mostrare l'errore
                }
            } catch (e) {
                this.committatoError = 'Errore di rete: ' + e.message;
                this.step = 2;
            } finally {
                this.loadingComitato = false;
            }
        },

        canConfirm() {
            return this.torneoLocaleId &&
                   this.comitato.some(r => r.includi && r.user_id_selezionato);
        },

        goToConfirm() {
            if (!this.canConfirm()) return;
            this.importResult = null;
            this.step = 3;
        },

        nomeArbitroById(id) {
            const a = this.arbitriLocali.find(a => a.id == id);
            return a ? a.name : '(ID: ' + id + ')';
        },

        resetAll() {
            this.step            = 1;
            this.figList         = [];
            this.searchFig       = '';
            this.selectedFig     = null;
            this.figError        = '';
            this.comitato        = [];
            this.committatoError = '';
            this.torneoLocaleId  = '';
            this.importing       = false;
            this.importResult    = null;
        },

        async executeImport() {
            if (!this.righeValide.length || !this.torneoLocaleId) return;
            this.importing = true;

            try {
                const res = await fetch('{{ route("admin.federgolf-import.execute") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        tournament_id: parseInt(this.torneoLocaleId),
                        assegnazioni:  this.righeValide,
                    }),
                });
                this.importResult = await res.json();
            } catch (e) {
                this.importResult = {
                    success: false,
                    messaggio: 'Errore di rete: ' + e.message,
                    errori: [],
                };
            } finally {
                this.importing = false;
            }
        },
    };
}
</script>
@endpush

@endsection
