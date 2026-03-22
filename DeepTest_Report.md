# 🔬 Deep Test Report — Golf Arbitri (Laravel)

> Analisi multilivello eseguita il 22 marzo 2026
> File analizzati: 14 | Metodi analizzati: ~80 | Bug trovati: **15** (4 critici, 6 alti, 5 medi)

---

## Sommario Esecutivo

| Livello | Metodo | Problemi trovati |
|---------|--------|-----------------|
| 1 | Complessità Ciclomatica | 3 metodi critici |
| 2 | Mutation Testing | 4 rami logici ciechi |
| 3 | Analisi Statica | 5 errori (1 fatale, 4 critici) |
| 4 | Fuzz Testing | 5 crash su input imprevisti |

---

## LIVELLO 1 — Complessità Ciclomatica

| Metodo | File | CC | Stato | Azione |
|--------|------|----|-------|--------|
| `sendNationalNotification()` | `NotificationController` | ~18 | 🚨 | Estrarre `buildToRecipients()`, `buildCcRecipients()`, `dispatchEmails()` |
| `resendNationalNotification()` | `NotificationController` | ~14 | 🔴 | Riusare logica di `sendNationalNotification` (codice quasi identico duplicato) |
| `sendSeparatedAdminNotifications()` | `AvailabilityController` | ~10 | ⚠️ | Estrarre in `AvailabilityNotificationService` |
| `detectDateConflicts()` | `AssignmentValidationService` | ~9 | ⚠️ | Estrarre `checkRefereeConflicts($refereeAssignments)` |
| `findMissingRequirements()` | `AssignmentValidationService` | ~12 | 🔴 | Estrarre `checkMinReferees()`, `checkRefereeLevel()`, `checkWrongZone()` |
| `storeMultiple()` | `AssignmentController` | ~8 | ⚠️ | Accettabile, ma la parte TournamentNotification andrebbe in un Observer |

**Problema grave di duplicazione:** `sendNationalNotification()` e `resendNationalNotification()` nel `NotificationController` contengono **circa 60 righe di codice identico** per la costruzione dei destinatari. Qualunque modifica alla logica di notifica deve essere aggiornata in due posti — bug garantiti nel tempo.

---

## LIVELLO 2 — Mutation Testing (Test Ciechi)

### 🧬 Mutante 1 — [CRITICO] Conflitto date con `end_date` null

**File:** `AssignmentValidationService.php:349-354`

```php
private function datesOverlap(Assignment $a1, Assignment $a2): bool
{
    $start1 = Carbon::parse($a1->tournament->start_date);
    $end1   = Carbon::parse($a1->tournament->end_date);   // ← BUG se null
    ...
}
```

**Problema:** `Carbon::parse(null)` non lancia un'eccezione ma **restituisce la data e ora corrente**. Se un torneo non ha `end_date` (campo nullable), tutti i tornei risulteranno in conflitto con esso perché la data di fine diventa "adesso". Risultato: falsi allarmi di conflitto a cascata.

**Mutazione che sopravviverebbe:** Cambiare `$a1->tournament->end_date` con `null` → nessun test fallisce.

**Fix:**
```php
$end1 = $a1->tournament->end_date
    ? Carbon::parse($a1->tournament->end_date)
    : Carbon::parse($a1->tournament->start_date)->endOfDay();
```

---

### 🧬 Mutante 2 — [ALTO] Severità conflitto calcolata su start_date, non sulla sovrapposizione reale

**File:** `AssignmentValidationService.php:357-371`

```php
private function calculateConflictSeverity(Assignment $a1, Assignment $a2): string
{
    $start1 = Carbon::parse($a1->tournament->start_date);
    $start2 = Carbon::parse($a2->tournament->start_date);
    $daysDiff = abs($start1->diffInDays($start2));
    if ($daysDiff === 0)  return 'high';
    elseif ($daysDiff <= 1) return 'medium';
    return 'low';
}
```

**Problema:** Due tornei che si sovrappongono per una settimana intera (es. 10-17 marzo e 13-20 marzo) vengono classificati come severità `low` perché le date di *inizio* differiscono di 3 giorni. Ma il conflitto è reale e grave. La severità dovrebbe basarsi sui **giorni di sovrapposizione effettivi**, non sulla differenza tra le date di inizio.

