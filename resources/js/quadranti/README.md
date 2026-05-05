# Modulo Quadranti — Simulatore Tempi di Partenza

Questo modulo calcola lo schema delle partenze (tee times) di una gara di golf su 36 / 54 buche, in singolo tee oppure in doppio tee (1 e 10), seguendo la logica tecnica federale dei "quadranti".

L'obiettivo del README è documentare **la logica di distribuzione effettivamente implementata** così che possa essere verificata punto per punto prima di introdurre nuove funzioni (es. la cancellazione di un nominativo dopo la chiusura iscrizioni).

---

## 1. Struttura dei file

```
resources/js/quadranti/
├── config.js            # default + costanti (limiti tecnici, colori, opzioni tempo)
├── utils.js             # helper: range, addTime, halfTime, storage(localStorage), formatDate, debounce
├── quadranti-logic.js   # logica pura: bilanciamento, limiti, gruppi, generazione tabelle
├── quadranti.js         # bootstrap UI: legge il form, persiste su localStorage, ridisegna la tabella
└── README.md
```

Backend coinvolto:

- `app/Http/Controllers/User/QuadrantiController.php` — endpoint AJAX `coordinates`, `upload-excel`
- `routes/user/quadranti.php` — definisce le route `/user/quadranti/*`
- `resources/views/user/quadranti/index.blade.php` — form di configurazione + tabella `#first_table`
- Integrazione Federgolf: `/user/federgolf/load-all` e `/user/federgolf/iscritti` (vedi `quadranti.js` → `handleLoadFedergolfGare` / `handleFedergolfGaraSelected`).

---

## 2. Modello dati in input

Tre fonti di nominativi, in ordine di precedenza dentro `getPlayerArrays()`:

1. **Modalità "Nominativo On"** — array `atleti` (uomini) e `atlete` (donne) salvati in `localStorage`. Vengono valorizzati da:
   - upload file Excel via `handleFileUpload` → `/user/quadranti/upload-excel`
   - import iscritti Federgolf via `handleFedergolfGaraSelected` → `/user/federgolf/iscritti`
