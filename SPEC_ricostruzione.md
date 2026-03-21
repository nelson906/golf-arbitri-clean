# Specifiche per Ricostruzione — Golf Arbitri
Data: 2026-03-21
Versione originale: Laravel 12 · PHP 8.2 · Tailwind CSS 3 · Alpine.js 3 · React 19 · FullCalendar 6
Hosting target: **Aruba Virtual Host (no SSH, no daemon processes)**

---

## Obiettivo del sito

**Golf Arbitri** è un gestionale web per la sezione regole della federazione golf italiana. Permette di:
- Gestire l'anagrafica degli arbitri di golf e la loro carriera
- Pubblicare e gestire il calendario tornei
- Raccogliere le disponibilità degli arbitri per ogni torneo
- Assegnare gli arbitri ai tornei
- Inviare notifiche email a circoli, arbitri e email istituzionali
- Produrre documenti PDF (convocazioni, lettere ai circoli)
- Visualizzare statistiche su arbitri, tornei, zone

**Utenti**: Arbitri + Amministratori zonali/nazionali/super admin. Sistema chiuso, no registrazione pubblica.

---

## Stack Raccomandato

```
Backend:   Laravel 12 + PHP 8.2
Frontend:  Tailwind CSS 3 (compilato via Vite) + Alpine.js 3 (npm) + React 19 (solo calendari)
PDF:       barryvdh/laravel-dompdf
Email:     Laravel Mail (driver SMTP Aruba, invio sincrono per no-SSH)
Queue:     SYNC (niente daemon su Aruba — email inviate inline)
Session:   database (compatibile Aruba)
Cache:     file (più semplice di database su Aruba, meno contesa)
Asset:     Vite con output in public/build/ — un solo @vite per tutti i layout
Auth:      Laravel Breeze (già presente, tenere)
```

### Struttura cartelle suggerita (invariata rispetto all'originale)
```
app/
├── Enums/          (UserType, RefereeLevel, TournamentStatus, AssignmentRole)
├── Http/
│   ├── Controllers/
│   │   ├── Admin/       (routes admin)
│   │   ├── User/        (routes arbitro autenticato)
│   │   ├── SuperAdmin/  (routes super admin)
│   │   └── Auth/        (breeze)
│   ├── Middleware/
│   └── Requests/
├── Models/
├── Services/        (NotificationService, DocumentGenerationService, ecc.)
├── Mail/
└── Helpers/
resources/
├── views/
│   ├── layouts/
│   │   ├── admin.blade.php     (UNICO layout con sidebar — include @vite)
│   │   └── guest.blade.php
│   ├── components/
│   ├── admin/
│   ├── user/                   (UNICO prefisso, niente referee/)
│   ├── super-admin/
│   ├── aruba-admin/
│   ├── auth/
│   └── emails/
├── css/app.css
└── js/
    ├── app.js
    └── quadranti/
routes/
├── web.php
├── admin/
├── user/
├── super-admin/
├── api/
└── auth.php
```

---

## Mappa pagine/route

### Area pubblica (solo login)
| Route | View | Descrizione |
|-------|------|-------------|
| `GET /` | welcome | Home pubblica con link login |
| `GET /login` | auth/login | Form login |
| `GET /forgot-password` | auth/forgot-password | Reset password |

### Dashboard (tutti gli utenti autenticati)
| Route | Redirect |
|-------|---------|
| `GET /dashboard` | Redirect intelligente per ruolo: admin → `/admin/dashboard`, referee → `/user/availability` |

### Tornei (tutti gli utenti autenticati)
| Route | View | Descrizione |
|-------|------|-------------|
| `GET /tournaments` | tournaments/index | Lista tornei visibili (filtrata per ruolo/zona/livello) |
| `GET /tournaments/calendar/view` | tournaments/calendar | Calendario React FullCalendar |
| `GET /tournaments/{id}` | tournaments/show | Dettaglio torneo |

### Profilo
| Route | Metodo | Descrizione |
|-------|--------|-------------|
| `/profile` | GET/PATCH/DELETE | Modifica dati personali, cambio password, cancellazione account |

