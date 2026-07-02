# Analisi Ruoli, Copertura Autorizzazioni e Surplus Codice
*Generato il 2026-07-02 — analisi statica (laravel-flow-analyzer) + lettura manuale di route, middleware, scope e controller*

> **AGGIORNAMENTO 2026-07-02 — FIX APPLICATI**
>
> | Item | Fix |
> |---|---|
> | G1 | ✅ Rimosso blocco `tournament-management/types` da routes/admin/tournaments.php — gestione tipi torneo solo via super-admin.php (middleware super_admin) |
> | G2 | ✅ Rimossa "Public Tournament API" da routes/api/internal.php |
> | G3 | ✅ RefereeOrAdmin: checkZoneAccess ora solo per ZoneAdmin (national_admin non più filtrato per zona) |
> | G4 | ✅ Consolidato su toggle-active: rimossi route+metodo `deactivate`; **bonus**: aggiunto `@method('PATCH')` mancante in 3 view (form toggle-active facevano POST su route PATCH → 405 latente) |
> | S1 | ✅ Eliminati middleware ZoneAdmin, RefereeOnly, DocumentAccess |
> | S2 | ✅ Eliminato `TournamentTypeRequest` — regole per schema VECCHIO (short_name, required_referee_level) incompatibili col controller attuale (code, calendar_color): non cablabile, solo dead code |
> | S3 | ✅ Rimossi route+metodo `clubs.export` (irraggiungibile, mai linkata) |
> | S4 | ✅ = G1 |
> | S5 | ✅ Rimossi gruppi vuoti validation/mass-communication |
> | S6 | ✅ Rimosse route legacy `referee.quadranti.*`; test RefereeDashboardAuthTest aggiornato (ora verifica che NON esistano più) |
> | S7 | ✅ Rimossi placeholder reports.dashboard, admin.settings, super-admin.settings.index (+ link navigation); super-admin.users.index ora redirect a admin.users.index |
> | S8 | ⏸ Lasciato (dev routes solo local/staging) |
>
> Verifica eseguita: nessun riferimento orfano a route/classi rimosse in app/, resources/views/, routes/, tests/. PHP non disponibile nel sandbox: eseguire in locale `php artisan route:list` e `php artisan test` per conferma finale.

## Architettura ruoli

4 ruoli (`App\Enums\UserType`): `referee`, `admin` (zonale), `national_admin` (CRC), `super_admin`.

Middleware registrati (`bootstrap/app.php`):

| Alias | Classe | Chi passa |
|---|---|---|
| `admin_or_superadmin` | AdminOrSuperAdmin | admin zonale, national_admin, super_admin |
| `super_admin` | SuperAdmin | solo super_admin |
| `referee_or_admin` | RefereeOrAdmin | tutti con user_type valido + check ownership (referee) e zona (admin) |
| — (globale web) | EnsureUserIsActive | logout forzato utenti disattivati |

Visibilità dati: **le route admin sono condivise tra i 3 tipi di admin**; la differenza tra zonale, nazionale e super è applicata dagli scope, non dal routing. Single source of truth: `App\Support\TournamentVisibility` + `User::scopeVisible` + `Club::scopeVisible`.

| Entità | Zone Admin | National Admin (CRC) | Super Admin |
|---|---|---|---|
| Tornei | solo propria zona (via club.zone_id) | solo `tournamentType.is_national = true` | tutti |
| Arbitri | solo propria zona | solo livelli nazionali/internazionali | tutti |
| Circoli | solo propria zona | tutti | tutti |
| Notifiche | circolo + arbitri (zonale) | crc_referees + zone_observers (nazionale) | entrambe |

---

## Funzioni per ruolo

### UTENTE (arbitro) — `/user/*`, middleware `auth` + `referee_or_admin`