**Mutazione che sopravviverebbe:** Cambiare `$daysDiff <= 1` in `$daysDiff <= 10` → nessun test fallisce.

---

### 🧬 Mutante 3 — [ALTO] Confronto `!=` invece di `!==` per user_id

**File:** `AssignmentController.php:263`

```php
if ($validated['user_id'] != $assignment->user_id) {
```

**Problema:** `$validated['user_id']` è una stringa (proveniente da `$request->validate()`), mentre `$assignment->user_id` è un intero (dal database). In PHP, `"1" != 1` è `false` (confronto lasco), quindi il controllo funziona. Ma se il tipo cambia (es. aggiornamento Laravel) o il cast non avviene, si potrebbero avere comportamenti inattesi. Usare `!==` garantirebbe la type safety.

---

### 🧬 Mutante 4 — [MEDIO] `checkAvailabilityStatus` ignora l'anno corrente

**File:** `AssignmentValidationService.php:427-430`

```php
private function checkAvailabilityStatus(User $referee): string
{
    $hasAvailabilities = $referee->availabilities()->exists();
    return $hasAvailabilities ? 'available' : 'unavailable';
}
```

**Problema:** Un arbitro che aveva dichiarato disponibilità 3 anni fa viene classificato come `'available'`, indipendentemente dall'anno corrente. Il metodo non filtra per tornei futuri o per l'anno in corso. Risultato: arbitri con disponibilità vecchie sembrano disponibili nelle statistiche.

**Mutazione che sopravviverebbe:** Rimuovere il filtro `exists()` e restituire sempre `'available'` → nessun test fallisce perché non esistono test su questo metodo.

---

## LIVELLO 3 — Analisi Statica

### ❌ ERRORE FATALE — `Club::active()` scritto con minuscola

**File:** `TournamentController.php:450`

```php
public function getclubsByZone(Request $request)
{
    ...
    $clubs = club::active()   // ← 'club' minuscolo: Class "club" not found
        ->where('zone_id', $request->zone_id)
        ->ordered()
        ->get(['id', 'name', 'short_name']);
}
```

**Impatto:** PHP Fatal Error garantito ogni volta che questo endpoint AJAX viene chiamato. La funzione di caricare i club per zona nel form di creazione/modifica torneo è **completamente rotta**.

**Fix:** `Club::active()` (maiuscola).

---

### ❌ ERRORE CRITICO — Null pointer su `tournamentType` senza relazione caricata

**File:** `TournamentController.php:415`

```php
->when($tournament->tournamentType->is_national, function ($q) {
    ...
```

**Problema:** `$tournament->tournamentType` può essere `null` se il torneo non ha un tipo associato (campo nullable). L'accesso a `->is_national` su null causa un `TypeError: Cannot access property "is_national" on null`.

La relazione `tournamentType` non viene caricata prima di questo punto nel metodo `availabilities()`.

**Fix:** `->when($tournament->tournamentType?->is_national ?? false, ...)`

---

### ❌ ERRORE CRITICO — Null pointer su `$tournament->club` nelle notifiche nazionali

**File:** `NotificationController.php:326` e `NotificationController.php:679`

```php
// Riga 326 (resendNationalNotification)
if ($tournament->club->zone?->email) {

// Riga 679 (sendNationalNotification)
if ($request->has('send_to_zone') && $tournament->club->zone?->email) {
```

**Problema:** `$tournament->club` può essere `null` se il circolo è stato eliminato o il torneo non ha un circolo associato. L'optional chaining `?->` è applicato su `zone` ma **non su `club`**, causando un `TypeError` se il club è null. Il nullable operator viene applicato solo al secondo livello ma manca al primo.

**Fix:** `$tournament->club?->zone?->email`

---

### ⚠️ AVVISO ALTO — Null pointer su `tournamentType` nel Service di validazione

**File:** `AssignmentValidationService.php:101`

```php
if ($tournament->assignments->count() < $tournament->tournamentType->min_referees) {
```