---

### Area User (arbitro autenticato, middleware `referee_or_admin`)

#### Disponibilità
| Route | Descrizione |
|-------|-------------|
| `GET /user/availability` | Lista tornei con pulsante dichiara/revoca disponibilità |
| `POST /user/availability/{tournament}` | Dichiara disponibilità (crea record Availability) |
| `DELETE /user/availability/{tournament}` | Revoca disponibilità |
| `GET /user/availability/calendar` | Calendario personale con disponibilità e assegnazioni |
| `GET /user/availability/tournaments` | Vista tabellare tornei disponibili |

#### Curriculum
| Route | Descrizione |
|-------|-------------|
| `GET /user/curriculum` | Visualizza curriculum (dati personali + storico tornei arbitrati) |

#### Quadranti (simulatore tempi di partenza golf)
| Route | Descrizione |
|-------|-------------|
| `GET /user/quadranti` | Interfaccia simulatore (SPA con Alpine/JS puro, nessuna API backend) |

#### Documenti
| Route | Descrizione |
|-------|-------------|
| `GET /user/documents` | Lista documenti personali (convocazioni, lettere) |
| `GET /user/documents/{id}/download` | Download documento |

#### Comunicazioni
| Route | Descrizione |
|-------|-------------|
| `GET /user/communications` | Lista comunicazioni ricevute dall'admin |
| `GET /user/communications/{id}` | Dettaglio comunicazione |

---

### Area Admin (middleware `admin_or_superadmin`)

#### Dashboard Admin
| Route | Descrizione |
|-------|-------------|
| `GET /admin/dashboard` | Statistiche riepilogative: tornei imminenti, arbitri disponibili, assegnazioni mancanti |
| `GET /admin/quick-stats` | JSON per aggiornamento live delle stat (AJAX) |

#### Tornei
| Route | Descrizione |
|-------|-------------|
| `GET /admin/tournaments` | Lista tornei con filtri (stato, tipo, zona, data) |
| `GET /admin/tournaments/create` | Form creazione torneo |
| `POST /admin/tournaments` | Salva nuovo torneo |
| `GET /admin/tournaments/{id}` | Dettaglio torneo (assegnazioni, disponibilità, notifiche) |
| `GET /admin/tournaments/{id}/edit` | Form modifica |
| `PATCH /admin/tournaments/{id}` | Aggiorna torneo |
| `DELETE /admin/tournaments/{id}` | Elimina torneo |
| `GET /admin/tournaments/calendar` | Calendario admin con FullCalendar (React) |
| `GET /admin/tournaments/{id}/availabilities` | Lista arbitri disponibili per un torneo |

#### Arbitri (Utenti)
| Route | Descrizione |
|-------|-------------|
| `GET /admin/users` | Lista arbitri con filtri (zona, livello, tipo) |
| `GET /admin/users/create` | Form creazione arbitro |
| `POST /admin/users` | Salva nuovo arbitro |
| `GET /admin/users/{id}` | Profilo arbitro |
| `GET /admin/users/{id}/edit` | Modifica dati arbitro |
| `PATCH /admin/users/{id}` | Aggiorna arbitro |
| `DELETE /admin/users/{id}` | Disattiva arbitro |
| `GET /admin/referees/curricula` | Lista curricula di tutti gli arbitri |
| `GET /admin/referees/{id}/curriculum` | Curriculum singolo arbitro |

#### Assegnazioni
| Route | Descrizione |
|-------|-------------|
| `GET /admin/assignments` | Lista assegnazioni con filtri |
| `GET /admin/assignments/create` | Form creazione assegnazione |
| `POST /admin/assignments` | Assegna arbitro a torneo |
| `GET /admin/assignments/{id}/edit` | Modifica assegnazione |
| `PATCH /admin/assignments/{id}` | Aggiorna assegnazione |
| `DELETE /admin/assignments/{id}` | Rimuove assegnazione |
| `GET /admin/tournaments/{id}/assign-referees` | Interfaccia assegnazione guidata (vede disponibili + filtra) |
| `GET /admin/assignment-validation` | Dashboard validazione: conflitti, sotto/sovrassegnati, requisiti mancanti |
| `GET /admin/assignment-validation/conflicts` | Lista conflitti di calendario |
| `GET /admin/assignment-validation/missing-requirements` | Tornei con requisiti non soddisfatti |
| `GET /admin/assignment-validation/overassigned` | Arbitri con troppi tornei |
| `GET /admin/assignment-validation/underassigned` | Arbitri sotto utilizzati |

