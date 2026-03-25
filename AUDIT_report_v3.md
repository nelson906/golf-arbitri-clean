# Audit Report v3 — Golf Arbitri
**Data:** 24 Marzo 2026
**Analista:** Audit automatico + analisi manuale approfondita
**Scope:** Codebase completo (app/, routes/, resources/, database/)

---

## Sommario Esecutivo

Il progetto è un'applicazione Laravel ben strutturata a grandi linee, con buona separazione in Service, Enum e Trait. Tuttavia, **lo sviluppo iterativo ha prodotto stratificazioni significative** che hanno generato: una duplicazione pericolosa nella logica delle notifiche, un bug silenzioso che rende il reinvio delle notifiche nazionali sempre sbagliato, funzioni globali PHP definite due volte, stringhe hardcoded invece di enum in punti critici, e una migrazione parziale referee→user mai completata. Il codice non è un disastro, ma ha bisogno di un refactoring mirato su 5-6 aree chiave prima che i bug si manifestino in produzione.

---

## Indice di Stratificazione

| Area | Score | Motivazione |
|------|-------|-------------|
| Architettura generale | 4/10 | Struttura buona, ma notifiche stratificate in 5 service |
| PHP/Laravel Backend | 5/10 | Bug funzionale nel resend nazionale, duplicazioni significative |
| Routing | 3/10 | Modularizzato bene, qualche legacy e inconsistenza |
| Database / Models | 4/10 | Model User scarsamente allineato al DB, doppia fonte verità su Tournament |
| Frontend (CSS/JS) | 2/10 | Tailwind + Alpine + React, minimal e pulito |
| Views / Blade | 3/10 | Migrazione referee→user incompleta, cartelle ibride |

---

## SEZIONE 1 — Problemi Critici (Bug funzionali)

### BUG-01: Il reinvio delle notifiche nazionali NON funziona mai correttamente

**File:** `app/Http/Controllers/Admin/NotificationController.php`, righe 296-320 e 839-856

**Problema:** Il metodo `resend()` cerca `$metadata['is_national']` per decidere se usare il percorso nazionale. Tuttavia, `sendNationalNotification()` **non salva mai `is_national: true` nel campo `metadata`** quando crea il record in `tournament_notifications`. Quindi `$isNational` sarà sempre `false` e il reinvio nazionale non verrà mai eseguito: verrà usato il percorso zonale (`sendWithTransaction`) che tenterà di usare `recipients` e `metadata` vuoti, causando errori o invii vuoti.

```php
// resend() cerca:
$isNational = $metadata['is_national'] ?? false;  // sarà sempre false

// sendNationalNotification() salva:
TournamentNotification::updateOrCreate(
    ['tournament_id' => $tournament->id, 'notification_type' => $notificationType],
    [
        'status' => ...,
        'sent_at' => now(),
        'details' => [...],
        // ← MANCA 'metadata' con 'is_national' => true e 'type' => $notificationType
    ]
);
```

**Fix:** Aggiungere al salvataggio di `sendNationalNotification()`:
```php
'metadata' => ['is_national' => true, 'type' => $notificationType, 'subject' => $validated['subject'], 'message' => $validated['message']]
```

---

### BUG-02: `CommunicationPolicy` usa `'super-admin'` con trattino — ruolo inesistente

**File:** `app/Policies/CommunicationPolicy.php`, righe 24, 37, 45, 53

**Problema:** La policy chiama `$user->hasRole(['admin', 'super-admin'])`. Il metodo `hasRole()` in `User.php` gestisce solo `'super_admin'` (con underscore), non `'super-admin'` (con trattino). Il caso `default:` dell'enum confronta con `user_type->value`, e `UserType::SuperAdmin->value === 'super_admin'`. Quindi `hasRole('super-admin')` restituirà sempre `false` — il SuperAdmin non riesce a creare/modificare/eliminare comunicazioni.

```php
// CommunicationPolicy.php (ERRATO):
$user->hasRole(['admin', 'super-admin'])

// User::hasRole (il match):
'super_admin' => $this->isSuperAdmin(),   // ← con underscore
// 'super-admin' va nel default → false
```

