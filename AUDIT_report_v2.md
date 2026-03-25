# Audit Report — Golf Arbitri Clean
**Data:** 24 marzo 2026
**Analista:** Claude (site-analyzer skill)
**Codebase:** Laravel 11, PHP, Alpine.js + React.js + Vanilla JS, Tailwind CSS

---

## Sommario Esecutivo

Il progetto è un'applicazione Laravel funzionante e strutturalmente solida, costruita incrementalmente in più sessioni di sviluppo (probabilmente con assistenza AI). La base è buona: enum tipati, servizi dedicati, trait condivisi, route modulari. Tuttavia, ogni iterazione ha lasciato tracce visibili: middleware inutilizzati, directory views fuori-posto, controller duplicati, e una proliferazione di classi Mail quasi identiche. Il problema principale non è che il codice sia rotto — è che non si capisce con certezza quali parti siano "quella giusta" tra versioni diverse dello stesso concetto.

**Scala di urgenza:** Media-alta per i problemi critici (2 bug latenti), bassa per le ridondanze (pulizia senza rompere nulla).

---

## Indice di Stratificazione

| Area | Score | Motivazione |
|------|-------|-------------|
| PHP/Backend (Controllers) | 6/10 | Duplicazione DocumentController, 3 DashboardController, TournamentController x2 |
| Routing | 5/10 | Route legacy referee, route senza middleware auth, placeholder non rimosse |
| Servizi/Mail | 7/10 | 5 Notification services + 10 classi Mail, alcune praticamente identiche |
| Views/Blade | 5/10 | 3 layout diversi, directory views fuori struttura, views orfane |
| JavaScript | 4/10 | 3 framework JS diversi (Alpine + React + Vanilla), quadranti ben isolato |
| Database/Migrazioni | 2/10 | Schema solido, solo 8 migrazioni pulite (punto di forza) |
| Architettura generale | 5/10 | Buona astrazione trait/enum, ma inconsistenza naming e middleware morti |

---

## 🚨 Problemi Critici

### 1. Route `/referee/dashboard` senza middleware `auth`
**File:** `routes/referee/dashboard.php`

```php
// NESSUN middleware auth!
Route::get('/referee/dashboard', [DashboardController::class, 'index'])->name('referee.dashboard');
Route::prefix('/referee/quadranti')->name('referee.quadranti.')->group(function () {
    Route::get('/', [QuadrantiController::class, 'index'])->name('index');
    Route::post('/upload-excel', [QuadrantiController::class, 'uploadExcel'])->name('upload-excel');
});
```

Queste route sono definite fuori da qualsiasi gruppo `middleware(['auth'])`. In teoria un utente non autenticato potrebbe accedere a `/referee/dashboard` e `/referee/quadranti`. Il controller in sé presumibilmente verifica `auth()->user()`, ma il redirect al login potrebbe non avvenire in modo sicuro.

**Fix:** Aggiungere `->middleware(['auth', 'referee_or_admin'])` o spostare nel gruppo corretto.

---

### 2. Query raw che usa stringa `'admin'` invece dell'enum
**File:** `NotificationController.php` riga 350, `AvailabilityController.php` riga 579

```php
// FRAGILE: dipende dal valore stringa del DB, non dall'enum
$zoneAdmins = User::where('user_type', 'admin')->...
```

Il problema è che `UserType::ZoneAdmin = 'admin'` funziona oggi, ma se il valore DB viene mai rinominato o se qualcuno si confonde tra `'admin'` (ZoneAdmin) e `'national_admin'`/`'super_admin'`, queste query silenziosamente restituiranno dati sbagliati.

**Fix:**
```php
User::where('user_type', UserType::ZoneAdmin->value)->...
```

---

### 3. `config/golf.php` dichiara ruolo `'assistant' => 'Assistente'` che non esiste
**File:** `config/golf.php`, sezione `assignment_roles`

```php
'assignment_roles' => [
    'default' => 'Arbitro',
    'director' => 'Direttore di Torneo',
    'referee' => 'Arbitro',
    'assistant' => 'Assistente',   // ← NON ESISTE nell'enum AssignmentRole né nel DB!
    'observer' => 'Osservatore',
],
```

L'enum `AssignmentRole` conosce solo `Referee`, `TournamentDirector`, `Observer`. Il ruolo `Assistente` non esiste nel database (`enum('Direttore di Torneo','Arbitro','Osservatore')`). Se qualcosa nel codice legge questa config per popolare un select, può assegnare un ruolo invalido che poi causerà un errore di DB a runtime.

**Fix:** Rimuovere `'assistant'` dalla config o allinearlo all'enum.

---

## Ridondanze

### R1. DocumentController duplicato (Admin vs User) — 610 righe totali
**File:** `Admin/DocumentController.php` (349 righe) e `User/DocumentController.php` (261 righe)

Tre metodi privati sono copiati identici in entrambe le classi:
- `getCorrectMimeType()` — stessa implementazione
- `determineDocumentType()` — stessa implementazione
- `authorizeDocumentAccess()` — stessa logica di base

**Fix:** Estrarre in un `DocumentService` o in un trait `HasDocumentOperations`.