#### Circoli
| Route | Descrizione |
|-------|-------------|
| `GET/POST /admin/clubs` | Lista + creazione circoli golf |
| `GET/PATCH/DELETE /admin/clubs/{id}` | Dettaglio + modifica + eliminazione |
| `GET /admin/clubs/{id}/show` | Vista dettaglio con tornei ospitati |

#### Comunicazioni Admin
| Route | Descrizione |
|-------|-------------|
| `GET/POST /admin/communications` | Lista + creazione comunicazioni verso arbitri |
| `GET /admin/communications/{id}` | Dettaglio comunicazione |

#### Notifiche Tornei
| Route | Descrizione |
|-------|-------------|
| `GET /admin/tournament-notifications` | Lista notifiche con stato (bozza/inviata) |
| `GET /admin/tournament-notifications/{id}` | Dettaglio notifica |
| `GET /admin/tournament-notifications/{id}/edit` | Form preparazione notifica |
| `PATCH /admin/tournament-notifications/{id}` | Aggiorna bozza notifica |
| `POST /admin/notifications/prepare` | Prepara notifica per un torneo (crea bozza) |
| `POST /admin/notifications/{id}/send` | Invia notifica (email a club + arbitri + istituzionali) |

#### Documenti Admin
| Route | Descrizione |
|-------|-------------|
| `GET /admin/documents` | Lista documenti generati |
| `GET /admin/documents/create` | Form upload/generazione documento |
| `POST /admin/documents` | Carica documento |
| `GET /admin/documents/{id}/download` | Download documento |
| `DELETE /admin/documents/{id}` | Elimina documento |

#### Statistiche
| Route | Descrizione |
|-------|-------------|
| `GET /admin/statistics` | Dashboard statistiche con grafici |
| `GET /admin/statistics/arbitri` | Statistiche per arbitro |
| `GET /admin/statistics/assegnazioni` | Statistiche assegnazioni |
| `GET /admin/statistics/disponibilita` | Statistiche disponibilità |
| `GET /admin/statistics/tornei` | Statistiche tornei |
| `GET /admin/statistics/zone` | Statistiche per zona |
| `GET /admin/statistics/performance` | Performance comparative |

#### Storico Carriera Arbitri
| Route | Descrizione |
|-------|-------------|
| `GET /admin/career-history` | Lista arbitri con riepilogo carriera |
| `GET /admin/career-history/{id}` | Storico completo arbitro per anno |
| `GET /admin/career-history/{id}/year/{year}` | Modifica tornei di un anno specifico |
| `POST /admin/career-history/{id}/add-tournament` | Aggiunge torneo allo storico |
| `POST /admin/career-history/{id}/batch-save` | Salvataggio batch tornei |
| `GET /admin/career-history/archive` | Form archiviazione anno corrente |
| `POST /admin/career-history/archive` | Processa archiviazione anno |

---

### Area Super Admin (middleware `super_admin`)

| Route | Descrizione |
|-------|-------------|
| `GET /super-admin/tournament-types` | Gestione tipi/categorie torneo |
| `GET /super-admin/institutional-emails` | Gestione email istituzionali (destinatari fissi per notifiche) |
| `GET /super-admin/clauses` | Gestione clausole notifiche (testi predefiniti) |
| `GET /super-admin/monitoring/*` | Dashboard monitoraggio sistema (health, logs, metrics) |

### Area Aruba Admin (middleware `super_admin`)

