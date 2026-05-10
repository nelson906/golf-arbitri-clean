# Architectural Review — Golf Arbitri (Laravel 12)
*Senior Laravel Architect — Generato il 2026-04-07*
*Scope: SOLID Principles · Service Pattern · N+1 Query · Security*

---

## Executive Summary

| Area | Stato | Anomalie critiche |
|------|-------|-------------------|
| SOLID / SRP | 🟡 Parziale | 2 controller God-class |
| Service Pattern | 🟢 Buono | Alcune eccezioni |
| Form Requests | 🟡 Parziale | 1 bug bloccante |
| N+1 Queries | 🔴 Critico | 3 pattern confermati |
| Security | 🔴 Critico | XSS da flash, Mail::raw nel controller |

La codebase ha un'architettura **mediamente matura**: esiste un service layer reale (7+ service specializzati), i middleware sono applicati sistematicamente, i Form Request sono usati per i flussi principali. I problemi più seri sono localizzati e risolvibili senza un refactoring globale.

---

## 1. SOLID Principles — Single Responsibility

### Violazioni confermate

**`NotificationController` — 847 righe, 18 metodi pubblici**

Questo controller gestisce contemporaneamente: lista notifiche, generazione documenti DOCX, invio email, upload file, eliminazione, preview, salvataggio clausole, invio convocazioni, invio notifiche nazionali. Sono almeno 5 responsabilità distinte.

Il problema più grave è che `sendNationalNotification()` e `resendNationalNotification()` chiamano **`Mail::raw()` direttamente nel controller** (righe 402, 746, 770). Questo bypassa completamente i service già presenti nel costruttore (`NotificationPreparationService`, `NotificationDocumentService`, `NotificationTransactionService`) e introduce logica di invio duplicata con `resend()`.

```php
// NotificationController.php:402 — logica di invio direttamente nel controller
\Illuminate\Support\Facades\Mail::raw($message, function ($mail) use ($recipient, $subject, $ccArray) {
    $mail->to($recipient['email'], $recipient['name'])->subject($validated['subject']);
});
```

**Soluzione**: estrarre un `NationalNotificationSendService` che incapsula i due flussi `sendNationalNotification()` e `resendNationalNotification()`. Il controller deve delegare e ridirigersi.

---

**`AssignmentController` — 860 righe, 18 metodi pubblici**

Gestisce: CRUD assegnazioni, logica di validazione doppia (inline + service), query di selezione arbitri con 4 metodi privati (`getAssignedReferees`, `getAvailableReferees`, `getPossibleReferees`, `getNationalReferees`), auto-creazione `TournamentNotification` in `storeMultiple()`.

La logica di creazione automatica della notifica torneo dentro `storeMultiple()` è una responsabilità trasversale che appartiene a un Observer o a un Service dedicato, non al controller di assegnazione.

**Soluzione**: i 4 metodi `get*Referees` appartengono già concettualmente all'`AssignmentValidationService` o a un nuovo `RefereeSelectionService`. L'auto-creazione della notifica va nell'`AssignmentObserver` già presente.

---

**Controller ben dimensionati (no violazioni)**

`DashboardController` (54 righe, 1 metodo), `CommunicationController` (181 righe, 6 metodi), `TournamentTypeController` (147 righe) rispettano correttamente SRP.

---

## 2. Laravel Best Practices — Service Pattern & Form Requests

### Service Pattern: utilizzo buono con eccezioni

La struttura dei service è **ben progettata**:

- `NotificationPreparationService`, `NotificationDocumentService`, `NotificationTransactionService` → separazione fasi pipeline notifica
- `Statistics/*` (5 service) → corretto isolamento per dominio statistico
- `Monitoring/*` (4 service) → corretto
- `AssignmentValidationService` → validation logic fuori dal controller

Il problema è l'**incoerenza nell'applicazione del pattern**. `UserController.store()` e `UserController.update()` eseguono logica di business inline (generazione `referee_code`, hashing password, concatenazione `name`) senza delegare a un service:

