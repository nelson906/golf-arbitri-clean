# Audit Report — Golf Arbitri (golf-arbitri-clean)
Data: 2026-03-21
Stack: Laravel 12 · PHP 8.2 · Tailwind CSS 3 · Alpine.js 3 · React 19 · FullCalendar 6 · Vite 6
Hosting target: **Aruba Virtual Host (no SSH)**

---

## Sommario Esecutivo

Il progetto è un'applicazione Laravel 12 ben strutturata a livello di backend (models puliti, Enum tipizzati, Service layer). La stratificazione è concentrata nel layer frontend/layout e in alcune scelte architetturali accumulate durante iterazioni successive: **due sistemi di navigazione paralleli, un layout (app.blade.php) che non carica i CSS compilati, Alpine.js caricato tre volte, e file debug/temporanei rimasti in produzione**. Ci sono anche problemi specifici per il deployment su Aruba (code email mai inviate, password in chiaro nel .env, script pubblicamente accessibile).

Il backend PHP è in buono stato e non richiede riscrittura. Il frontend (layout + JS) necessita di una pulizia sostanziale ma circoscritta.

---

## Indice di Stratificazione

| Area | Score | Motivazione |
|------|-------|-------------|
| CSS | 2/10 | Solo `@tailwind` directives, nessun CSS custom. Pulito. |
| JavaScript | 6/10 | 20 console.log in prod, ~70 righe di legacy dead code, logica errore ipertrofica per 3 casi identici |
| Blade/HTML | 7/10 | Due sistemi di navigazione paralleli, layout con strategia asset diversa, referee/user views duplicate |
| PHP/Backend | 4/10 | Notification service iperframmentato (4 file), route placeholder, maintenance route temporanea con ID hardcoded |
| Architettura/Config | 6/10 | QUEUE_CONNECTION=database senza daemon su Aruba, mail commentata, file pubblici pericolosi |

---

## Problemi Critici

### C1 — Alpine.js caricato TRE volte
**File:** `resources/views/layouts/admin.blade.php` righe 6-9
**File:** `resources/views/layouts/app.blade.php` righe 5-8
**File:** `resources/js/app.js` righe 3-6

In entrambi i layout viene caricato Alpine Core + Focus Plugin da CDN (`unpkg.com`), **e in più** `app.js` importa Alpine da npm e chiama `Alpine.start()`. Risultato: ogni pagina che usa uno di questi layout *più* l'output di Vite inizializza Alpine tre volte. Questo causa conflitti di stato e comportamenti imprevedibili sui componenti `x-data`.

```html
<!-- admin.blade.php righe 6-9 — DA RIMUOVERE -->
<script defer src="https://unpkg.com/@alpinejs/focus@3.x.x/dist/cdn.min.js"></script>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
```

### C2 — `app.blade.php` non carica gli asset compilati
**File:** `resources/views/layouts/app.blade.php`

Il layout `app.blade.php` (usato da 9 pagine: referee/availabilities, user/curriculum, user/quadranti, user/documents, user/communications) carica Tailwind da CDN ma **non ha `@vite`**. Queste pagine:
- Non ricevono il CSS compilato di produzione
- Non ricevono `app.js` (React + Alpine da npm)
- Usano la versione CDN di Tailwind, che genera il CSS al runtime nel browser (40KB di engine JS)
- Sono incompatibili con la policy CSP che potrebbe essere attivata su Aruba

Il layout `admin.blade.php` (usato da 62 pagine) invece usa correttamente `@vite`.

```html
<!-- app.blade.php riga 20 — CDN Tailwind, NO @vite -->
<script src="https://cdn.tailwindcss.com"></script>
```

### C3 — `public/fix_autoload.php` accessibile pubblicamente
**File:** `public/fix_autoload.php`