| Area | Azioni | Route file |
|---|---|---|
| Dashboard | vista personale (assegnazioni, disponibilità) | referee/dashboard.php |
| Disponibilità | lista, vista tornei, dichiara singola, dichiara batch, rimuovi, calendario | user/availability.php |
| Quadranti | simulatore orari partenza, upload Excel giocatori, effemeridi alba/tramonto | user/quadranti.php |
| Curriculum | vista curriculum personale | user/curriculum.php |
| Documenti | lista, download | user/documents.php |
| Comunicazioni | lista, dettaglio (sola lettura) | user/communications.php |
| Tornei | index, calendario, dettaglio (filtrati per visibilità) | web.php |
| Profilo | edit, update, delete account | web.php |
| Federgolf | ricerca gare, iscritti (AJAX) | web.php |

### ADMIN (zonale) — `/admin/*`, middleware `auth` + `admin_or_superadmin`, dati filtrati su propria zona

| Area | Azioni | Route file |
|---|---|---|
| Dashboard | overview + quick-stats AJAX | web.php |
| Tornei | CRUD completo, calendario, cambio status, disponibilità per torneo | admin/tournaments.php |
| Assegnazioni | CRUD, assegnazione multipla arbitri a torneo, rimozione, conferma | admin/assignments.php |
| Validazione assegnazioni | conflitti, requisiti mancanti, arbitri over/under-assigned, fix conflitti | admin/assignments.php |
| **Notifiche** (processo core) | lista, form preparazione, genera/upload/download/elimina documenti (convocazione, lettera circolo), clausole, send, resend, elimina singola/tutte per torneo | admin/notifications.php + admin/tournaments.php |
| Notifica nazionale | `send-national-notification` (senza allegati, per gare is_national) | admin/tournaments.php |
| Utenti | CRUD unificato (arbitri+admin), toggle attivo — con abort 403 interni per gerarchie | admin/users.php |
| Circoli | CRUD, deactivate, toggle attivo, export CSV (⚠ irraggiungibile, v. sotto) | admin/clubs.php |
| Carriera arbitri | curricula, storico per arbitro/anno, archiviazione anno, add/remove tornei storici, batch entry | admin/career-history.php + referee-career.php |
| Comunicazioni | crea, pubblica, scadenza, elimina | admin/communications.php |
| Documenti | CRUD, upload, download | admin/documents.php |
| Statistiche | dashboard, disponibilità, assegnazioni, tornei, arbitri, zone, performance, export CSV, API | admin/statistics.php |
| Import Federgolf | wizard Comitato di Gara (ricerca FIG, fetch comitato, import su conferma) | web.php |

### ADMIN NAZIONALE (CRC) — nessuna route esclusiva

Stesse route dell'admin zonale. Differenze solo di scope: vede tornei nazionali e arbitri di livello nazionale/internazionale, tutti i circoli. Flusso notifica diverso (destinatari crc_referees + zone_observers, `is_national` fonte di verità).

### SUPERADMIN — tutto quanto sopra senza filtri + `/super-admin/*` e `/aruba-admin/*`

| Area | Azioni | Route file |
|---|---|---|
| Tipi torneo | CRUD, toggle attivo | super-admin.php |
| Email istituzionali | CRUD, toggle attivo, export | super-admin.php |
| Clausole notifica | CRUD, toggle, riordino, preview | super-admin.php |
| Monitoring | dashboard, metriche realtime, history, performance, health check, uptime, log per livello/stats, gestione cache | super-admin/monitoring.php |
| Aruba tools | cache, optimize, pulizia asset, phpinfo, log, permessi, composer, backup/restore DB, storage-link, security | maintenance.php |
| Placeholder | users.index, settings.index (viste vuote) | super-admin.php |

---

## Verifica copertura autorizzazioni

Lo script segnala 34 anomalie "critiche" `missing_authorization`: **la maggior parte sono falsi positivi** — non vede il middleware di gruppo (`['auth','super_admin']` su maintenance.php e super-admin.php copre tutte le route Aruba/monitoring/email/clausole; le route auth POST login/register/forgot-password sono volutamente pubbliche).

Gap reali dopo verifica manuale:

### 🔴 G1 — Tipi torneo modificabili da qualsiasi admin
`routes/admin/tournaments.php` espone `tournament-management/types` (resource + toggle-active) sotto il solo `admin_or_superadmin`. `TournamentTypeController` non ha alcun check interno: `store()`/`update()` usano `$request->validate()` inline. Esiste `TournamentTypeRequest` con `authorize() => isSuperAdmin()` ma **non è mai usato dal controller**. Risultato: un admin zonale può creare/modificare/eliminare/disattivare i tipi torneo nazionali via URL diretto (le view linkano solo `super-admin.tournament-types.*`, quindi il buco è invisibile in UI ma raggiungibile).
**Fix consigliato**: eliminare il blocco `tournament-management` da routes/admin/tournaments.php (superficie duplicata mai linkata) e/o iniettare `TournamentTypeRequest` in store/update + authorize su destroy/toggleActive.

### 🔴 G2 — API pubblica espone dati arbitri
`routes/api/internal.php`: `GET /api/tournaments/{tournament}` (senza auth) fa `->load(['assignments.user'])`. `User::$hidden` copre solo password e remember_token → email, telefono, zona ecc. degli arbitri esposti a chiunque. Nessun riferimento a `api/tournaments` in views/JS → endpoint probabilmente inutilizzato.
**Fix consigliato**: rimuovere il blocco pubblico o spostarlo sotto `auth` limitando i campi (`->only([...])`).

### 🟡 G3 — National admin con zone_id viene filtrato per zona
`RefereeOrAdmin::checkZoneAccess()` applica il blocco zona anche a `UserType::NationalAdmin` se ha `zone_id` valorizzato — in conflitto con la regola "il CRC vede tutti i tornei nazionali a prescindere dalla zona". Se in produzione i national_admin hanno zone_id null, innocuo; altrimenti 403 errati su risorse fuori zona.
**Verifica dati consigliata**: `User::where('user_type','national_admin')->whereNotNull('zone_id')->count()`.

### 🟡 G4 — Doppia semantica disattivazione circoli
`ClubController` ha sia `deactivate` sia `toggleActive`, entrambe usate nelle view (table-actions-club le usa entrambe). Ridondanza funzionale, rischio incoerenza UI.

---

## Surplus di codice

| # | Elemento | Stato | Azione |
|---|---|---|---|
| S1 | Middleware `ZoneAdmin`, `RefereeOnly`, `DocumentAccess` | Mai registrati in bootstrap/app.php, mai referenziati | Eliminare |
| S2 | `TournamentTypeRequest` | Mai usato (causa anche G1) | Usarlo (fix G1) o eliminare |
| S3 | `admin.clubs.export` | Definita DOPO `/{club}` → `/clubs/export` matcha il binding e va in 404; nessuna view la usa | Eliminare o spostare prima delle route dinamiche |
| S4 | Blocco `tournament-management.types.*` in routes/admin/tournaments.php | Mai linkato (view usano solo super-admin.*); duplica super-admin.php | Eliminare (chiude G1) |
| S5 | Gruppi route vuoti `validation` e `mass-communication` in routes/admin/users.php | Closure vuote | Eliminare |
| S6 | Route legacy `referee.quadranti.*` (routes/referee/dashboard.php) | Nessun riferimento in views/app (referee.dashboard invece è usato dal redirect dashboard) | Eliminare il solo blocco quadranti |
| S7 | Placeholder: `reports.dashboard`, `admin.settings`, `super-admin.users.index`, `super-admin.settings.index` | View "placeholder", ma linkate in navigation.blade.php | Implementare o rimuovere route + link |
| S8 | routes/dev/* (~1.300 righe, view-helpers.php 671) | Solo local/staging, ok ma peso rilevante | Valutare snellimento |

## Nota metodologica

Report script grezzo (67 route parse — non segue i `require` modulari, quindi i moduli admin/user sono stati mappati a mano): `docs/` non incluso; le tabelle sopra derivano dalla lettura diretta di routes/, app/Http/Middleware, app/Support/TournamentVisibility, app/Models e controller. Suite test presente (tests/Feature Admin-Auth-Notifications-Referee, tests/Unit con regression test audit precedenti): i gap G1 e G2 non risultano coperti da test — candidati per regression test dopo il fix.