| Route | Descrizione |
|-------|-------------|
| `GET /aruba-admin` | Dashboard con stato sistema |
| `GET /aruba-admin/cache` | Gestione cache (clear config/route/view) |
| `GET /aruba-admin/permissions` | Verifica e fix permessi storage |
| `GET /aruba-admin/phpinfo` | PHPInfo |
| `GET /aruba-admin/logs` | Lettura log Laravel |
| `GET /aruba-admin/composer` | Gestione Composer (dump-autoload) |
| `GET /aruba-admin/database` | Backup/restore database |
| `GET /aruba-admin/monitoring` | Monitoring server (carico, processi, storage) |
| `GET /aruba-admin/security` | Scansione file sensibili e sospetti |

---

## Componenti condivisi

### Layout unico `layouts/admin.blade.php`
Usato da tutte le pagine post-login (sia admin che referee). Include:
- `@vite(['resources/css/app.css', 'resources/js/app.js'])` — UNICA inclusione asset
- Alpine.js importato solo via npm (rimuovere CDN)
- Sidebar laterale blu visibile solo a admin/superadmin
- Top bar con dropdown utente e nome ruolo
- Flash messages (success/error) globali
- `@stack('scripts')` per JS inline delle singole view

### Sidebar admin
Condizionale su `isSuperAdmin()` / `isAdmin()`. Voci con icone SVG (non emoji) per disambiguità visiva. Voci attive determinate da `request()->routeIs()`.

### Componenti Blade (`resources/views/components/`)
- `action-buttons` — gruppo pulsanti azione (edit/delete/show) standardizzato
- `filter` + `filter-field` — form filtri riutilizzabile
- `status-badge` — badge colorato per stato torneo/assegnazione
- `table-header` — intestazione tabella con ordinamento
- `table-actions`, `table-actions-club`, `table-actions-referee` — azioni contestuali (da unificare in un solo componente con parametri)
- `modal` — modal Alpine.js generico
- `referee-list` — lista arbitri con checkbox per selezione multipla
- Standard Breeze: `button`, `danger-button`, `primary-button`, `secondary-button`, `input-error`, `input-label`, `text-input`, `dropdown`, `dropdown-link`, `nav-link`

---

## Schema dati

### Tabelle principali

```
zones
  id, name, code (unique), email, phone, description
  is_national (bool), is_active (bool)

users
  id, name, first_name, last_name, email (unique), password
  user_type (enum: super_admin|national_admin|admin|referee)
  zone_id (FK zones, nullable)
  referee_code (nullable), level (enum: Aspirante|1_livello|Regionale|Nazionale|Internazionale|Archivio)
  gender (enum: male|female|mixed)
  club_member, city, phone, is_active (bool), notes
  email_verified_at

tournament_types
  id, name, short_name (unique), description
  color (hex), requires_national_referee (bool)
  competence (enum: national|zonal|both)
  is_active (bool)

tournaments
  id, name, start_date, end_date, location
  club_id (FK clubs), zone_id (FK zones, nullable)
  tournament_type_id (FK tournament_types)
  status (enum: draft|published|assigned|completed|cancelled)
  max_referees (int), min_referees (int)
  is_national (bool), notes
  [+ campi colore, visibilità, metadati organizzativi]

clubs
  id, name, city, address, email, phone
  zone_id (FK zones, nullable)
  is_active (bool), notes

assignments
  id, tournament_id (FK), user_id (FK)
  role (enum: chief_referee|assistant_referee|observer)
  notes, confirmed_at

availabilities
  id, user_id (FK), tournament_id (FK)
  created_at, updated_at
  (unique: user_id + tournament_id)

notifications (TournamentNotification)
  id, tournament_id (FK, unique)
  status (enum: pending|draft|sent)
  recipients (JSON: {club: bool, referees: [ids], institutional: [email_ids]})
  documents (JSON: {convocation: filename, club_letter: filename})
  metadata (JSON: clausole selezionate, note libere)
  sent_at

notification_clauses
  id, title, body (text), order (int)
  type (enum: general|convocation|club_letter)
  is_active (bool)

notification_clause_selections
  id, notification_id (FK), clause_id (FK)

institutional_emails
  id, name, email (unique), role_description
  zone_id (FK, nullable — null = nazionale)
  is_active (bool)

documents
  id, name, type, path (storage path)
  user_id (FK, nullable), tournament_id (FK, nullable)
  notification_id (FK, nullable)
  mime_type, size, is_public (bool)

communications
  id, subject, body (text)
  sender_id (FK users),
  recipient_type (enum: all|zone|level|individual)
  recipient_ids (JSON, nullable)
  sent_at, created_at

referee_career_history
  id, user_id (FK, unique)
  data (JSON: {2024: {tournaments: [{...}], total_days: N}, 2025: {...}})
  updated_at

letter_templates
  id, name, type, content (text), is_active (bool)
```