2. **Modalità "Numerico"** — `atleti` e `atlete` rigenerati come `range(1..N)` (semplici numeri d'ordine).

Chiavi `localStorage` usate: `atleti`, `atlete`, `storedPlayersCount`, `storedProetteCount`, `nominativo`, più tutte le chiavi di `DEFAULT_CONFIG` (players, proette, playersPerFlight, garaNT, doppiePartenze, compatto, round, startTime, gap, giornata, geoArea).

---

## 3. Logica di distribuzione — i quattro quadranti

L'idea della distribuzione è dividere il campo in **quattro quadranti** Q1..Q4 e mappare ciascun quadrante su un quarto della tabella oraria/spaziale:

```
                     Tee 1               Tee 10
   Early (mattina)   Q1 ──► Tee1 Early   Q2 ──► Tee10 Early
   ─────────── (incrocio = round/2) ───────────
   Late  (incrocio)  Q3 ──► Tee1 Late    Q4 ──► Tee10 Late
```

Le seguenti spiegazioni si riferiscono al **giorno 1**. Il giorno 2 viene ruotato (sezione 5).

### 3.1 `bilanciaQuadranti(players, mod, maxEarlySlots)`

Determina **quanti flight** vanno in ciascun quadrante.

1. `totalMatches = ceil(players / mod)` — numero di flight totali (mod = giocatori per flight, 3 o 4).
2. Distribuzione equa: ogni Q parte da `floor(totalMatches/4)`.
3. Distribuzione del resto (modulo 4), in ordine **Early-first**:
   - resto ≥ 1 → `Q1++`
   - resto ≥ 2 → `Q2++`
   - resto ≥ 3 → `Q4++`
4. **Constraint orari Early**: se `maxEarlySlots !== null` e `Q1 > maxEarlySlots`, l'eccedenza scende sul Late. Le righe Early sono coppie Q1+Q2 sullo stesso orario, quindi se Q1 perde *k* righe le perde anche Q2, e Q3 / Q4 le acquistano.
5. **Regola "coda corta"** (compensa flight incompleti):
   - mod = 3 e `players % 12 ∈ {1,2,3}` → sposta 1 flight da Q3 a Q2.
   - mod = 4 e `players % 16 ∈ {1,2,3,4}` → idem.

### 3.2 `limitiQuadranti(players, mod, maxEarlySlots)`

Trasforma il conteggio dei flight in **finestre di indici** sull'array dei giocatori, ordinati 1..N (in modalità Nominativo l'ordine è quello del file/iscrizioni; in modalità Numerico è l'ordine naturale).

```
playersQx = mod * Qx       (capacità nominale)
fullPlayers = (Q1+Q2+Q3+Q4) * mod
difference  = fullPlayers - players      // 0..3
playersQ1   = playersQ1 - difference     // i posti vuoti vengono "scaricati" su Q1

limit1 = playersQ2                       // fine Q2
limit2 = playersQ2 + playersQ1           // fine Q1
limit3 = players - playersQ3             // inizio Q3
```

L'array di giocatori viene quindi sezionato così:

| Sezione | Indici | Ordine letto da `generatePlayerGroups` | Quadrante |
|---|---|---|---|
| `[0, limit1)` | giocatori 1..limit1 | **decrescente** (dai più alti ai più bassi) | Q2 (Early, Tee10) |
| `[limit1, limit2)` | giocatori limit1+1..limit2 | crescente, gestendo la `difference` | Q1 (Early, Tee1) |
| `[limit2, limit3)` | giocatori limit2+1..limit3 | crescente | Q4 (Late, Tee10) |
| `[limit3, players)` | ultimi giocatori | **decrescente**, troncato se difference=3 | Q3 (Late, Tee1) |

> Nota: i numeri "alti" (es. tester di pari classifica) finiscono in Q2 e Q3, quelli "bassi" in Q1 e Q4. È così per disegno: la coppia Q2/Q3 raccoglie le code dell'ordine, la coppia Q1/Q4 raccoglie le teste.

### 3.3 Gestione dei flight incompleti (`difference`)

`difference = fullPlayers - players` può valere 0, 1, 2, 3. Indica quanti posti restano scoperti. La logica scelta:

| difference | Comportamento |
|---|---|
| 0 | Tutti i flight pieni. |
| 1 | **Primo flight di Q1** ha 2 giocatori invece di `mod`. |
| 2 | **Primi due flight di Q1** hanno 2 giocatori invece di `mod`. |
| 3 (mod=3) | Q1 resta pieno; viene tagliato l'**ultimo flight di Q3** (caso speciale). |

Questo è ciò che fa `generatePlayerGroups()` nel ramo `q1Groups` e nel taglio di `q3Players`.

### 3.4 Bilanciamento Uomini ↔ Donne (doppio tee)

Implementato in `generateDoubleTee()`:

1. Si calcolano **prima le donne** senza vincoli: `femaleGroups = generatePlayerGroups(proette, mod, atlete, 'F')`.
2. Si misura `femaleEarlySlots = ceil(femaleEarlyFlights / 2)` (un "orario" è una riga della tabella, ovvero una coppia Tee1+Tee10).
3. Si calcola la metà ideale degli orari: `targetEarlySlots = ceil(totalOrari / 2)`. Il `ceil` fa sì che il "±1" di squilibrio finisca su Early il giorno 1 (e su Late il giorno 2 grazie al remap).
4. Per gli uomini si passa il vincolo `maleMaxEarlySlots = max(0, targetEarlySlots - femaleEarlySlots)`, che attiva il ramo "constraint Early" di `bilanciaQuadranti`.

In pratica, le donne occupano sempre il numero "naturale" di slot Early; gli uomini si aggiustano sopra di esse fino a riempire la mattina senza superarla.

---

## 4. Sequenza temporale (giorno 1)

`generate54HoleTableNew` / `generate36HoleTableNew` con `dayNumber = 1`:

```
1) Early Male:    Q1 → Tee1, Q2 → Tee10, partono da startTime con passo gap
2) Early Female:  Q1 → Tee1, Q2 → Tee10, in coda agli uomini, stesso gap
3) Riga vuota + attesa "incrocio":
     - compatto = 'Early/Late'    → +halfTime(round)   (es. round 04:30 → +02:15)
     - compatto = 'Early(<14)'    → +00:10             (gap minimo, no incrocio)
4) Late Female:   Q3 → Tee1, Q4 → Tee10
5) Late Male:     Q3 → Tee1, Q4 → Tee10
```

Il box riepilogativo in cima alla tabella (`infoHTML`) mostra:

- **Ultima Partenza Early** = `startTime + (numero orari Early) * gap`
- **Prima Partenza Late** = ultima Early + incrocio (regola sopra)
- **Fine Gara Stimata** = `startTime + totalFlights * gap + round`

---

## 5. Giorno 2 (`dayNumber = 2`) — remap

`remapQuadrant`:

```
Q1 ↔ Q4    (Early Tee1   ↔ Late Tee10)
Q2 ↔ Q3    (Early Tee10  ↔ Late Tee1)
```

Operativamente, il giorno 2 le partenze sono nell'ordine:

```
1) Late Male  → Q4 a Tee1, Q3 a Tee10  (ora "Early")
2) Late Female → Q4 a Tee1, Q3 a Tee10
3) Incrocio
4) Early Female → Q2 a Tee1, Q1 a Tee10  (ora "Late")
5) Early Male   → Q2 a Tee1, Q1 a Tee10
```

In questo modo chi ha giocato Late il giorno 1 gioca Early il giorno 2 (e viceversa), preservando le coppie di compagni di flight.

---

## 6. Tee unico

`generateSingleTee()` ignora il bilanciamento Early/Late e concatena:

- 1ª giornata: `[...femaleGroups, ...maleGroups]`
- 2ª giornata: `[...maleGroups.reverse(), ...femaleGroups.reverse()]`

I gruppi sono comunque calcolati con `generatePlayerGroups`, quindi mantengono l'ordine letto dai quattro quadranti, ma vengono stampati su un'unica colonna.

---

## 7. Limiti tecnici (`config.js → TECHNICAL_LIMITS`)

```
maxMenDoubleTee       = 36   // soglia "tipica" per uomini in doppio tee
maxWomenDoubleTee     = 18
maxSingleTeeRecommended = 93
minSingleTeeMandatory   = 78
```

Oggi questi limiti sono solo informativi: non bloccano la generazione, servono come riferimento documentale.

L'unico limite UI applicato è `toggleCompactOption()`: l'opzione "Modalità compatto" viene mostrata solo se `players + proette ≤ mod * 32`.

---

## 8. Persistenza

Tutto su `localStorage` tramite `utils.js → storage`:

- chiavi `players`, `proette`, `playersPerFlight`, `garaNT`, `doppiePartenze`, `compatto`, `round`, `startTime`, `gap`, `giornata`, `geoArea`, `start`, `nominativo`, `display1`, `display2`
- chiavi nominativi: `atleti`, `atlete`, `storedPlayersCount`, `storedProetteCount`

Non c'è (oggi) persistenza lato server dello schema generato: ogni reload ricostruisce la tabella da configurazione + array di nominativi.

---

## 9. Endpoint usati

| URL | Metodo | Chi lo chiama | Scopo |
|---|---|---|---|
| `/user/quadranti/coordinates` | POST | `fetchEphemerisData` | alba/tramonto per zona geografica |
| `/user/quadranti/upload-excel` | POST | `handleFileUpload` | parsing Excel iscritti |
| `/user/federgolf/load-all` | POST | `handleLoadFedergolfGare` | elenco gare aperte su federgolf.it |
| `/user/federgolf/iscritti` | POST | `handleFedergolfGaraSelected` | iscritti ammessi (richiede iscrizioni chiuse) |

Il flag `iscrizioni_aperte` ritornato da `/user/federgolf/iscritti` interrompe l'import: significa che la gara è ancora aperta e il sistema non ha ancora una lista ammessi definitiva.

---

## 10. Test

Esiste `resources/js/quadranti/quadranti-logic.test.js` (Vitest, vedi `vitest.config.js`) per la logica di bilanciamento e generazione gruppi.

---

## Glossario rapido

- **Flight / match**: gruppo di 2-4 giocatori che parte insieme.
- **Orario / slot**: una riga della tabella, corrispondente a una coppia Tee1+Tee10 nel doppio tee.
- **Quadrante (Q1..Q4)**: una delle quattro fasce della giornata (Early-Tee1, Early-Tee10, Late-Tee1, Late-Tee10).
- **Incrocio (cross)**: tempo di attesa fra fine Early e inizio Late, pari a metà del `round` in modalità Early/Late.
- **`difference`**: posti scoperti perché `players` non è multiplo di `mod`.
- **`compatto`**: scelta tra "Early/Late" (con incrocio) ed "Early(<14)" (gara compatta senza incrocio significativo).