Uno script di utilità per la pulizia della cache (workaround per l'assenza di SSH su Aruba) è rimasto nella cartella `public/`. Chiunque conosca l'URL può:
- Svuotare config/route/view cache dell'intera applicazione
- Visualizzare il percorso assoluto del progetto sul server
- Verificare la scrivibilità dei path interni

Lo script stesso avverte "Elimina questo file dopo l'uso" ma non è stato eliminato. Da rimuovere immediatamente e sostituire con la sezione Aruba Admin dedicata.

### C4 — Route di manutenzione con utente hardcoded, mai rimosse
**File:** `routes/maintenance.php` righe 13-50

Tre route temporanee per setup iniziale sono ancora attive in produzione:
- `/admin/check-disabled-functions` — mostra funzioni PHP disabilitate
- `/admin/run-migration` — esegue `php artisan migrate --force`
- `/admin/set-super-admin` — promuove un utente a super admin

L'accesso è limitato a `auth()->id() !== 14` (ID hardcoded, non una costante). Queste route NON devono esistere in un ambiente di produzione.

### C5 — Mail configuration commentata, password in chiaro nel .env
**File:** `.env` righe 62-68

Tutta la configurazione SMTP è commentata, quindi l'app non può inviare email. Peggio, la password è visibile nel file:

```
# MAIL_PASSWORD=Gl!lfr097*c;
```

Questo `.env` è stato committato o condiviso con la password in chiaro. La password va cambiata e il `.env` va escluso dal repository con `.gitignore` (che già esiste ma il file .env è presente nella cartella).

### C6 — QUEUE_CONNECTION=database senza daemon su Aruba
**File:** `.env` riga 50, `config/queue.php`

La queue usa il driver `database`. Su Aruba virtual host non è possibile eseguire `php artisan queue:work` come processo persistente. Tutti i job in coda (email di notifica assegnazioni, email disponibilità) vengono inseriti nella tabella `jobs` ma **non vengono mai processati** senza un worker attivo.

Per Aruba è necessario usare `QUEUE_CONNECTION=sync` (invio sincrono, blocca la request) oppure configurare un cron job che esegua `queue:work --once` a intervalli.

---

## Ridondanze

### R1 — Due sistemi di navigazione paralleli e disconnessi

L'applicazione ha **due diversi layout e due diverse navigazioni** che devono essere mantenuti in sincronia:

1. `layouts/admin.blade.php` (274 righe) — sidebar laterale blu, usata da 62 view admin
2. `layouts/app.blade.php` + `layouts/navigation.blade.php` (424 righe) — barra orizzontale, usata da 9 view referee/user

Quando si aggiunge una voce di menu, va aggiunta in **almeno 3 punti** (admin sidebar, navigation desktop, navigation mobile). Questo ha già causato discrepanze: "Storico Carriera Arbitri" appare nella sidebar admin ma non nella navigation; "Validazione Assegnazioni" compare solo nella sidebar.

### R2 — Viste `referee/` e `user/` duplicate

Esistono due alberi di view per la stessa funzionalità:
- `resources/views/referee/availabilities/` (3 file) — usano `layouts/app`
- `resources/views/user/` (5+ cartelle con file analoghi) — usano `layouts/app`

Con route legacy `/referee/*` → redirect a `/user/*`. La migrazione è a metà: le view `referee/` continuano ad esistere e a essere usate, mentre `user/` è il percorso canonico.

### R3 — Due controller/route per la carriera degli arbitri

Esistono due moduli sovrapposti:
- `routes/admin/referee-career.php` + `RefereeCareerController` → `GET /admin/referees/curricula`, `GET /admin/referees/{id}/curriculum`
- `routes/admin/career-history.php` + `CareerHistoryController` (676 righe) → `/admin/career-history/*`

Il primo mostra il curriculum corrente; il secondo gestisce lo storico annuale. Sono concettualmente connessi ma implementati come moduli separati senza riferimento reciproco, con sidebar admin che li mostra come voci distinte con la stessa icona (📊).

### R4 — Notification service frammentato in 4 file

La logica di notifica è distribuita su:
- `NotificationService.php` (386 righe) — orchestrazione principale
- `NotificationPreparationService.php` (219 righe) — preparazione dati
- `NotificationTransactionService.php` (208 righe) — transazioni invio
- `NotificationRecipientBuilder.php` (260 righe) — costruzione lista destinatari

Totale: **1073 righe** per quello che funzionalmente è "prepara e invia una email di notifica torneo". La frammentazione non segue un pattern DDD chiaro ma sembra il risultato di refactoring iterativi su un singolo service originale.

### R5 — CalendarErrorHandler triplicato in app.js

`app.js` (355 righe) ha un oggetto `CalendarErrorHandler` con metodi `logError`, `createErrorDisplay`, `validateCalendarData`, `createLoadingState`. Questi metodi vengono chiamati in modo identico tre volte consecutive (AdminCalendar, RefereeCalendar, PublicCalendar) con copy-paste dello stesso try/catch. Tutta questa logica potrebbe essere estratta in una funzione `mountCalendar(containerId, Component, calendarDataKey, type)` da ~20 righe.

---

## Codice Morto

### D1 — Funzione `testAlpine()` nel layout admin
**File:** `resources/views/layouts/admin.blade.php` righe 14-24

Una funzione JavaScript di debug per testare Alpine.js è rimasta nel `<head>` del layout admin. Non viene mai chiamata.

```html
{{-- Alpine.js test --}}
<script>
    function testAlpine() {
        return { open: false, toggle() { this.open = !this.open; } }
    }
</script>
```

### D2 — 20 `console.log` in produzione nel bundle app.js

`app.js` contiene 20 istruzioni `console.log/error` con emoji (🔧, 👨‍⚖️, 🌐, 📅, 📊, ✅, ❌) che vengono bundlate nell'asset di produzione. Queste espongono informazioni interne agli utenti e inquinano la console del browser.

### D3 — Codice legacy per container ID non più esistenti (~70 righe)
**File:** `resources/js/app.js` righe 263-325

Il blocco "LEGACY SUPPORT" cerca `tournament-calendar-root` e `availability-calendar-root` — ID che non esistono in nessuna view Blade attuale. Questo codice non viene mai eseguito.

### D4 — `web.php.backup` committato nel repository
**File:** `routes/web.php.backup` (174 righe)

Una copia backup del file routes principale è presente nella cartella `routes/` e probabilmente nel repository git. Non ha alcun effetto funzionale (non viene caricato) ma genera confusione e aumenta la superficie di manutenzione.

### D5 — Route e view `reports` registrate ma non implementate
**File:** `routes/web.php` riga 54-57

Una route `/reports` che restituisce `admin.placeholder` è registrata e presente nel codice ma il link nel menu è commentato. Idem per `/admin/settings`.

### D6 — Colonne `users` mai usate nell'applicazione
**File:** `database/migrations/0001_01_01_000000_create_users_table.php`

La migration crea colonne che non compaiono nel `$fillable` del modello User né in nessun form Blade: `address`, `postal_code`, `tax_code`, `badge_number`, `bio`, `experience_years`, `qualifications` (JSON), `languages` (JSON), `specializations` (JSON), `preferences` (JSON), `available_for_international`, `total_tournaments`, `tournaments_current_year`. Quest'ultime due sono counter denormalizzati che non vengono aggiornati dall'applicazione.

---

## Inconsistenze

### I1 — Emoji duplicate nella sidebar admin
**File:** `resources/views/layouts/admin.blade.php`

- 📋 usato sia per "Curriculum" (riga 132) che per "Assegnazioni" (riga 140)
- 📊 usato per "Validazione Assegnazioni" (riga 147), "Statistiche" (riga 156), "Storico Carriera Arbitri" (riga 164)

Tre voci con la stessa icona creano ambiguità visiva.

### I2 — Active route check errato per Comunicazioni (mobile)
**File:** `resources/views/layouts/navigation.blade.php` riga 88 e riga 336

Per il menu Comunicazioni in admin, il controllo active è `routeIs('communications.*')` (senza prefisso `admin.`), mentre tutte le route sono registrate come `admin.communications.*`. Il link è visivamente mai "attivo" quando si è nella sezione comunicazioni.

### I3 — Dev routes caricate in due blocchi `if` separati
**File:** `routes/web.php` righe 171-178

```php
if (app()->environment(['local', 'staging'])) {
    require __DIR__.'/dev/view-preview.php';
    require __DIR__.'/dev/view-test-all.php'; // ⚠️ AGGIUNGI
}
// ...
if (app()->environment(['local', 'staging'])) {  // stesso blocco duplicato
    require __DIR__.'/dev/view-routes.php';
}
```

Due blocchi condizionali identici che potrebbero essere uno solo. Il commento `// ⚠️ AGGIUNGI` è rimasto dopo l'intervento.

### I4 — Placeholder routes per SuperAdmin ancora visibili
**File:** `routes/super-admin.php` righe 16, 34

Le route `super-admin.users.index` e `super-admin.settings.index` restituiscono solo una view placeholder. Compaiono entrambe nella navigazione mobile (navigation.blade.php righe 293, 305) come link attivi ma portano a pagine vuote.

### I5 — Doppio controllo `isNationalAdmin` nel dropdown utente
**File:** `resources/views/layouts/admin.blade.php` righe 226-227 e `navigation.blade.php` righe 225-226

```php
@elseif(Auth::user()->isNationalAdmin() && !Auth::user()->isSuperAdmin())
```

Il `!isSuperAdmin()` è ridondante: `isNationalAdmin()` ritorna true solo per `UserType::NationalAdmin`, che è diverso da `UserType::SuperAdmin`. Il controllo `isSuperAdmin()` nel blocco precedente garantisce già la mutua esclusione.

---

## Problemi Specifici per Aruba Virtual Host

### A1 — `exec()` può essere disabilitata su Aruba
**File:** `app/Http/Controllers/SuperAdmin/ArubaToolsController.php`
**File:** `app/Helpers/SystemOperations.php`

`ArubaToolsController` usa `exec()` per eseguire Composer, leggere il carico server, listare processi PHP. Su molti piani Aruba, `exec`, `shell_exec`, `system` e `passthru` sono nella lista `disable_functions` di PHP. Il controller stesso ha un endpoint di diagnosi (`/aruba-admin/composer/diagnostic`) che testa i percorsi di Composer via `exec()` — già un indizio che questa funzionalità è problematica.

### A2 — Storage symlink vs cartella fisica
Il `public/.htaccess` reindirizza tutto a `public/`. Su Aruba, il deploy avviene copiando i file via FTP nella document root (es. `public_html/`). La struttura Laravel prevede che `public/` sia la document root, ma spesso su Aruba il progetto viene caricato con `public/` come sottocartella di `public_html/`. Il `.htaccess` radice gestisce il redirect, ma il symlink `storage:link` potrebbe non funzionare se `public_html/` e la cartella del progetto sono separati.

### A3 — Session driver `database` e carico sulla tabella sessions
Con `SESSION_DRIVER=database`, ogni request lancia almeno una query SELECT e una INSERT/UPDATE sulla tabella `sessions`. Su Aruba con connessioni MySQL limitate, questo può diventare un collo di bottiglia sotto carico.

---

## Suggerimenti (in ordine di priorità)

1. **[URGENTE]** Eliminare `public/fix_autoload.php` dal server e dal repository
2. **[URGENTE]** Rimuovere le route di manutenzione temporanee da `maintenance.php` (o sposta dentro `if (app()->environment('local'))`)
3. **[URGENTE]** Cambiare la password SMTP Aruba (era visibile in `.env`), configurare correttamente il mailer
4. **[URGENTE]** Cambiare `QUEUE_CONNECTION=sync` per Aruba, oppure configurare un cron job `queue:work --once`
5. **[ALTA]** Unificare i layout: aggiungere `@vite` in `app.blade.php` e rimuovere Tailwind CDN
6. **[ALTA]** Rimuovere i tag CDN Alpine da entrambi i layout (è già in `app.js` via npm)
7. **[ALTA]** Eliminare `testAlpine()` dal layout admin
8. **[MEDIA]** Rimuovere `web.php.backup` dal repository
9. **[MEDIA]** Migrare completamente da `referee/` a `user/`: cancellare le 4 view `referee/`, aggiornare le route, rimuovere la sezione "LEGACY COMPATIBILITY"
10. **[MEDIA]** Eliminare i 20 `console.log` da `app.js` (o usare una variabile `window.APP_DEBUG`)
11. **[MEDIA]** Rimuovere il codice LEGACY SUPPORT da `app.js` (~70 righe)
12. **[MEDIA]** Correggere il check active di Comunicazioni in `navigation.blade.php`
13. **[BASSA]** Rimuovere le colonne inutilizzate dalla migration `users` (o documentarle per uso futuro)
14. **[BASSA]** Consolidare i due blocchi `if (app()->environment(['local', 'staging']))` in `web.php`
15. **[BASSA]** Aggiornare le emoji nella sidebar admin per eliminare duplicati

---

## Verdetto

**Non rifare da zero. Pulire e risanare.**

Il backend (models, enums, service layer, controller structure) è ben fatto e non mostra segni di stratificazione problematica. Le 80.000 righe totali includono una quantità importante di logica di business corretta che sarebbe rischiosa da riscrivere.

I problemi identificati sono **tutti chirurgici**: si risolvono con interventi localizzati su 5-6 file principali (i due layout, app.js, maintenance.php, .env, routes/web.php). L'unico intervento strutturale che vale la pena valutare a medio termine è la **unificazione del sistema di navigazione** (un solo layout con sidebar/topbar condizionale in base al ruolo).

Effort stimato per la pulizia urgente: **4-8 ore di sviluppo**. Effort per l'unificazione del sistema di navigazione: **1-2 giorni**.