```php
// UserController.php:203 — logica di business nel controller
$validated['password'] = Hash::make('password123');
$validated['name'] = trim($validated['first_name'].' '.$validated['last_name']);
$validated['referee_code'] = 'REF' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
```

Questo codice appartiene a un `UserCreationService` o al Model stesso (accessor `full_name`, factory method `generateRefereeCode()`).

Analogamente, `ClubController.export()` itera su `$clubs` chiamando `$club->tournaments()->count()` dentro un loop — vedi sezione N+1.

---

### Form Requests: buon utilizzo con un bug critico

**Pattern corretto** nei flussi principali:
- `TournamentRequest` → usato in `store()` e `update()`, con `authorize()` che verifica stato torneo
- `AssignmentRequest` → `authorize()` implementata con controllo zona
- `TournamentTypeRequest` → `authorize()` verifica `isSuperAdmin()`
- `DocumentUploadRequest` → presente

**Bug critico: `AvailabilityRequest` — `authorize()` restituisce sempre `false`**

```php
// app/Http/Requests/AvailabilityRequest.php
public function authorize(): bool
{
    return false; // ← BLOCCA SEMPRE LA REQUEST
}

public function rules(): array
{
    return []; // ← NESSUNA REGOLA DEFINITA
}
```

Questo Form Request è stato creato ma mai implementato. Se viene usato da `AvailabilityController`, **ogni richiesta viene rigettata con 403**. Se non viene usato, il controller gestisce le disponibilità senza validazione. In entrambi i casi è un problema.

Verifica `AvailabilityController` e completa il Form Request con `return true` (o logica di autorizzazione) e le regole appropriate per date e torneo.

---

**Inconsistenza: `AssignmentController.store()` usa `$request->validate()` inline**

```php
// AssignmentController.php:154
$validated = $request->validate([
    'tournament_id' => 'required|exists:tournaments,id',
    'user_id'       => 'required|exists:users,id',
    'role'          => 'nullable|string',
]);
```

Esiste già `AssignmentRequest` — perché non è usato qui? Probabile sviluppo in momenti diversi. Uniformare usando sempre il Form Request dedicato.

---

## 3. Ottimizzazione DB — N+1 Query

### N+1 confermato #1: `ClubController::export()` — gravità ALTA

```php
// ClubController.php:315-330
foreach ($clubs as $club) {
    fputcsv($file, [
        $club->zone->name ?? 'N/A',         // ← accesso relazione non eager-loaded
        $club->tournaments()->count(),       // ← query COUNT per ogni club
    ]);
}
```

Su 100 club: **200 query aggiuntive** (100 per `zone`, 100 per `tournaments()->count()`). La query base non include eager loading di `zone`.

**Fix:**
```php
$clubs = Club::with('zone')->withCount('tournaments')->get();
// Poi nel loop:
$club->zone->name ?? 'N/A'
$club->tournaments_count
```

---

### N+1 confermato #2: `AssignmentController::storeMultiple()` — gravità MEDIA

```php
// AssignmentController.php:508-520
foreach ($request->referee_ids as $refereeId) {
    $exists = Assignment::where('tournament_id', $tournament->id)
        ->where('user_id', $refereeId)
        ->exists(); // ← 1 query per arbitro
    if (!$exists) {
        Assignment::create($data); // ← 1 query per arbitro
    }
}
```

Con 10 arbitri selezionati: 20 query. Con 30: 60 query.

**Fix:** precaricare gli `user_id` esistenti per il torneo in un singolo array, poi fare il check in PHP:
```php
$existingIds = Assignment::where('tournament_id', $tournament->id)
    ->pluck('user_id')
    ->toArray();

foreach ($request->referee_ids as $refereeId) {
    if (!in_array($refereeId, $existingIds)) {
        // insert
    }
}
```

---