### Relazioni chiave
- `User` → `Zone` (belongs to, nullable)
- `User` → `Assignment[]` (has many)
- `User` → `Availability[]` (has many)
- `User` ↔ `Tournament` (many-to-many via assignments)
- `Tournament` → `Club` (belongs to)
- `Tournament` → `Zone` (belongs to, nullable)
- `Tournament` → `TournamentType` (belongs to)
- `Tournament` → `TournamentNotification` (has one)
- `TournamentNotification` → `NotificationClauseSelection[]` (has many)

---

## Specifiche per area

### Visibilità tornei per ruolo
La regola di visibilità è implementata via `TournamentVisibility` + `HasZoneVisibility` trait:

| Ruolo | Vede |
|-------|------|
| SuperAdmin | Tutti i tornei |
| NationalAdmin | Tornei nazionali (`is_national = true`) |
| ZoneAdmin | Tornei della propria zona |
| Referee Nazionale/Internazionale | Tutti (tornei zonali della propria zona + tornei nazionali) |
| Referee Regionale/sotto | Solo tornei della propria zona |

### Disponibilità
- Un arbitro dichiara disponibilità per un torneo specifico
- La disponibilità è semplice (disponibile/non disponibile) — non ci sono gradi di disponibilità
- Alla scadenza per le disponibilità, l'admin vede la lista di chi è disponibile e procede con le assegnazioni
- Alla dichiarazione di disponibilità, viene inviata email di conferma all'arbitro
- Esiste anche un sistema di notifica batch: quando si vuole raccogliere disponibilità per un insieme di tornei, si invia una email massiva agli arbitri

