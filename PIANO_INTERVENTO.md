# Piano di Intervento — Golf Arbitri (Laravel 12)
*Revisione critica — 2026-04-07*
*Aggiornamento stato esecuzione — 2026-05-10*

---

## Stato di esecuzione (riepilogo veloce)

| # | Intervento | Stato | Note |
|---|-----------|-------|------|
| INT-01 | XSS flash session | ✅ Risolto | Audit del 2026-05-09 conferma: pattern già fixato (vedi `AUDIT_architect_review.md` in coda) |
| INT-02 | `AvailabilityRequest` (dead code) | ✅ Risolto | Eliminato il 2026-05-10 |
| INT-03 | N+1 `ClubController::export()` | 🟡 Aperto | Da fare |
| INT-04 | N+1 `AssignmentController::storeMultiple()` | 🟡 Aperto | Da fare |
| INT-05 | `Mail::raw()` → Mailable (era "rimosso") | ✅ Risolto | Eseguito il 2026-05-10 al 3° tentativo (vedi sezione "Esecuzione 2026-05-10" in fondo) |
| Extra | Reinvio unificato → form | ✅ Risolto | Modifica al di fuori del piano originale (2026-05-10) |
| Extra | Validazione email + nuovo formato CC | ✅ Risolto | Bug latente esposto dal refactor Mailable (2026-05-10) |
| Extra | Icona reinvio anche su `partial` | ✅ Risolto | UX fix (2026-05-10) |

---

## Rivalutazione del review originale

Dopo verifica diretta del codice, il piano precedente sopravvalutava alcuni rischi. Questa versione classifica gli interventi in tre categorie oneste: **necessari**, **utili ma non urgenti**, **rimossi** (con motivazione).

---

## Interventi NECESSARI

### INT-01 · XSS da session flash

**Verifica precondizione**
```bash
grep -rn "{!!" resources/views/ --include="*.blade.php" | grep "session("
```
Cinque occorrenze confermate. L'intervento è ancora necessario se l'output contiene righe.

**Contesto reale — severità ridimensionata**
La route admin è protetta da `['auth', 'admin_or_superadmin']`. Per sfruttare l'XSS, un attore deve già essere admin autenticato. Il rischio non è "chiunque può iniettare script": è un admin che crea un record con nome malevolo che colpisce un altro admin.

Detto ciò, la verifica del codice mostra pattern concreti che portano dati esterni nelle flash:
- `"Torneo '{$tournament->name}' aggiunto"` — il nome del torneo è input utente
- `"Assegnazione di {$refereeName} rimossa..."` — nome arbitro da DB
- `"Errore durante l'aggiornamento: ".$e->getMessage()` — messaggio di eccezione (può contenere frammenti SQL, nomi di tabelle)

Il punto più sensibile è `$e->getMessage()` nelle flash di errore: un'eccezione DB che espone nomi di colonna o tabella viene renderizzata non escaped. Non è un exploit XSS classico, ma è information disclosure involontaria.

**Il fix rimane consigliato perché costa 30 minuti e chiude sia XSS che information disclosure.**

**Cambiamento**
In ogni occorrenza: `{!! session('xxx') !!}` → `{{ session('xxx') }}`

Se qualche flash contiene deliberatamente HTML (link), verificarlo prima: in quel caso usare `e()` nel controller al momento della scrittura.

**Verifica postcondizione**
```bash
grep -rn "{!!" resources/views/ --include="*.blade.php" | grep "session("
# Output atteso: nessuna riga
```

---

### INT-04 · N+1 in `AssignmentController::storeMultiple()`

**Verifica precondizione**
```bash
sed -n '508,525p' app/Http/Controllers/Admin/AssignmentController.php
```
Confermare che `Assignment::where(...)->exists()` sia dentro un `foreach`.

**Contesto reale**
Il metodo è dentro `DB::beginTransaction()`. Nel golf reale, un torneo ha tipicamente 5-15 arbitri. 15 query `EXISTS` + 15 `INSERT` = 30 query in una transazione — accettabile in senso assoluto ma inutilmente costoso, e il fix è banale.