### N+1 confermato #3: `NotificationController::destroyTournament()` — gravità BASSA

```php
// NotificationController.php:500-504
$notifications = TournamentNotification::where('tournament_id', $tournament->id)->get();
foreach ($notifications as $notification) {
    $this->transactionService->deleteWithCleanup($notification); // ← 1+ query per notifica
}
```

Se `deleteWithCleanup()` esegue query aggiuntive (cancellazione documenti, log), si amplifica su ogni notifica. Verificare se `transactionService` può accettare una collection e operare in batch.

---

### Eager loading buono (pattern corretto trovato)

Il `NotificationController::index()` usa correttamente eager loading completo:
```php
TournamentNotification::with([
    'tournament.club',
    'tournament.zone',
    'tournament.tournamentType',
    'tournament.assignments.user',
])
```

Il `User/DashboardController` usa anch'esso `->with('tournament.tournamentType')` e `->with(['tournament.club', 'tournament.tournamentType'])` prima dei loop — **nessun N+1 qui**.

---

## 4. Security

### 🔴 XSS da session flash — gravità ALTA

In più template Blade, i messaggi flash vengono stampati **senza escaping**:

```blade
{{-- admin/tournaments/index.blade.php:31 --}}
<p>{!! session('error') !!}</p>

{{-- admin/tournaments/show.blade.php:37 --}}
{!! session('error') !!}

{{-- layouts/admin.blade.php:245 --}}
{!! session('error') !!}

{{-- aruba-admin/layout.blade.php:117,129 --}}
{!! session('success') !!}
{!! session('info') !!}
```

Se un controller scrive in sessione un valore che include input utente non sanitizzato (es. nome di un torneo contenente `<script>`), questo viene eseguito come HTML nel browser. In questo progetto i controller scrivono spesso messaggi come `"Torneo {$tournament->name} eliminato"`.

**Fix immediato** — sostituire con escaping:
```blade
{{ session('error') }}
{{ session('success') }}
```

Se serve HTML (es. link nelle flash messages), usare `e()` esplicitamente nei controller prima di scrivere in sessione.

---

### 🔴 `Mail::raw()` nel controller — gravità ALTA (invio di testo arbitrario)

`sendNationalNotification()` e `resendNationalNotification()` inviano `$validated['message']` come corpo email **grezzo**, senza Mailable, senza template, senza sanitizzazione:

```php
Mail::raw($validated['message'], function ($mail) use ($recipient, $validated, $ccArray) {
    $mail->to(...)->subject($validated['subject']);
});
```

Il contenuto viene da `$request->input('message')` validato solo come `'required|string'`. Chiunque abbia accesso admin può inviare email con contenuto arbitrario (phishing, HTML injection nelle email). Usare un `Mailable` dedicato con template Blade e `nl2br(e($message))`.

---

### 🟡 Mass Assignment — stato accettabile ma da verificare

`User::$fillable` include esplicitamente tutti i campi sensibili:
```php
protected $fillable = [
    'name', 'first_name', 'last_name', 'email', 'password',
    'user_type', 'zone_id', 'referee_code', 'level', 'is_active', ...
];
```

Non c'è `$guarded = []` (nessun modello usa unguarded). Il rischio principale è che `user_type` e `is_active` siano in fillable: un utente che riesce a POST su `/admin/users` con `user_type=super_admin` bypassa il controllo di ruolo.

In `UserController.store()` il valore è hardcoded (`$validated['user_type'] = 'referee'`) — protetto. Ma se in futuro qualcuno aggiungesse un endpoint che fa `User::create($request->all())`, il campo `user_type` sarebbe modificabile. **Raccomandazione**: rimuovere `user_type` e `is_active` dal fillable e gestirli sempre esplicitamente nei controller.

---

### 🟡 Middleware — copertura buona con un'anomalia

Le route admin sono protette da `['auth', 'admin_or_superadmin']`, le super-admin da `['auth', 'super_admin']`. Il coverage è corretto.