---

### R2. Tre DashboardController per un redirect
- `Controllers/DashboardController.php` (34 righe): solo un `if isAdmin() → redirect`
- `Controllers/Admin/DashboardController.php` (54 righe): dashboard admin reale
- `Controllers/User/DashboardController.php` (153 righe): dashboard arbitro reale

Il controller root non fa nulla di utile che non si possa fare inline in una route closure o spostando la logica nella route stessa.

---

### R3. Proliferazione classi Mail (10 classi, alcune quasi identiche)
Tre classi di disponibilità hanno identica struttura (52 righe ciascuna):
- `RefereeAvailabilityConfirmation.php`
- `NationalAvailabilityNotification.php`
- `ZonalAvailabilityNotification.php`

Stessa cosa per:
- `BatchAvailabilityNotification.php` vs `BatchAvailabilityAdminNotification.php`

**Fix:** Una singola `AvailabilityNotificationMail` con un parametro `$type` o `$recipientRole` risolverebbe 4-6 classi in una.

---

### R4. RefereeLevelsHelper = wrapper di un wrapper
**Catena:** `helpers.php` → `RefereeLevelsHelper` → `RefereeLevel` (enum)

Il file `helpers.php` definisce 3 funzioni globali (`referee_levels()`, `normalize_referee_level()`, `referee_level_label()`) che chiamano `RefereeLevelsHelper`, che a sua volta è segnato come `@deprecated` e delega all'enum `RefereeLevel`.

Tre livelli di indirection per arrivare a `RefereeLevel::selectOptions()`.

---

### R5. Cinque servizi Notification (1673 righe totali)
Per gestire un singolo flusso "crea notifica + invia email + allega documento" esistono:
- `NotificationService.php` (386 righe) — logica principale
- `NotificationTransactionService.php` (208 righe) — wrappa in transaction
- `NotificationPreparationService.php` (219 righe) — prepara i dati
- `NotificationRecipientBuilder.php` (260 righe) — costruisce destinatari
- `NotificationDocumentService.php` (360 righe) — gestisce allegati

La frammentazione è comprensibile per separare responsabilità, ma il confine tra `NotificationService` e `NotificationTransactionService` non è chiaro: perché il "transaction" è un servizio separato?

---

## Codice Morto

### M1. Due middleware registrati ma senza alias (non utilizzabili come route middleware)
**File:** `app/Http/Middleware/RefereeOnly.php` e `ZoneAdmin.php`

In `bootstrap/app.php` sono registrati solo 3 alias:
```php
'admin_or_superadmin' => AdminOrSuperAdmin::class,
'super_admin'         => SuperAdmin::class,
'referee_or_admin'    => RefereeOrAdmin::class,
```

`RefereeOnly` e `ZoneAdmin` esistono come classi ma **non hanno alias** → non possono essere usati nelle route. Sono dead code a meno che non vengano chiamati direttamente (improbabile).

`DocumentAccess` ha la stessa situazione (nessun alias) ma viene simulato come metodo privato nei controller.

---

### M2. Directory views legacy/orfane
Tre directory di views fuori dalla struttura standard `admin/` e `user/`:

**`resources/views/referee/`** (4 file)
La migrazione da `referee` a `user` è parziale: le views stanno ancora in `referee/` ma i controller sono già in `User/`. Mantiene inconsistenza: `referee.dashboard` punta a `view('referee.dashboard')`, mentre le views curriculum/quadranti/documents stanno in `user/`.

**`resources/views/tournaments/`** (3 file: index, calendar, show)
Vengono servite dalla route generica `/tournaments` in `web.php` tramite `TournamentController` (radice). Struttura non standard per Laravel.

**`resources/views/documents/`** (2 file: index, create)
Non è chiaro quale controller le usi o se siano ancora attive.

---

### M3. Route `reports.dashboard` — feature non implementata
**File:** `routes/web.php`, `layouts/navigation.blade.php`

```php
Route::get('reports', function () {
    return view('admin.placeholder', ['title' => 'Reports']);
})->name('reports.dashboard');
```

I link a questa route nel file di navigazione sono **commentati**:
```blade
{{-- <x-nav-link :href="route('reports.dashboard')" :active="request()->routeIs('reports.*')">
```

La route esiste, i link no: è una feature fantasma.

---

### M4. Route super-admin placeholder
Due route in `super-admin.php` puntano a view placeholder:
- `super-admin.users.index` → `view('admin.placeholder', ['title' => 'Gestione Utenti Sistema'])`
- `super-admin.settings.index` → `view('admin.placeholder', ['title' => 'Impostazioni Sistema'])`

Queste compaiono nella sidebar solo per il SuperAdmin ma non hanno implementazione.

---

### M5. Dev routes (671 righe di helpers mock)
**File:** `routes/dev/view-helpers.php` (671 righe)

Un sistema completo di mock class per preview delle views in sviluppo. Include `DebugCollector`, classi mock per tutti i modelli, sistemi di rilevamento views orfane. Molto utile in sviluppo, ma 671 righe è un peso significativo da mantenere.

---

## Inconsistenze