Il problema più sottile è che questo codice trattiene una connessione DB per tutta la durata del loop, inclusa la logica di auto-creazione `TournamentNotification` che segue. Se un `INSERT` è lento, la transazione rimane aperta.

**Cambiamento**
```php
// Una sola query prima del loop
$existingUserIds = Assignment::where('tournament_id', $tournament->id)
    ->pluck('user_id')
    ->toArray();

foreach ($request->referee_ids as $refereeId) {
    if (in_array($refereeId, $existingUserIds)) {
        $skipped++;
        continue;
    }
    Assignment::create($data);
    $created++;
}
```

**Verifica postcondizione**
Con Telescope o query log: testare `storeMultiple()` con 5 arbitri (mix di già-assegnati e nuovi). Il numero di query deve essere `1 SELECT pluck + N INSERT`, non `N * 2`.

---

## Interventi UTILI ma non urgenti

### INT-02 · `AvailabilityRequest` — dead code con `authorize() = false`

**Verifica precondizione**
```bash
grep -rn "AvailabilityRequest" app/Http/
```
Output atteso: solo la definizione della classe. Il `User\AvailabilityController` usa `Request` plain con validazione inline — il Form Request non è referenziato.

**Motivazione**
Non è un bug attivo. È una trappola: qualsiasi futuro sviluppatore che aggiunge `AvailabilityRequest` come type-hint al controller (comportamento atteso dato il nome del file) blocca silenziosamente tutto il workflow delle disponibilità arbitri con un 403.

**Opzioni**:
- Eliminare il file se non si intende mai usarlo
- Completarlo (regole + `return true`) e collegarlo al controller al posto della validazione inline

**Non è urgente ma ha senso farlo nella stessa sessione in cui si tocca `AvailabilityController`.**

---

### INT-03 · N+1 in `ClubController::export()`

**Verifica precondizione**
```bash
grep -n "tournaments()" app/Http/Controllers/Admin/ClubController.php | grep "count"
```

**Motivazione**
Export CSV eseguito raramente, path non critico. La query `zone` è già eager-loaded. Rimane solo `$club->tournaments()->count()` nel loop. Su 200 circoli = 200 query aggiuntive. Non causa disservizi in condizioni normali ma può rallentare o andare in timeout su DB con carico.

**Fix in due righe:**
```php
// Nella query base
$clubs = $query->withCount('tournaments')->orderBy('name', 'asc')->get();

// Nel loop
$club->tournaments_count,  // invece di $club->tournaments()->count()
```

**Prerequisito**: verificare `function tournaments()` in `Club.php` prima di aggiungere `withCount`.

---

## Interventi RIMOSSI dal piano

### ~~INT-05 · `Mail::raw()` nel controller~~

**Perché rimosso**: la forma minimale dell'email è intenzionale e voluta. Il contenuto viene da `$validated['message']` con validazione `required|string` — sufficiente per l'uso previsto. L'accesso alla route è limitato a `admin_or_superadmin`. Non c'è un problema funzionale né un rischio concreto nel contesto applicativo. Il refactoring verso un Mailable dedicato aggiungerebbe complessità senza beneficio operativo.

---

### ~~INT-06 · Estrarre `NationalNotificationSendService`~~

**Perché rimosso**: il controller è grande (847 righe) ma i flussi di invio nazionale funzionano. Estrarre un service ora avrebbe senso solo se si dovesse modificare la logica di invio — ma con `Mail::raw` confermato come corretto, non c'è un trigger concreto per questo refactoring. Da rivalutare se il flusso di notifica nazionale viene esteso.

---

### ~~INT-07 · Estrarre `RefereeSelectionService`~~

**Perché rimosso**: i 4 metodi `get*Referees` sono privati e usati solo all'interno di `AssignmentController`. Non ci sono duplicazioni cross-controller, non ci sono bug. Il refactoring è "codice più pulito" senza beneficio misurabile oggi.

---

### ~~INT-08 · Auto-creazione `TournamentNotification` → `AssignmentObserver`~~