**Fix:** Sostituire `'super-admin'` con `'super_admin'` in tutta la policy, o meglio usare direttamente `$user->isAdmin()`.

---

### BUG-03: N+1 query in `NotificationController::index()` — scrive sul DB per ogni riga

**File:** `app/Http/Controllers/Admin/NotificationController.php`, righe 47-53
**File:** `app/Services/NotificationPreparationService.php`, metodo `updateRecipientInfo()`

**Problema:** `index()` carica tutte le notifiche con `get()`, poi chiama `updateRecipientInfo()` per **ciascuna**. Questo metodo accede a `$notification->tournament->assignments` e poi fa un `$notification->update()` — una scrittura DB per ogni riga della lista. Con 100 notifiche = 100 UPDATE query più eventuali lazy load. Peggio: queste scritture avvengono ad ogni visualizzazione della pagina.

```php
foreach ($allNotifications as $notification) {
    $this->preparationService->updateRecipientInfo($notification); // UPDATE per ogni riga!
}
```

**Fix:** Rimuovere questa chiamata dalla `index()` — i dati di `referee_list` devono essere aggiornati solo quando cambiano le assegnazioni (es. observer su Assignment).

---

### BUG-04: `NotificationTransactionService::prepareAndSend()` — parametro `$data` fantasma

**File:** `app/Services/NotificationTransactionService.php`, righe 92-115

**Problema:** Il metodo `prepareAndSend(TournamentNotification $notification, array $data)` riceve `$data` ma non lo usa mai. Se il chiamante volesse passare metadati o clausole, sarebbero ignorati silenziosamente.

```php
public function prepareAndSend(TournamentNotification $notification, array $data): void
{
    // $data non viene mai usato nel body!
    $documents = $this->documentService->regenerateAllDocuments($notification);
    $this->notificationService->send($notification);
}
```

**Fix:** O usare `$data`, o rimuovere il parametro dall'interfaccia.

---

### BUG-05: N+1 query nel loop statistiche mensili del DashboardController utente

**File:** `app/Http/Controllers/User/DashboardController.php`, righe 84-95

**Problema:** Il loop esegue 12 query DB separate (`count()`) per calcolare le statistiche mensili degli ultimi 12 mesi — una per mese. Dovrebbe usare un'unica query aggregata con `GROUP BY MONTH`.

```php
for ($i = 11; $i >= 0; $i--) {
    $month = Carbon::now()->subMonths($i)->format('Y-m');
    $count = $user->assignments()
        ->whereHas('tournament', fn($q) => $q->where('start_date', 'like', $month.'%'))
        ->count(); // ← 12 query separate!
}
```

---

## SEZIONE 2 — Duplicazioni e Ridondanze

### DUP-01: `prepareNotification()` definita in due Service diversi

**File:** `NotificationService.php` (riga 27) e `NotificationPreparationService.php` (riga 21)

Entrambi i service hanno un metodo `prepareNotification(Tournament $tournament): TournamentNotification` con logica simile ma non identica. Nel controller viene usata solo la versione di `NotificationPreparationService`, ma `NotificationService::prepareNotification()` è accessibile e potrebbe essere chiamata per errore da codice futuro con comportamento differente.

**Differenze:**
- `NotificationService::prepareNotification()` popola `recipients` con gli arbitri correnti
- `NotificationPreparationService::prepareNotification()` popola `referee_list` e `details`

---

### DUP-02: Funzioni helper globali PHP definite due volte

**File:** `app/Helpers/helpers.php` (righe 7-24) e `app/Helpers/RefereeLevelsHelper.php` (righe 187-206)

Le funzioni `referee_levels()`, `normalize_referee_level()`, `referee_level_label()` sono definite identiche in entrambi i file. Entrambi usano `if (! function_exists(...))` quindi non causano crash, ma `helpers.php` esiste solo come ridondanza: serve solo come file di autoload nella bootstrap, ma il contenuto è duplicato. Se entrambi i file venissero caricati (cosa che accade) solo il primo definirà effettivamente le funzioni.

