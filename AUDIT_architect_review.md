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