### I1. `UserType::ZoneAdmin = 'admin'` — naming fuorviante
Il caso dell'enum si chiama `ZoneAdmin` ma il suo valore DB è `'admin'`. Questo significa che `user_type = 'admin'` nel database si riferisce a un Amministratore Zonale, non a un "admin generico". Chiunque legga il DB senza conoscere l'enum può confondersi — come già accaduto nelle 2 query raw citate sopra.

---

### I2. Tre layout Blade eterogenei
- **`layouts.admin`** (257 righe): sidebar, Alpine.js, Vite, emoji nel menu
- **`layouts.app`** (41 righe): top navigation, Vite, standard Breeze
- **`aruba-admin.layout`** (141 righe): suo proprio layout, carica **Tailwind via CDN** anziché Vite, include Font Awesome da CDN

Il layout aruba-admin è un sistema completamente separato che bypassa il build pipeline. In produzione potrebbe avere versioni Tailwind diverse dal resto dell'app.

---

### I3. Tre framework JavaScript in parallelo
- **Alpine.js** per interattività nelle views (form, modal, dropdown)
- **React.js** per i 3 componenti calendario (AdminCalendar, RefereeCalendar, PublicCalendar)
- **Vanilla JS puro** per il modulo quadranti (2001 righe in 4 file)

Non è necessariamente sbagliato, ma il modulo quadranti è completamente isolato e non usa nessuno dei due framework scelti per il resto dell'app. Se Alpine è già disponibile, si potrebbe usare anche lì.

---

### I4. Migrazione da `referee/*` a `user/*` incompiuta
Il sistema ha iniziato come "Referee Portal" e sta migrando verso "User Portal" più generico, ma la migrazione è parziale:

| Cosa | Dove si trova |
|------|---------------|
| Views disponibilità | `views/referee/availabilities/` (legacy) |
| Views dashboard arbitro | `views/referee/dashboard.blade.php` (legacy) |
| Views curriculum, docs, quadranti | `views/user/` (nuovo) |
| Controller disponibilità | `Controllers/User/AvailabilityController` (nuovo) |
| Route compatibility `/referee/*` | Redirigono a user, ma `/referee/dashboard` ancora servita direttamente |

---

### I5. Views `tournaments/index.blade.php` con logica di routing nell'UI
```blade
@extends(auth()->user()->isAdmin() ? 'layouts.admin' : 'layouts.app')
```

Una view che decide autonomamente quale layout usare in base al tipo utente è un antipattern: la scelta del layout dovrebbe avvenire nella route o nel controller, non nella view.

---

### I6. `letter_templates` nel DB senza Model Laravel
La migrazione `2025_08_29_000007_create_letter_templates_table.php` crea la tabella `letter_templates`, ma non esiste nessun file `app/Models/LetterTemplate.php`. La tabella è accessibile solo via query raw o DB facade.

---

## Suggerimenti Prioritizzati

### Priorità ALTA (bug/sicurezza)
1. **Aggiungere `middleware(['auth'])` alle route `routes/referee/dashboard.php`** — rischio accesso non autenticato
2. **Sostituire le 2 query raw con `UserType::ZoneAdmin->value`** in NotificationController e AvailabilityController
3. **Rimuovere `'assistant'` da `config/golf.php`** o aggiungere il caso all'enum AssignmentRole

### Priorità MEDIA (manutenibilità)
4. **Completare la migrazione `referee → user`**: spostare le 4 views da `views/referee/` a `views/user/`, aggiornare il controller User/DashboardController per puntare alla nuova posizione, rimuovere le route legacy
5. **Estrarre la logica comune di DocumentController** in un trait o service
6. **Registrare o eliminare** `RefereeOnly` e `ZoneAdmin` middleware (hanno alias o vanno rimossi)
7. **Allineare il layout aruba-admin** per usare Vite invece di CDN Tailwind

### Priorità BASSA (cleanup)
8. **Consolidare le classi Mail** da 10 a ~5 usando parametri invece di classi separate
9. **Rimuovere il DashboardController radice** (solo redirect) e usare una route closure
10. **Eliminare `RefereeLevelsHelper` e `helpers.php`** dopo aver sostituito le chiamate con l'enum direttamente
11. **Decidere il destino di `reports.dashboard`** e `super-admin.settings`: implementare o rimuovere
12. **Creare `LetterTemplate` Model** o rimuovere la tabella se non usata

---

## Verdetto

**Conviene pulire, non rifare.**

La base architettonica è solida: enum tipati, trait per logica condivisa, service layer ben articolato, migrazioni coerenti, test presenti. I problemi sono di *stratificazione incrementale* — strati successivi che non hanno rimosso quello precedente — non di design fondamentalmente sbagliato.

Il refactoring è praticabile in modo chirurgico, modulo per modulo, senza dover fermare lo sviluppo. I problemi critici (3) si risolvono in poche ore. Le ridondanze (5) richiedono 1-2 giorni ciascuna. Le inconsistenze si possono affrontare su un arco di settimane.

**Stima effort totale:** 5-8 giorni di lavoro per portare il codebase da score medio 5/10 a 8/10.