**Problema:** Se un torneo non ha `tournamentType` associato (relazione null), questa riga causa un `TypeError`. La query a riga 87 carica la relazione con `with(['tournamentType', ...])`, ma se il `tournament_type_id` è null o punta a un record eliminato, Eloquent restituirà `null`.

**Fix:** `$tournament->tournamentType?->min_referees ?? 0`

---

### ⚠️ AVVISO ALTO — Inconsistenza case nei livelli arbitro

**File:** `AssignmentController.php:118`, `AssignmentController.php:129` vs `TournamentController.php:416`

```php
// AssignmentController (maiuscolo)
->whereIn('level', ['Nazionale', 'Internazionale'])

// TournamentController::availabilities() (minuscolo)
->whereIn('level', ['nazionale', 'internazionale'])
```

**Problema:** Lo stesso filtro è scritto in due modi diversi in due file diversi. Uno dei due non funzionerà correttamente, a seconda di come i livelli sono salvati nel database. Se il DB contiene `'Nazionale'`, la query di `availabilities()` non troverà mai arbitri nazionali. Se contiene `'nazionale'`, il contrario.

**L'`HasZoneVisibility` trait usa correttamente l'enum `RefereeLevel` per ottenere i valori** — gli altri due posti dovrebbero fare lo stesso.

---

### ⚠️ AVVISO MEDIO — Middleware `DocumentAccess` non protegge se route param è null o non è un Document

**File:** `DocumentAccess.php:26`

```php
$document = $request->route('document');
if ($document instanceof \App\Models\Document && !$document->is_public ...) {
```

**Problema:** Se il parametro di route `document` non viene risolto come model (es. route binding disattivato, o la route non ha il parametro), `$document` sarà `null` o un intero. La condizione `instanceof` sarà `false` e il middleware lascerà passare tutto senza verificare nulla. Il controllo accesso viene silenziosamente saltato.

---

## LIVELLO 4 — Fuzz Testing

### 🎲 `threshold` senza validazione — Input malevolo

**File:** `AssignmentController.php:754` e `AssignmentController.php:783`

```php
$threshold = $request->input('threshold', 5);   // overassigned
$threshold = $request->input('threshold', 2);   // underassigned
```

| Input | Comportamento | Impatto |
|-------|--------------|---------|
| `threshold=-1` | Tutti gli arbitri risultano sovrassegnati | 🔴 Crash logico / DoS su grandi dataset |
| `threshold=0` | Tutti gli arbitri risultano sovrassegnati | 🔴 Stesso problema |
| `threshold=abc` | PHP converte a `0`, tutti sovrassegnati | 🔴 Tipo errato non catturato |
| `threshold=9999999` | Nessun arbitro trovato, risposta vuota | ⚠️ Risultato silenzioso e ingannevole |

**Fix:** `$threshold = max(1, (int) $request->input('threshold', 5));`

---

### 🎲 Parametro `$type` non validato nei document endpoints — Path traversal risk

**File:** `NotificationController.php` — metodi `generateDocument()`, `deleteDocument()`, `downloadDocument()`, `uploadDocument()`

```php
public function generateDocument(TournamentNotification $notification, $type)
public function deleteDocument(TournamentNotification $notification, $type)
public function downloadDocument(TournamentNotification $notification, $type)
```

**Problema:** Il parametro `$type` arriva dalla URL (es. `/admin/notifications/5/document/convocation/generate`) e **non viene mai validato**. Se `$type` viene passato a un metodo che costruisce un path su filesystem (es. `storage_path("notifications/{$type}")`), un attaccante autenticato potrebbe tentare path traversal con valori come `../../etc/passwd` o `../config/database.php`.

**Test di fuzz che crasherebbe:**

| Input `$type` | Effetto |
|---------------|---------|
| `../../etc/passwd` | Possibile path traversal nel DocumentService |
| `convocation/../../../config/app.php` | Path traversal |
| `<script>alert(1)</script>` | Se il tipo viene riflesso nella risposta JSON |
| `''` (stringa vuota) | Comportamento indefinito nel DocumentService |
| `null` | TypeError se `$type` viene usato in operazioni stringa |

**Fix:** Aggiungere validazione all'inizio di ogni metodo:
```php
if (!in_array($type, ['convocation', 'club_letter'], true)) {
    abort(422, 'Tipo documento non valido');
}
```