**Anomalia**: le route `maintenance.php` (ArubaTools) appaiono nel report dello script come prive di autorizzazione, ma dalla lettura del file risultano già protette da `['auth', 'super_admin']`. Lo script ha rilevato un falso positivo perché le route sono definite in un file separato incluso dopo la dichiarazione del gruppo. Non è un problema reale.

**Da verificare**: le route API pubbliche in `routes/api/internal.php`:
```php
// Senza middleware auth
Route::prefix('tournaments')->group(function () {
    Route::get('/', function () {
        return Tournament::with([...])->where('status', 'completed')->paginate(20);
    });
});
```

Espone tornei completati pubblicamente. Verificare se intenzionale (accesso pubblico ai risultati) o dimenticanza.

---

### 🟢 CSRF — nessun problema rilevato

Tutti i form POST/PUT/DELETE usano `@csrf`. Le route API v1 che usano `api` middleware escludono CSRF correttamente (standard Laravel).

---

## Priorità di intervento

**Immediato (prima del prossimo deploy)**

1. `AvailabilityRequest::authorize()` restituisce `false` — verificare se blocca le disponibilità degli arbitri e correggere
2. `{!! session('error') !!}` → `{{ session('error') }}` in 5 template — 30 minuti di lavoro

**Breve termine (sprint corrente)**

3. `ClubController::export()` N+1 — aggiungere `with('zone')->withCount('tournaments')`
4. `AssignmentController::storeMultiple()` N+1 — precaricare gli `user_id` esistenti
5. `Mail::raw()` nel controller → `Mailable` dedicato

**Medio termine (refactoring pianificato)**

6. Estrarre `NationalNotificationSendService` da `NotificationController`
7. Estrarre logica `get*Referees` in `RefereeSelectionService`
8. Spostare auto-creazione `TournamentNotification` nell'`AssignmentObserver`
9. Rimuovere `user_type` e `is_active` dal fillable di `User`
10. Completare `AvailabilityRequest` con regole di validazione effettive

---

*Report generato tramite analisi statica + lettura diretta del codice — non sostituisce i test di integrazione.*

---

# 📌 AGGIORNAMENTO 2026-05-09 — Verifica delta + nota sulla stratificazione documentale

Verifica eseguita 1 mese dopo, applicando lo stesso script `analyze_laravel.py` usato per il progetto Lupi Grigi (rilettura completa: 67 route, 9 modelli, 48 anomalie / 34 critiche). Risultato: **niente è stato fixato dal 7 aprile**, e si sono accumulati altri due god controller non segnalati ad aprile.

## Stato dei finding del 7 aprile

| Finding | Stato 2026-05-09 |
|---|---|
| 🔴 `AssignmentController` god class (860 righe) | **INVARIATO** (oggi 861 righe) |
| 🔴 `NotificationController` god class (847 righe) | **INVARIATO** (oggi 847 righe) |
| 🔴 `Mail::raw()` nelle 3 occorrenze del controller (rg 402, 746, 770) | **INVARIATO** — codice identico |
| 🔴 `AvailabilityRequest::authorize()` restituisce `false` | da verificare in locale |
| 🔴 5 template con `{!! session('error') !!}` | non più trovato dal grep — possibile **risolto** già (verifica) |
| 🔴 N+1 in `ClubController::export()` | da verificare in locale |
| 🔴 N+1 in `AssignmentController::storeMultiple()` | da verificare in locale |
| ⚠️ Route API tornei pubblici in `routes/api/internal.php` | da verificare se intenzionale |

## God controller emersi dalla verifica di oggi (NON segnalati ad aprile)

| Controller | Righe | Note |
|---|---|---|
| `Admin/CareerHistoryController` | 676 | 4° controller più grande del progetto |
| `User/AvailabilityController` | 605 | gestione disponibilità arbitri |
| `SuperAdmin/ArubaToolsController` | 603 | dashboard sysadmin (giustificato? = strumenti + pagine + diagnostica in unico file) |