### Assegnazioni
- Un torneo ha un numero minimo e massimo di arbitri
- Un arbitro può avere ruolo chief_referee o assistant_referee
- Il sistema di validazione rileva:
  - Conflitti di calendario (stesso arbitro assegnato a due tornei sovrapposti)
  - Tornei con requisiti non soddisfatti (es. serve un nazionale, non ce n'è uno assegnato)
  - Arbitri sovrassegnati (troppe giornate in un periodo)
  - Arbitri sottoutilizzati
- Il modello `AssignmentValidationService` implementa queste logiche

### Sistema notifiche tornei
Il flusso è:
1. Admin sceglie "Prepara notifica" per un torneo
2. Il sistema crea una bozza `TournamentNotification` con documenti PDF precompilati (convocazione + lettera circolo)
3. Admin seleziona clausole (testi predefiniti), può modificare destinatari
4. Admin invia: il sistema invia email a club, arbitri assegnati, email istituzionali
5. **Su Aruba: tutto in modo sincrono** — niente queue

### Quadranti (simulatore tempi partenza)
Funzionalità standalone senza API backend:
- L'utente inserisce: numero buca di partenza, ora di partenza primo gruppo, intervallo tra gruppi, numero di gruppi
- Il simulatore calcola e visualizza i tempi di partenza stimati per ogni buca
- Implementato in ~2000 righe di JS puro (resources/js/quadranti/)
- La view Blade carica il file compilato `quadranti-DklDbtEf.js` via Vite
- Nessuna interazione col database

### Storico Carriera
- Ogni arbitro ha un record `referee_career_history` con JSON contenente tornei arbitrati per anno
- I dati vengono caricati manualmente dall'admin (batch entry) o aggiunti automaticamente dalle assegnazioni
- A fine anno, l'admin può "archiviare" l'anno: congela i dati e prepara il nuovo anno
- Il curriculum arbitro (vista user + admin) mostra questi dati in forma tabellare

---

## Design System

### Colori

| Token | Hex | Uso |
|-------|-----|-----|
| Primary (sidebar) | `#1e40af` (blue-800) | Sidebar admin, bottoni primari |
| Primary hover | `#1d4ed8` (blue-700) | Hover sidebar |
| Primary active | `#1e3a8a` (blue-900) | Link attivo sidebar |
| Success | `bg-green-100 text-green-700` | Flash message success, badge completato |
| Error | `bg-red-100 text-red-700` | Flash message error, validazione |
| Warning | `bg-yellow-100 text-yellow-700` | Alert warning |
| Info | `bg-blue-50 text-blue-700` | Informativi |
| Page bg | `bg-gray-100` | Sfondo pagina |
| Content bg | `bg-white` | Card, tabelle |

I colori dei tipi torneo sono configurabili nel database (campo `color` di `tournament_types`) e vengono applicati dinamicamente via `TournamentColorService`.

### Tipografia
- Font: Figtree (400, 500, 600) da bunny.net — caricato nel layout
- Dimensioni: standard Tailwind (text-sm per tabelle, text-base per body, text-xl/2xl per titoli pagina)

### Componenti UI ricorrenti

**Tabelle admin**: struttura fissa con `<thead>` colorato (`bg-gray-50`), righe alternate, paginazione Tailwind customizzata. Filtri tramite GET su form superiore alla tabella.

**Card statistiche** (dashboard): griglia 4 colonne su desktop, 1 su mobile. Ogni card mostra: icona emoji, numero grande, etichetta, trend vs periodo precedente.

**Form**: label + input stile Breeze. Validazione errori inline con `<x-input-error>`. Submit button a destra.

**Modal**: Alpine.js con `x-show` + `x-transition`. Overlay scuro. Usato per conferma eliminazione, anteprima documenti.

**Calendario**: React + FullCalendar 6. Tre varianti (Admin, Referee, Public) montate su container `#admin-calendar-root`, `#referee-calendar-root`, `#public-calendar-root`.

### Breakpoint responsive
Standard Tailwind: `sm: 640px`, `md: 768px`, `lg: 1024px`, `xl: 1280px`. Il layout admin è desktop-first (sidebar fissa). Su mobile la sidebar è nascosta (non implementato menu hamburger per admin — da completare).

---

## Dipendenze raccomandate

### PHP (composer.json)
```json
{
  "require": {
    "php": "^8.2",
    "laravel/framework": "^12.0",
    "laravel/breeze": "^2.3",
    "laravel/sanctum": "^4.2",
    "laravel/tinker": "^2.9",
    "barryvdh/laravel-dompdf": "^3.1",
    "maatwebsite/excel": "^3.1",
    "phpoffice/phpword": "^1.4"
  }
}
```
**phpword** è usato per generare documenti Word oltre ai PDF. **maatwebsite/excel** per export dati.

### JavaScript (package.json)
```json
{
  "dependencies": {
    "@fullcalendar/core": "^6.1",
    "@fullcalendar/daygrid": "^6.1",
    "@fullcalendar/interaction": "^6.1",
    "@fullcalendar/list": "^6.1",
    "@fullcalendar/react": "^6.1",
    "@fullcalendar/timegrid": "^6.1",
    "react": "^19",
    "react-dom": "^19"
  },
  "devDependencies": {
    "@tailwindcss/forms": "^0.5",
    "alpinejs": "^3.4",
    "axios": "^1.7",
    "laravel-vite-plugin": "^1.2",
    "vite": "^6.0",
    "tailwindcss": "^3.1",
    "postcss": "^8.4",
    "autoprefixer": "^10.4"
  }
}
```

**Non includere**: CDN Tailwind, CDN Alpine (già in npm).

---

## Note implementative

### Aruba Virtual Host — Vincoli specifici

1. **No SSH**: tutte le operazioni di manutenzione (cache, migrate, ottimizzazione) devono avvenire tramite browser o script PHP accessibili via URL protetto da autenticazione super admin. La sezione `/aruba-admin` gestisce questo. Mantenere `ArubaToolsController`.

2. **No queue daemon**: usare `QUEUE_CONNECTION=sync`. Tutte le email vengono inviate inline durante la request. Se il sistema diventa troppo lento per batch di email grandi, considerare l'invio via cron PHP (`queue:work --once`) schedulato tramite il pannello Aruba.

3. **exec() potenzialmente disabilitata**: il wrapper `SystemOperations::composerDumpAutoload()` usa `exec()`. Su Aruba potrebbe non funzionare. Prevedere un fallback che mostri istruzioni manuali all'utente invece di fallire silenziosamente.

4. **Storage symlink**: su Aruba, `public_html/` è spesso la root servita. Se il progetto Laravel vive in una sottocartella (`public_html/golf/`), il `.htaccess` radice reindirizza alla sottocartella `public/`. Il symlink `storage` deve puntare correttamente. L'`ArubaToolsController` include già test e creazione del symlink.

5. **Assicurarsi che `bootstrap/cache/` e `storage/` siano scrivibili** (chmod 775) — altrimenti Laravel non può creare i file di cache. L'`ArubaToolsController` include il fix permessi.

6. **HTTPS**: il `.htaccess` radice forza HTTPS. Su Aruba con certificato SSL gratuito è corretto.

7. **`fix_autoload.php`**: non committarlo mai. Se serve una copia di emergenza, caricarla via FTP, usarla, poi eliminarla immediatamente.

8. **Vite in produzione**: su Aruba non si può eseguire `npm run build` direttamente. Il build deve essere fatto in locale e i file `public/build/` caricati via FTP insieme al deploy. Ricordarsi di committare `public/build/` oppure di includere lo step di build nel processo di deploy.

### Autenticazione e ruoli

- **Unico campo ruolo**: `users.user_type` (enum). Non usare Spatie Permission o altri pacchetti — i 4 ruoli sono stabili e hardcoded negli Enum.
- **Middleware**: `admin_or_superadmin`, `super_admin`, `referee_or_admin`, `referee_only`, `zone_admin` già implementati. Mantenerli.
- **Registrazione**: disabilitata in produzione (o gestita solo da super admin). La route `/register` esiste ma deve essere inaccessibile pubblicamente tramite policy o middleware.

### Gestione email

- Configurare SMTP Aruba in `.env` con `MAIL_MAILER=smtp`, host `smtps.aruba.it`, porta 465, SSL.
- Non committare mai `.env` con credenziali. Usare `.env.example` senza valori sensibili.
- I mail Mailable sono in `app/Mail/`: `AssignmentNotification`, `RefereeAssignmentMail`, `ClubNotificationMail`, `InstitutionalNotificationMail`, `RefereeAvailabilityConfirmation`, ecc.

### Sicurezza da implementare

- Rimuovere route di debug e maintenance temporanee prima del deploy
- Assicurarsi che `/admin/*` rispetti il middleware di zona (un admin zonale non deve vedere dati di altre zone)
- Verificare che `DocumentAccess` middleware impedisca download di documenti da parte di utenti non autorizzati
- Aggiungere CSRF su tutte le form (già gestito da Laravel, verificare che `@csrf` sia presente ovunque)

### Testing

Il progetto ha PHPUnit configurato. Le factory esistono per: User, Tournament, Club, TournamentType, Zone, Assignment. Nessun test scritto allo stato attuale (directory `tests/` con struttura base). Prima di ogni deploy significativo, aggiungere almeno test di smoke per login e navigazione.

### Dati di seed

- `CoreDataSeeder` — zone, tipi torneo base, utenti di test
- `NotificationClauseSeeder` — clausole standard per le notifiche
- `ClubsTableSeeder` — circoli golf italiani
- `UsersTableSeeder` — utenti con diversi ruoli per testing

Eseguire in ordine con `php artisan db:seed` (o via `/aruba-admin` in produzione).