---

### DUP-03: Costruzione destinatari nazionali duplicata in `sendNationalNotification()` e `resendNationalNotification()`

**File:** `NotificationController.php`, righe 682-878 e 325-468

Entrambi i metodi costruiscono manualmente le stesse liste di destinatari con lo stesso pattern: `$toRecipients = []`, `$ccRecipients = []`, poi loop su admin e arbitri. Esiste già `NotificationRecipientBuilder` creato esattamente per questo scopo, ma **non viene mai utilizzato** nel controller nazionale.

Linee di codice duplicato: ~80 righe nella costruzione destinatari, ~40 righe nell'invio email.

---

### DUP-04: `InstitutionalEmail` interrogata due volte nella stessa chiamata

**File:** `app/Services/NotificationPreparationService.php`, righe 162-170

```php
'institutionalEmails' => InstitutionalEmail::where('is_active', true)
    ->orderBy('category')->orderBy('name')->get(),
'groupedEmails' => InstitutionalEmail::where('is_active', true)
    ->orderBy('category')->orderBy('name')->get()   // ← IDENTICA
    ->groupBy('category'),
```

Due query identiche al DB. Basta fare `->get()` una volta e poi `->groupBy()` sulla collection in memoria.

---

### DUP-05: `Tournament::STATUS_*` constants ridondanti con `TournamentStatus` Enum

**File:** `app/Models/Tournament.php`, righe 91-109
**File:** `app/Enums/TournamentStatus.php`

Il model Tournament definisce ancora le costanti `STATUS_DRAFT`, `STATUS_OPEN` ecc. e `STATUSES = [...]` mentre esiste l'enum `TournamentStatus` che è la fonte di verità. Chi usa `Tournament::STATUS_OPEN` bypassa l'enum e potrebbe non beneficiare di future modifiche centralizzate.

---

### DUP-06: `AvailabilityNotificationService` vs logica inline in `AvailabilityController`

**File:** `app/Services/AvailabilityNotificationService.php`
**File:** `app/Http/Controllers/User/AvailabilityController.php`, metodi `sendSeparatedAdminNotifications()`, `collectZoneAdminEmails()`, `collectNationalAdminEmails()`

Esiste un service dedicato per le notifiche di disponibilità, ma il controller re-implementa la stessa logica di separazione zona/nazionale inline con metodi privati. `AvailabilityNotificationService` sembra non essere usato. Vanno confrontati e unificati.

---

## SEZIONE 3 — Inconsistenze

### INC-01: Stringa `'national_admin'` usata direttamente invece dell'Enum in punti critici

**File:** `NotificationController.php` riga 375, `AvailabilityController.php` riga 599

```php
// ERRATO — stringa hardcoded:
\App\Models\User::where('user_type', 'national_admin')  // NotificationController L375
User::where('user_type', 'national_admin')               // AvailabilityController L599

// CORRETTO — dovrebbe usare:
User::where('user_type', UserType::NationalAdmin->value)
```

Nota: `UserType::ZoneAdmin->value` è `'admin'` (non `'zone_admin'`!), quindi queste stringhe hardcoded sono particolarmente rischiose — un refactoring dell'Enum non propagherebbe il cambiamento.

---

### INC-02: Migrazione referee→user incompleta — cartelle di view ibride