**Perché rimosso**: il blocco è già isolato in un `try/catch` separato dalla transazione principale — un fallimento nell'auto-creazione non rompe l'assegnazione. Spostarlo in un Observer introduce un rischio: l'observer si attiva su **ogni** `Assignment::create()`, non solo su `storeMultiple()`, e potrebbe generare notifiche indesiderate in altri flussi (import Federgolf, assegnazioni singole). Il costo del refactoring supera il beneficio.

---

### ~~INT-09 · Rimuovere `user_type` e `is_active` dal fillable di `User`~~

**Perché rimosso**: il rischio è teorico. `user_type` è un Enum castato — un valore non valido nell'enum Laravel causa un'eccezione prima che il record venga salvato. `UserController.store()` hardcoda `user_type = 'referee'`. Non esiste oggi nessun endpoint che fa `User::create($request->all())`. Da monitorare se viene aggiunto un endpoint di import utenti.

---

### ~~INT-10 · Completare `AvailabilityRequest` con regole~~

Dipendente da INT-02. Se INT-02 viene eseguito come "elimina il file", questo non esiste. Se viene eseguito come "completa e collega", questo è parte dello stesso intervento.

---

## Piano rivisto

| # | Intervento | Effort | Necessità | Fare quando |
|---|-----------|--------|-----------|-------------|
| INT-01 | XSS flash (5 template) | 30 min | Sì | Prima del prossimo deploy |
| INT-04 | N+1 storeMultiple | 20 min | Sì | Prima del prossimo deploy |
| INT-03 | N+1 ClubController export | 15 min | Utile | Prossima sessione su ClubController |
| INT-02 | AvailabilityRequest (decide: elimina o completa) | 30 min | Utile | Prossima sessione su workflow disponibilità |

Gli interventi rimossi (INT-05, 06, 07, 08, 09, 10) non vanno eseguiti nella forma descritta nel review originale. Possono essere rivalutati se i flussi coinvolti vengono estesi.

---

*Revisione basata su lettura diretta del codice — 2026-04-07.*

---

# Esecuzione 2026-05-10

Sessione di patch chirurgico con l'utente in cowork. Riepilogo cronologico.

## Modifiche applicate

### 1. Eliminazione `AvailabilityRequest` (INT-02)

`app/Http/Requests/AvailabilityRequest.php` aveva `authorize() = false` e `rules() = []`. La classe non era importata da nessuna parte (confermato via grep). Il `dead_code_report.md` la flaggava al 85% di confidenza. Eliminato come trappola futura.

### 2. Reinvio unificato → form di preparazione (extra)

Il metodo `NotificationController::resend()` aveva due rami: zonale (via `transactionService->sendWithTransaction`) e nazionale (via `resendNationalNotification` privato). I record importati da FIG (`metadata.source = "Import batch FIG"`) cadevano nel ramo zonale anche su tornei nazionali → flusso rotto.

**Cambio**: `resend()` ora ridirige sempre a `route('admin.tournaments.show-assignment-form', $notification->tournament)`. L'admin rivede destinatari e contenuti, poi invia via `sendNationalNotification` (per nazionali) o flusso zonale (per zonali). Comportamento uniforme per tutti i record (zonali, nazionali, FIG).

**Cleanup**: rimosso il metodo privato `resendNationalNotification()` (~90 righe) ora dead code.

### 3. Refactor `Mail::raw` → `NationalNotificationMail` (INT-05, era "rimosso")

L'utente ha voluto ritentarlo dopo il commit del cambio (2). Tre tentativi:

- **Tentativo 1** (rollback): regressione apparente, file Mailable creati ma errori inspiegabili in MAMP
- **Tentativo 2** (rollback): stesso scenario
- **Tentativo 3** (success): durante il debug ho trovato la causa reale — non era il Mailable in sé, era un **bug latente nel formato del CC array**

**Cosa cambia in concreto**:
- `app/Mail/NationalNotificationMail.php` (nuovo) — Mailable con `subjectLine` + `body`
- `resources/views/emails/national-notification.blade.php` (nuovo) — view con `{!! nl2br(e($body)) !!}` per neutralizzare HTML injection
- `NotificationController::sendNationalNotification` — 2 chiamate `Mail::raw` sostituite con `Mail::to($email)->cc($ccArray)->send(new NationalNotificationMail(...))`