## Pattern XSS in view — verifica nuova

Esaminate tutte le `{!! ... !!}` nel `resources/views/`:
- ✅ Pattern corretto **ben applicato**: `nl2br(e(...))` per testi e `json_encode(...)` per chart data (Statistics, Communications)
- ⚠️ `aruba-admin/dashboard.blade.php:106` — HTML hardcoded condizionale, niente user input → SAFE
- ⚠️ `aruba-admin/phpinfo.blade.php:71` — `{!! phpinfo() !!}` in pagina admin-only → SAFE ma in produzione phpinfo è infosec-leak (paths, env)
- Il guestbook-style XSS visto su lupi-grigi NON è presente qui — disciplina di escape buona

## Stratificazione documentale (analogia con Lupi Grigi)

Nella root del progetto ci sono **10 documenti `.md` accumulati**:

| File | Data | Stato consigliato |
|---|---|---|
| `README.md` | apr 2026 | TIENI |
| `AUDIT_architect_review.md` | apr 2026 | TIENI (questo, aggiornato) |
| `AUDIT_report.md` | apr 2026 | archivia |
| `AUDIT_report_v2.md` | apr 2026 | archivia |
| `AUDIT_report_v3.md` | apr 2026 | archivia |
| `AUDIT_notifications_v1.md` | apr 2026 | da valutare (specifico, forse ancora utile) |
| `dead_code_report.md` | apr 2026 | TIENI (verificalo prossima sessione, come fatto per lupi-grigi) |
| `DeepTest_Report.md` | apr 2026 | TIENI (output strumento dedicato) |
| `PIANO_INTERVENTO.md` | apr 2026 | da valutare in funzione dello stato di esecuzione |
| `SPEC_ricostruzione.md` | apr 2026 | archivia se la ricostruzione non è più in piano |

**Suggerimento (non eseguito oggi):** quando vorrai, applica la stessa procedura usata per Lupi Grigi: cartella `_audit_archive/` con i V1/V2/V3 e SPEC, mantenendo in root solo i 4-5 documenti vivi (vedi sezione "Documentazione tecnica complementare" in `OPERATIONS.md` di Lupi Grigi).

## Distribuzione anomalie con verità sui falsi positivi

Lo script segnala 27 `missing_authorization` ma le route admin/superadmin sono protette via middleware di gruppo (`['auth', 'admin_or_superadmin']` / `['auth', 'super_admin']`). Numeri reali:

| Tipo | N° tool | Reali stimati |
|---|---|---|
| `missing_authorization` | 27 | ~3-5 (route API pubbliche `/api/internal/tournaments/*`) |
| `db_in_controller` | 13 | smell architetturale, non urgente |
| `missing_validation` | 7 | da verificare endpoint per endpoint |
| `n_plus_1` | 1 | da verificare con `\DB::listen` in locale |

## Top 5 azioni proposte (per priorità beneficio/sforzo)

1. **`Mail::raw()` nelle 3 occorrenze di `NotificationController`** — sostituire con `Mailable` dedicato. Beneficio: sblocca testabilità (`Mail::fake()`), pattern coerente col resto. Sforzo: 1-2 sessioni.
2. **Verifica `AvailabilityRequest::authorize() === false`** — se è davvero così, blocca silenziosamente tutte le disponibilità arbitri. 5 minuti per verificare in locale.
3. **Verifica route API pubbliche `/api/internal/tournaments/*`** — se non intenzionale, aggiungere middleware. 1 minuto di check.
4. **Consolidamento documenti `.md`** — vedi tabella sopra. 15 minuti, riduce confusione.
5. **God controller `AssignmentController` e `NotificationController`** — refactor pianificato (medio termine, no nuove stratificazioni: estrarre service esistenti che già hanno il pattern).

---

# 📌 AGGIORNAMENTO 2026-05-10 — Esecuzione fix in cowork