**Situazione:**
- Routes `/user/*` gestite da controller in `App\Http\Controllers\User\`
- Ma le views delle disponibilità puntano ancora a `referee/availabilities/` (non `user/availabilities/`)
- `User\DashboardController::index()` renderizza `referee.dashboard`
- La cartella `resources/views/referee/` contiene 4 file ancora attivi
- La cartella `resources/views/user/` contiene solo le nuove viste

Il routing è stato migrato a `/user/` ma le views sono ancora in `referee/`. Funziona ma è confuso e rende impossibile capire cosa è "nuovo" e cosa è "legacy".

---

### INC-03: `$user->user_type` è castato a `UserType` enum, ma il campo `status` di Assignment non è castato

**File:** `app/Models/Assignment.php` (da verificare)
**File:** `app/Models/Tournament.php` — `status` castato a `TournamentStatus` ✓

Assignment ha `role` come stringa ENUM nel DB ma non viene castato nel model. `AssignmentRole::tryFrom($a->role)` funziona, ma `$assignment->role === AssignmentRole::Referee` fallirebbe (oggetto vs stringa).

---

### INC-04: `TournamentNotification` — docblock con campi inesistenti nel DB

**File:** `app/Models/TournamentNotification.php`, docblock PHPDoc

Il docblock lista `@property int $total_recipients` e `@property string $templates_used` e `@property string $error_message` come colonne, ma non esistono come colonne nel DB (sono dentro JSON `details` o `metadata`). Chi legge il docblock crede che siano colonne interrogabili, portando a query `->where('total_recipients', ...)` che fallirebbero.

---

### INC-05: `Tournament::zone()` usa `hasOneThrough` ma il campo `zone_id` è nella tabella tournaments

**File:** `app/Models/Tournament.php`, righe 116-130

Il torneo ha sia un campo `zone_id` diretto nel DB (per performance), sia una relazione `hasOneThrough` che passa per `Club`. Poi c'è un accessor `getZoneIdAttribute()` che cerca di risolvere il conflitto. Questo triplo meccanismo (colonna DB + relazione + accessor) è fragile e causa confusione, specialmente con `$appends = ['zone_id']` che serializza sempre l'accessor.

---

### INC-06: `documents/` e `user/documents/` — due viste per i documenti

**File:** `resources/views/documents/create.blade.php` e `resources/views/documents/index.blade.php`
**File:** `resources/views/user/documents/index.blade.php`

Esiste una cartella `documents/` con viste standalone (forse precedente) e una `user/documents/` con la vista corrente. La cartella `documents/create.blade.php` potrebbe essere dead code.

---

## SEZIONE 4 — Codice Morto / Non Usato

### DEAD-01: `NotificationService::prepareNotification()` — probabilmente non chiamata

La versione di `prepareNotification()` in `NotificationService` non è chiamata dal controller (che usa `NotificationPreparationService`). Va verificato se c'è qualche chiamata residua.

### DEAD-02: `AvailabilityNotificationService` — probabilmente non usato

Il service esiste ma il controller implementa la stessa logica inline. Va cercato chi lo chiama.

### DEAD-03: `resources/views/documents/create.blade.php`

Vista che sembra non essere raggiunta da alcun route dopo la migrazione a `user/documents/`.

### DEAD-04: Route commentata `// require __DIR__.'/admin/reports.php';`

**File:** `routes/web.php` riga ~100
File di route commentato. O esiste ma non si vuole includere, o non esiste più. Va rimosso o ripristinato.

### DEAD-05: `User::CATEGORY_*` constants

**File:** `app/Models/User.php`, righe 88-95
`CATEGORY_MASCHILE`, `CATEGORY_FEMMINILE`, `CATEGORY_MISTO` definite come costanti ma non utilizzate nel model stesso né nei fillable. Il DB ha un campo `gender` con enum `['male', 'female', 'mixed']` — le costanti usano parole italiane mentre il DB usa inglese. Doppio mismatch.

### DEAD-06: User Model — `$fillable` incompleto rispetto al DB

**File:** `app/Models/User.php`

Il DB ha decine di campi che non sono in `$fillable`: `address`, `postal_code`, `tax_code`, `badge_number`, `first_certification_date`, `last_renewal_date`, `expiry_date`, `bio`, `experience_years`, `qualifications`, `languages`, `specializations`, `preferences`, `available_for_international`, `total_tournaments`, `tournaments_current_year`, `last_login_at`, `profile_completed_at`. Questi campi esistono nel DB ma non possono essere assegnati via mass assignment. Può essere intenzionale (sicurezza) ma non è documentato.

---

## SEZIONE 5 — Problemi di Architettura

### ARCH-01: 5 Service per le notifiche senza chiara responsabilità