### 4. Fix bug latente formato CC array

**Sintomo che ha bloccato i primi 2 tentativi**:
```
local.ERROR: Errore invio email
{"recipient":"campionati@federgolf.it","error":"Email \"Sezione Zonale Regole 6\" does not comply with addr-spec of RFC 2822."}
```

Symfony Mailer trattava il NAME ("Sezione Zonale Regole 6") come se fosse l'email.

**Causa**: `NotificationRecipientBuilder::build()` restituiva CC come `['email_string' => 'name_string']` (formato associativo). `Mail::raw + closure` accetta entrambi i formati perché chiama `Symfony Message::cc()` direttamente. Ma `Mail::to()->cc($array)->send(Mailable)` passa attraverso `PendingMail::parseAddresses()` che itera l'array via `collect()->map()` sui **VALUES**, quindi il name finiva al parser RFC 2822 come stringa-email.

**Fix**: `build()` ora restituisce CC come `array<{email: string, name: string}>` — formato canonico Laravel, lo stesso del TO. Aggiornato anche il fallback "primo CC come TO" nel controller (`$ccArray[0]['email']` invece di `array_key_first`).

### 5. Validazione email difensiva (extra)

Durante il debug è emerso che alcune zone in DB hanno il **nome al posto dell'email** (dato corrotto). Aggiunta validazione `filter_var(FILTER_VALIDATE_EMAIL)` in `addCc/addTo`: skippa silenziosamente con `Log::warning`, l'invio prosegue. Previene che un singolo record cattivo blocchi l'intera notifica.

### 6. UX: icona reinvio anche su `partial` e `failed`

`resources/views/admin/tournament-notifications/index.blade.php` riga 173: la condizione era `'sent' || 'failed'`, ora `in_array($status, ['sent', 'failed', 'partial'], true)`. Permette il retry quando una parte degli invii fallisce.

### 7. Consolidamento documenti `.md`

Spostati in `_audit_archive/`:
- `AUDIT_report.md`, `AUDIT_report_v2.md`, `AUDIT_report_v3.md` (audit superati)
- `SPEC_ricostruzione.md` (ricostruzione non più in piano)

In root restano: `README.md`, `AUDIT_architect_review.md` (fonte di verità), `AUDIT_notifications_v1.md`, `dead_code_report.md`, `DeepTest_Report.md`, `PIANO_INTERVENTO.md`.

## Test di regressione aggiunti

- `tests/Feature/Admin/NationalNotificationMailDispatchTest.php` — verifica che `sendNationalNotification` dispatch correttamente la `NationalNotificationMail` con subject e body integri
- `tests/Unit/Services/NotificationRecipientBuilderEmailValidationTest.php` — copre 5 casi:
  1. Email malformata in TO viene skippata
  2. Email malformata in CC viene skippata
  3. Email valide vengono mantenute
  4. **`build()` restituisce CC in formato canonico** `array<{email, name}>` (regressione critica del bug del 10 maggio)
  5. **CC array è consumabile da `Mail::cc()`** senza errore RFC 2822

Eseguire dopo qualsiasi modifica al builder o al flusso di invio:
```bash
php artisan test tests/Unit/Services/NotificationRecipientBuilderEmailValidationTest.php
php artisan test tests/Feature/Admin/NationalNotificationMailDispatchTest.php
```

## Prossimi passi noti

- INT-03 (N+1 ClubController export) — da fare
- INT-04 (N+1 storeMultiple) — da fare
- Convocazioni FIG migrate: i record importati hanno `documents=NULL` e `metadata.subject/message` assenti. Con il reinvio unificato l'admin compila il form e prosegue normalmente. Se serve un tool di "rehydration" automatica, è un task separato.
- Dato corrotto nelle zone (`zones.email = nome zona` per alcune righe): investigare e bonificare con UPDATE SQL quando opportuno.