Sessione di patch chirurgico con l'utente. I dettagli completi sono in `PIANO_INTERVENTO.md` (sezione "Esecuzione 2026-05-10"). Riepilogo per quick reference:

## Stato finding (post-sessione)

| Finding 7 aprile | Stato 2026-05-10 |
|---|---|
| 🔴 `Mail::raw()` × 3 in `NotificationController` | ✅ **RISOLTO** — sostituito con `App\Mail\NationalNotificationMail` (Mailable + view con `nl2br(e($body))`). Una occorrenza era in `resendNationalNotification` privato che è stato eliminato (dead code dopo l'unifica del reinvio). Restano 2 chiamate aggiornate in `sendNationalNotification`. |
| 🔴 `AvailabilityRequest::authorize() === false` | ✅ **RISOLTO** — file eliminato (era dead code, nessun call site, confermato con grep) |
| 🔴 5 template con `{!! session('error') !!}` | ✅ **RISOLTO** — già fixato prima del 2026-05-09 (vedi audit del 9 maggio in coda) |
| ⚠️ Route API tornei pubblici | ✅ **INTENZIONALE** — verificato 2026-05-09: il file ha sezione "Public Tournament API (no auth required for read-only)", solo dati `completed`/`assigned`, no PII |
| 🔴 N+1 `ClubController::export()` | 🟡 Aperto |
| 🔴 N+1 `AssignmentController::storeMultiple()` | 🟡 Aperto |
| 🔴 `AssignmentController` god class | 🟡 Aperto |
| 🔴 `NotificationController` god class | 🟡 Migliorato (~110 righe rimosse: `resendNationalNotification` privato + reinvio unificato in 5 righe). Resta 700+ righe. |

## Sorpresa rilevata durante l'esecuzione

**Bug latente nel formato del CC array** in `NotificationRecipientBuilder::build()`. Era nascosto dal workaround di Symfony `Message::cc()` chiamato dentro la closure di `Mail::raw`. È esploso al primo passaggio a `Mail::to()->cc()->send(Mailable)`. Sintomatologia molto fuorviante: errore RFC 2822 che attribuiva al "name" il ruolo di "email".

Risolto cambiando il formato di ritorno di `build()` da `[email => name]` a `array<{email, name}>` (canonico Laravel). Test di regressione aggiunti per non riperdere la conoscenza:
- `tests/Unit/Services/NotificationRecipientBuilderEmailValidationTest.php` (5 test, copre formato + validazione + integrazione con `Mail::cc()`)
- `tests/Feature/Admin/NationalNotificationMailDispatchTest.php` (2 test, dispatch + render)

## Fix collaterali (non nel piano originale)

- **Reinvio unificato**: tutti i record (zonali, nazionali, FIG-importati) passano dal form `prepare_notification` su click di "Reinvia". Codice più semplice (5 righe invece di un branch), comportamento più prevedibile per l'utente.
- **Validazione email difensiva**: `addCc/addTo` in `NotificationRecipientBuilder` skippano email malformate con `Log::warning`. Previene crash da dati corrotti in DB (alcune zone hanno il nome al posto dell'email).
- **Icona reinvio anche su `partial` e `failed`**: nel view di indice, prima nascosta su `partial`. Permette retry senza dover passare dal dettaglio.
- **Consolidamento `.md`**: creato `_audit_archive/` con i V1/V2/V3 e SPEC_ricostruzione.

## Note operative

- MAMP usa OPcache aggressivo: dopo modifiche a service/controller serve un restart Apache completo (Stop + Start dalla GUI MAMP), non basta `php artisan optimize:clear`.
- Test unit con `Mail::fake()` hanno copertura cieca sul rendering effettivo della view e sul parsing degli address di Laravel/Symfony — non rilevano problemi di formato CC. Per quello servono test di integrazione (vedi `test_cc_array_is_consumable_by_mail_cc`).