Il sistema di notifiche è distribuito in:
1. `NotificationService` — invio zonale (mail con allegati DOCX)
2. `NotificationPreparationService` — preparazione e validazione
3. `NotificationTransactionService` — wrapper transazionale
4. `NotificationDocumentService` — gestione documenti
5. `NotificationRecipientBuilder` — costruzione destinatari (mai usato per nazionali!)

E poi il `NotificationController` contiene direttamente la logica dell'invio nazionale (~200 righe). L'architettura originale era corretta, ma l'aggiunta del flusso nazionale è stata fatta inline nel controller invece di estendere i service esistenti.

---

### ARCH-02: Flusso nazionale vs zonale — divergenza crescente senza astrazione comune

Le gare nazionali e zonali seguono flussi diversi ma non hanno un'astrazione comune:
- Zonali: usano `TournamentNotification` con campi `recipients`, `documents`, clausole DOCX
- Nazionali: inviano email raw (`Mail::raw()`) senza DOCX, senza clausole, salvano dati diversi

Questa divergenza non è documentata e rende il codice imprevedibile per chi lo mantiene.

---

### ARCH-03: `metadata` usato come campo multiuso non tipizzato

In `TournamentNotification`, il campo `metadata` viene usato per scopi eterogenei: errori dell'ultimo invio, contatori di successo, flag `is_national`, tipo di notifica, subject e message. Questo rende impossibile validare la struttura e difficile il debugging.

---

## SEZIONE 6 — Suggerimenti Prioritizzati

### Priorità 1 — Fix immediato (bug funzionali)

1. **BUG-01**: Aggiungere `metadata` con `is_national`, `type`, `subject`, `message` nel salvataggio di `sendNationalNotification()` — 5 righe di codice.

2. **BUG-02**: Correggere `CommunicationPolicy` da `'super-admin'` a `'super_admin'` — 4 righe.

3. **BUG-04**: Eliminare il parametro `$data` da `prepareAndSend()` o implementarlo — 1 riga.

### Priorità 2 — Performance e correttezza

4. **BUG-03**: Rimuovere `updateRecipientInfo()` dalla loop in `index()` — usare un Observer su `Assignment` per aggiornare `referee_list` solo quando necessario.

5. **BUG-05**: Sostituire il loop 12-query mensile con una query aggregata GROUP BY.

### Priorità 3 — Pulizia e coerenza

6. **DUP-02**: Eliminare `helpers.php` e lasciare solo le definizioni in `RefereeLevelsHelper.php`.

7. **DUP-04**: Correggere le due query identiche a `InstitutionalEmail` in `loadFormData()`.

8. **INC-01**: Sostituire le stringhe `'national_admin'` con `UserType::NationalAdmin->value`.

9. **INC-02**: Completare la migrazione referee→user spostando le views da `referee/` a `user/`.

10. **DUP-03**: Usare `NotificationRecipientBuilder` in `sendNationalNotification()` e `resendNationalNotification()` eliminando la duplicazione.

### Priorità 4 — Refactoring architetturale

11. **DUP-06**: Verificare se `AvailabilityNotificationService` è usato; se no, eliminarlo o usarlo nel controller.

12. **ARCH-01**: Estrarre la logica nazionale dal controller in un `NationalNotificationService`.

13. **DUP-05**: Deprecare `Tournament::STATUS_*` e `Tournament::STATUSES` in favore dell'enum.

14. **INC-04**: Correggere il docblock di `TournamentNotification` rimuovendo le proprietà false.

---

## Verdetto

**Conviene pulire, non rifare.** La struttura Laravel è corretta e ben organizzata. I problemi sono localizzati in aree specifiche e risolvibili senza toccare l'architettura generale. I bug critici (BUG-01 e BUG-02) sono fix da 5-10 minuti che però hanno impatto funzionale immediato in produzione. Il debito tecnico più pesante è la logica delle notifiche nazionali nel controller (200+ righe che dovrebbero stare in un service dedicato).

**Effort stimato per la pulizia completa:** 3-4 giorni di sviluppo per tutti i punti sopra, oppure 2-3 ore solo per i bug critici (priorità 1).

---

*Report generato da analisi manuale approfondita del codice sorgente — 24 Marzo 2026*