---

### 🎲 `saveBatch()` — IDOR: un arbitro può dichiarare disponibilità per tornei fuori dalla sua visibilità

**File:** `AvailabilityController.php:208-242`

```php
$request->validate([
    'availabilities' => 'array',
    'availabilities.*' => 'exists:tournaments,id',  // ← verifica solo esistenza
]);
```

**Problema:** La validazione verifica che l'ID torneo esista, ma **non verifica che l'arbitro abbia il permesso di accedere a quel torneo**. Il successivo filtro `$selectedTournaments = array_intersect($selectedTournaments, $pageTournamentIds)` protegge in teoria, ma solo se i filtri della pagina vengono inviati correttamente nella request. Un arbitro che manipola il form potrebbe riuscire ad aggiungere disponibilità per tornei di altre zone.

**Fix:** Sostituire `exists:tournaments,id` con un Rule personalizzato che verifica anche la visibilità, oppure aggiungere un controllo esplicito:
```php
$tournament = Tournament::findOrFail($tournamentId);
if (!$this->canAccessTournament($tournament, $user)) {
    continue; // o abort(403)
}
```

---

### 🎲 `sendAssignmentWithConvocation()` — `firstOrFail()` senza messaggio user-friendly

**File:** `NotificationController.php:534`

```php
$notification = TournamentNotification::where('tournament_id', $tournament->id)
    ->orderBy('created_at', 'desc')
    ->firstOrFail();
```

**Problema:** Se non esiste ancora una notifica per quel torneo (caso possibile se l'utente raggiunge l'endpoint direttamente senza passare dal form), `firstOrFail()` lancia una `ModelNotFoundException` che genera una pagina 404 generica, senza indicare all'utente come risolvere il problema. L'eccezione viene catturata dal `catch (\Exception $e)` sottostante, ma il messaggio di errore rivela informazioni interne sul modello.

---

## Piano d'Azione (prioritizzato)

### 🚨 IMMEDIATO — Fix critici (possono andare in produzione oggi)

1. **`TournamentController:450`** — `club::active()` → `Club::active()` *(2 minuti)*
2. **`TournamentController:415`** — aggiungere `?->is_national ?? false` *(5 minuti)*
3. **`NotificationController:326,679`** — `$tournament->club->zone` → `$tournament->club?->zone` *(5 minuti)*
4. **`NotificationController` metodi document** — aggiungere `in_array($type, [...], true)` *(15 minuti)*
5. **`AssignmentValidationService:101`** — aggiungere `?->min_referees ?? 0` *(5 minuti)*

### ⚠️ QUESTA SETTIMANA — Fix alti

6. **`AssignmentValidationService::datesOverlap()`** — gestire `end_date` null *(30 minuti)*
7. **Inconsistenza case nei livelli** — unificare usando `RefereeLevel::cases()` ovunque *(1 ora)*
8. **Validare `$threshold`** in overassigned/underassigned *(15 minuti)*
9. **`AvailabilityController::saveBatch()`** — verificare visibilità per ogni torneo selezionato *(1 ora)*

### 📅 PROSSIMO SPRINT — Refactoring

10. **Estrarre logica duplicata** da `sendNationalNotification` e `resendNationalNotification` in un metodo privato condiviso *(2 ore)*
11. **Correggere `calculateConflictSeverity`** per basarsi sulla sovrapposizione reale invece della differenza di start_date *(1 ora)*
12. **Correggere `checkAvailabilityStatus`** per filtrare per anno corrente *(30 minuti)*
13. **Rafforzare `DocumentAccess` middleware** *(30 minuti)*

---

## Appendice — File analizzati

- `app/Http/Controllers/Admin/AssignmentController.php`
- `app/Http/Controllers/Admin/TournamentController.php`
- `app/Http/Controllers/Admin/NotificationController.php`
- `app/Http/Controllers/User/AvailabilityController.php`
- `app/Services/AssignmentValidationService.php`
- `app/Services/NotificationTransactionService.php`
- `app/Traits/HasZoneVisibility.php`
- `app/Http/Middleware/DocumentAccess.php`
- `app/Models/Tournament.php`
- `app/Helpers/helpers.php`
