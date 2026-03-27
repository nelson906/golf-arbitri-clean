# Laravel Flow Analysis — Golf Arbitri
*Generato il: 2026-03-25 — Analisi su Laravel 12*

> **Nota metodologica**: questo report combina analisi statica automatica (63 route scansionate) con lettura manuale del codice dei controller, service e trait principali. Le sezioni "Scopo" descrivono il significato funzionale del codice nel contesto del dominio (gestione arbitri di golf), non solo le operazioni tecniche.

---

## Riepilogo

| Metrica | Valore |
|---------|--------|
| Route analizzate | 63 |
| Controller coinvolti | 15 (+ 10 modulari non esposti alle route principali) |
| Servizi applicativi | 11 |
| Modelli principali | 15 |
| Moduli applicativi | 8 |
| Anomalie totali | 43 |
| Anomalie critiche (🔴) | 31 |
| Anomalie false positive (stimate) | ~20 (protette da middleware) |

---

## Architettura e Ruoli Utente

L'applicazione implementa un sistema di gestione arbitri di golf con quattro ruoli distinti:

- **SuperAdmin**: accesso totale al sistema, gestisce la configurazione globale (tipi di torneo, email istituzionali, clausole di notifica, strumenti server).
- **NationalAdmin (CRC)**: gestisce tornei nazionali e arbitri di livello nazionale/internazionale.
- **ZoneAdmin (SZR)**: gestisce tornei e arbitri della propria zona geografica.
- **Referee/User (Arbitro)**: dichiara disponibilità, consulta calendario e tornei, gestisce il proprio curriculum.

La separazione di visibilità tra ruoli è centralizzata nel trait `HasZoneVisibility`, che viene iniettato in quasi tutti i controller.

### Workflow principale

```
Arbitro dichiara disponibilità
        ↓
Admin (SZR/CRC) consulta disponibilità aggregate
        ↓
Admin assegna arbitri ai tornei con ruoli specifici
        ↓
Sistema genera documenti DOCX (convocazione + lettera circolo)
        ↓
Notifiche email → Arbitri + Circolo ospitante + Email istituzionali
```

---

## Flussi per Modulo

---

### 1. Autenticazione

*Sorgente: `routes/auth.php` — Controller: `Auth\*`*

#### GET/POST `/register`, `/login`, `/forgot-password`, `/reset-password`

**Scopo**: Gestione completa del ciclo di autenticazione. Registrazione nuovi utenti (probabilmente gestita solo dagli admin in produzione, dato che è un sistema chiuso), login, reset password via email e verifica email. Usa i controller standard di Laravel Breeze/Fortify senza personalizzazioni rilevanti.

**Sequenza di chiamate**:
- Login: Route → `AuthenticatedSessionController::store()` → `Auth::attempt()` → redirect dashboard
- Password reset: Route → `PasswordResetLinkController::store()` → Mail → link token → `NewPasswordController::store()` → `Hash::make()`

**Middleware**: nessuno (pubbliche), oppure `auth` per le route protette come conferma password.

**Anomalie rilevate**: nessuna rilevante — pattern standard Laravel.

---

### 2. Dashboard e Routing per Ruolo

*Sorgente: `routes/web.php` — Controller: `DashboardController`, `Admin\DashboardController`, `User\DashboardController`*

#### GET `/dashboard`

**Scopo**: Punto di ingresso post-login. Il `DashboardController` principale funziona solo come dispatcher intelligente: legge il `user_type` (castato a enum `UserType`) e reindirizza verso la dashboard appropriata — admin se il tipo ha flag `isAdmin()`, arbitro altrimenti. La dashboard admin mostra statistiche aggregate (tornei, arbitri, assegnazioni, circoli) con filtro automatico per zona/ruolo.

**Sequenza di chiamate**:
Route → `DashboardController::index()` → verifica `user_type` → redirect a `admin.dashboard` o `referee.dashboard`

Per admin:
`Admin\DashboardController::index()` → query su `Tournament`, `User`, `Assignment`, `Club` filtrate per zona → view `admin.dashboard`

**Dati in transito**:
- **Output admin**: `stats` con conteggi e 5 assegnazioni recenti, filtrate per zona (ZoneAdmin) o per tornei nazionali (NationalAdmin).

**Anomalie rilevate**: il `DashboardController` principale non ha query proprie — è un puro router, quindi le anomalie rilevate automaticamente (db_in_controller per Admin\DashboardController) sono reali: le query Eloquent stanno nel controller invece che in un service dedicato.

---

### 3. Tornei — Vista Pubblica (Arbitri)

*Sorgente: `routes/web.php` → `TournamentController`*

#### GET `/tournaments`

**Scopo**: Lista tornei visibile agli arbitri autenticati. Mostra i tornei filtrati per zona/livello dell'arbitro, con la possibilità di filtrare per tipo, data, stato.

#### GET `/tournaments/{tournament}`

**Scopo**: Dettaglio torneo per l'arbitro — date, circolo, tipo, stato, e se l'arbitro ha già dichiarato disponibilità.

#### GET `/tournaments/calendar/view`

**Scopo**: Vista calendario FullCalendar con gli stessi dati, codificata per colori in base allo stato del torneo. Usa `CalendarDataService` e `TournamentColorService`.

**Sequenza di chiamate**:
Route → `TournamentController::index()` → `Tournament::visible($user)` (scope) → view `tournaments.index`
Route → `TournamentController::calendar()` → `CalendarDataService::prepareFullCalendarData()` → view `tournaments.calendar`

**Middleware**: `auth`

---

### 4. Tornei — Gestione Admin

*Sorgente: `routes/admin/tournaments.php` — Controller: `Admin\TournamentController`*

#### GET `/admin/tournaments`

**Scopo**: Lista amministrativa tornei. Filtra per zona se ZoneAdmin, mostra tutto se NationalAdmin/SuperAdmin. Supporta filtri per status, club, data, tipo. Il trait `HasZoneVisibility::applyTournamentVisibility()` centralizza la logica di visibilità.

#### GET `/admin/tournaments/create` + POST `/admin/tournaments`

**Scopo**: Creazione nuovo torneo. Il form include circolo ospitante, tipo di torneo, date, deadline disponibilità. Alla creazione, il `zone_id` viene ricavato automaticamente dal club selezionato (non inserito manualmente).

**Validazione**: `TournamentRequest` (FormRequest dedicato — nessuna anomalia).

#### GET `/admin/tournaments/{id}` — Show

**Scopo**: Vista dettaglio torneo per l'admin. Mostra arbitri assegnati, arbitri disponibili, statistiche (totale assegnazioni, disponibilità, arbitri richiesti dal tipo torneo, giorni alla deadline).

#### PATCH `/admin/tournaments/{id}/change-status`

**Scopo**: Override manuale dello stato del torneo (bypassa il workflow normale). Usato per correzioni amministrative. Logga l'override con user, old_status e new_status per audit trail.

#### GET `/admin/tournaments/{id}/availabilities`

**Scopo**: Vista admin delle disponibilità dichiarate per un torneo. Mostra chi ha dichiarato disponibilità e chi tra gli arbitri eleggibili non l'ha ancora fatto. Filtra per livello (nazionale/zonale) in base al tipo di torneo.

**Connessioni**: questo modulo è strettamente connesso ad Assignments (step successivo nel workflow) e a Notifications (step finale).

**Middleware**: `auth`, `admin_or_superadmin`

**Anomalie rilevate**:
- 🔴 **`missing_authorization`** — Le anomalie di autorizzazione rilevate automaticamente sono **false positive**: il middleware `admin_or_superadmin` protegge l'intero gruppo di route, e il trait `HasZoneVisibility::checkTournamentAccess()` verifica i permessi a livello di risorsa. Il rilevatore automatico non vede i middleware di gruppo.

---

### 5. Disponibilità Arbitri

*Sorgente: `routes/user/availability.php` — Controller: `User\AvailabilityController`*

**Questo è il workflow più critico del sistema dal lato arbitro.**

#### GET `/user/availability` — Lista disponibilità

**Scopo**: L'arbitro vede le proprie disponibilità dichiarate, ordinate per data torneo.

#### GET `/user/availability/tournaments` — Lista tornei per dichiarare disponibilità

**Scopo**: Mostra i tornei futuri per cui l'arbitro può dichiarare disponibilità, filtrati per zona e livello. Evidenzia quali tornei hanno già ricevuto la dichiarazione di disponibilità dell'utente.

#### POST `/user/availability` — Dichiarazione singola

**Scopo**: L'arbitro dichiara o ritira la disponibilità per un singolo torneo. Il sistema verifica che il torneo sia futuro e che la deadline non sia scaduta. Usa `updateOrCreate` per idempotenza. **Dopo ogni modifica, invia automaticamente notifiche email**:
- Memo all'arbitro (conferma)
- Email agli admin della zona (solo per tornei zonali → SZR)
- Email agli admin nazionali CRC (solo per tornei nazionali)

#### POST `/user/availability/batch` — Dichiarazione multipla

**Scopo**: L'arbitro può selezionare con checkbox multipli i tornei per cui è disponibile nella pagina corrente. Il sistema calcola le differenze (added/removed) rispetto allo stato precedente, aggiorna il DB in transazione, e poi invia notifiche aggregate (non una per torneo, ma un batch complessivo per tipo admin). Previene IDOR: un arbitro non può dichiarare disponibilità per tornei fuori dalla propria zona.

#### GET `/user/availability/calendar`

**Scopo**: Vista calendario FullCalendar per l'arbitro. Mostra i tornei colorati per stato (disponibile, assegnato, generico). Usa `CalendarDataService` con parametri specifici per il ruolo arbitro.

**Sequenza di chiamate** (batch):
Route → `AvailabilityController::saveBatch()` → validazione → `applyTournamentVisibility()` → `DB::beginTransaction()` → delete vecchie disponibilità → insert nuove → `DB::commit()` → `handleNotifications()` → `sendSeparatedAdminNotifications()` → `Mail::to()->send(BatchAvailabilityNotification)` + `Mail::to()->send(BatchAvailabilityAdminNotification)`

**Dati in transito**:
- **Input**: `availabilities[]` (array di tournament_id) + filtri pagina (zone_id, tournament_type_id, month)
- **Trasformazioni**: calcolo diff added/removed, verifica canDeclareAvailability per ogni torneo
- **Output**: redirect con flash message; email a arbitro + SZR/CRC separati

**Middleware**: `auth`, `referee_or_admin`

**Anomalie rilevate**:
- 🟡 **`db_in_controller`** — Le query Eloquent per raccogliere email degli admin (`collectZoneAdminEmails`, `collectNationalAdminEmails`) sono direttamente nel controller. Potrebbero stare in `AvailabilityNotificationService` (che esiste già ma non sembra usato qui — potenziale dead code o refactoring incompiuto).
- 🔴 **`missing_authorization`** — Falso positivo: protetto da middleware `referee_or_admin`.

---

### 6. Assegnazioni Arbitri

*Sorgente: `routes/admin/assignments.php` — Controller: `Admin\AssignmentController`*

**Questo è il workflow più critico dal lato admin.**

#### GET `/admin/assignments/assign-referees/{tournament}` + POST `/admin/assignments/{tournament}/store-multiple`

**Scopo**: Il cuore del processo di assegnazione. La pagina mostra tre liste di arbitri per un torneo: (1) arbitri che hanno dichiarato disponibilità, (2) altri arbitri della zona senza disponibilità, (3) arbitri nazionali (per tornei nazionali o per admin CRC). L'admin seleziona e assegna con ruoli specifici in una sola operazione. **Dopo la prima assegnazione, il sistema crea automaticamente una `TournamentNotification` in stato `draft`**, predisponendo il documento di convocazione.

#### POST `/admin/assignments` — Assegnazione singola

**Scopo**: Alternativa alla selezione multipla — assegna un singolo arbitro con un ruolo specifico a un torneo, verificando che non sia già assegnato.

#### GET `/admin/assignment-validation` — Dashboard validazione

**Scopo**: Pannello di controllo per la qualità delle assegnazioni. Usa `AssignmentValidationService` per rilevare conflitti di date, tornei con requisiti mancanti, arbitri sovrassegnati e arbitri sottoutilizzati. Offre anche correzione automatica dei conflitti.

**Sequenza di chiamate** (store-multiple):
Route → `AssignmentController::storeMultiple()` → `DB::beginTransaction()` → `Assignment::create()` per ogni arbitro → `DB::commit()` → auto-create `TournamentNotification` → redirect con modal "prossimo step"

**Connessioni**: `storeMultiple` è il trigger che attiva il modulo Notifiche. L'`AssignmentObserver` (non analizzato in dettaglio) aggiorna `referee_list` e `total_recipients` sulla TournamentNotification a ogni modifica delle assegnazioni.

**Middleware**: `auth`, `admin_or_superadmin`

**Anomalie rilevate**:
- 🟡 **`db_in_controller`** — Le query per `getAvailableReferees`, `getPossibleReferees`, `getNationalReferees` (metodi privati del controller) sono direttamente nel controller. Logica di selezione complessa che potrebbe stare in un service.
- 🔴 **`missing_authorization`** — Falso positivo: middleware di gruppo.

---

### 7. Notifiche Tornei

*Sorgente: `routes/admin/notifications.php` — Controller: `Admin\NotificationController`*

**Questo è il workflow di output finale del sistema: la comunicazione verso l'esterno.**

#### GET `/admin/notifications` — Lista notifiche

**Scopo**: Vista panoramica di tutte le convocazioni per torneo. Per i tornei nazionali, raggruppa la notifica CRC (per arbitri nazionali) e la notifica Zona (per osservatori) in una singola riga. Paginazione manuale su una collection già raggruppata.

**Nota tecnica importante**: un precedente N+1 bug (`updateRecipientInfo()` in loop ad ogni visualizzazione) è stato rimosso. I dati vengono ora aggiornati dall'`AssignmentObserver` al momento delle modifiche alle assegnazioni.

#### POST `/admin/notifications/{tournament}/send` — Invio notifica

**Scopo**: Invia le convocazioni per un torneo. Usa tre service in collaborazione:
- `NotificationPreparationService`: prepara i destinatari e i dati della notifica
- `NotificationDocumentService`: genera i documenti DOCX (convocazione arbitri + lettera circolo)
- `NotificationTransactionService`: gestisce la transazione di invio, aggiorna lo stato a `sent`, registra la data

Supporta sia primo invio che re-invio (`force=true`): in caso di re-invio, usa le assegnazioni correnti (non quelle archiviate nella notifica).

**Sequenza di chiamate**:
Route → `NotificationController::send()` → `NotificationPreparationService::prepare()` → `NotificationDocumentService::generateDocuments()` → `NotificationTransactionService::send()` → `Mail::to(club)->send(ClubNotificationMail)` + `Mail::to(referees)->send(RefereeAssignmentMail)` + `Mail::to(institutional)->send(InstitutionalNotificationMail)`

**Dati in transito**:
- **Destinatari**: circolo ospitante (flag `club: true/false`), arbitri selezionati (subset delle assegnazioni), email istituzionali (lista configurata dal SuperAdmin)
- **Documenti allegati**: DOCX convocazione + DOCX lettera circolo (generati da `DocumentGenerationService`)

**Middleware**: `auth`, `admin_or_superadmin`

**Anomalie rilevate**:
- 🟡 **Architettura a tre service**: `NotificationPreparationService`, `NotificationDocumentService`, `NotificationTransactionService` sono ben separati per responsabilità, ma il controller `NotificationController` ha ancora del codice di business (raggruppamento notifiche per torneo, paginazione manuale). Rischio: logica duplicata se si aggiunge un canale API.

---

### 8. Comunicazioni di Sistema

*Sorgente: `routes/admin/communications.php` — Controller: `Admin\CommunicationController`*

#### CRUD `/admin/communications`

**Scopo**: Gestione di annunci, avvisi e comunicazioni di sistema (non legate a tornei specifici). Tipi: `announcement`, `alert`, `maintenance`, `info`. Priorità: `low`, `normal`, `high`, `urgent`. Supporta scheduling (`scheduled_at`) e scadenza (`expires_at`). La zona admin vede solo comunicazioni della propria zona o globali (zona null).

**Anomalie rilevate**:
- 🔴 **`missing_validation`** per `store`: il metodo `store` valida il request, ma **non persiste effettivamente il record** — manca la chiamata `Communication::create($validated)`. La comunicazione viene validata ma non salvata nel database. **BUG CONFERMATO**.

---

### 9. Super-Admin — Configurazione Sistema

*Sorgente: `routes/super-admin.php` — Controller: `SuperAdmin\*`*

#### CRUD `/super-admin/tournament-types`

**Scopo**: Gestione dei tipi di torneo (es. Zonale, Nazionale, Interzonale). Ogni tipo ha `min_referees`, `is_national`, flag `is_active`. La flag `is_national` determina quale admin riceve le notifiche di disponibilità e quale pool di arbitri viene mostrato per le assegnazioni.

#### CRUD `/super-admin/institutional-emails`

**Scopo**: Lista di email istituzionali (federazione, organi di controllo, ecc.) che ricevono copia delle notifiche di torneo. Gestita separatamente dagli utenti del sistema. Supporta toggle attivo/inattivo e export.

#### CRUD `/super-admin/clauses`

**Scopo**: Gestione delle clausole legali/regolamentari che possono essere incluse nelle lettere di convocazione. Supporta ordinamento (`reorder`), preview e toggle attivo/inattivo.

**Middleware**: `auth`, `super_admin`

---

### 10. Strumenti Server (ArubaTools / Maintenance)

*Sorgente: `routes/maintenance.php` — Controller: `SuperAdmin\ArubaToolsController`*

**Scopo**: Pannello di controllo server per l'ambiente di hosting Aruba. Funzionalità: svuotamento cache, ottimizzazione, pulizia asset, consultazione phpinfo, lettura log, gestione permessi, backup/restore database, monitoraggio server, diagnostica Composer, gestione storage link.

**Anomalie rilevate**:
- 🔴 **`missing_authorization`** — Queste route non hanno middleware visibile nella scansione. **Da verificare urgentemente**: se non protette da `super_admin` middleware, sono accessibili a qualsiasi utente autenticato. Le operazioni includono backup/restore del database e modifica permessi filesystem.
- 🔴 **`missing_validation`** per endpoint POST (es. `databaseRestore`, `fixPermissions`) — input non sanitizzato per operazioni critiche.

---

## Connessioni Tra Moduli

```
Disponibilità (User) ──────────────────────────────────────────────────┐
        ↓ notifica SZR/CRC                                              │
Assegnazioni (Admin) → auto-create TournamentNotification              │
        ↓                                                               │
Notifiche (Admin) ← AssignmentObserver aggiorna recipient_list         │
        ↓                                                               │
DocumentGenerationService → DOCX convocazione + lettera circolo        │
        ↓                                                               │
Email: Arbitri + Circolo + Email Istituzionali (InstitutionalEmail)    │
                                                                        │
TournamentType.is_national ─────────────────────────────────────────────┘
  (controlla: pool arbitri, destinatario notifiche disponibilità, filtri admin)
```

Il modello `TournamentType` con il campo `is_national` è il **punto di biforcazione centrale** dell'intera logica di visibilità e notifica. Una modifica a questo campo cambia il comportamento di almeno 5 moduli diversi.

---

## Riepilogo Anomalie

### Anomalie Critiche Reali

| Endpoint | Tipo | Descrizione | Impatto |
|----------|------|-------------|---------|
| `Admin\CommunicationController::store()` | **BUG** | `Communication::create()` mancante — la comunicazione non viene salvata | Alto: funzionalità non funzionante |
| `routes/maintenance.php` | **`missing_authorization`** | ArubaTools probabilmente non protetto da middleware `super_admin` | Critico: backup/restore DB accessibile ad admin zonali |
| `POST /database/restore` | **`missing_validation`** | Input non validato per operazioni critiche server | Alto: rischio injection o danneggiamento dati |
| `Admin\AssignmentController` | **`db_in_controller`** | Logica di selezione arbitri (3 metodi privati con query) nel controller | Medio: testabilità e manutenibilità |
| `User\AvailabilityController` | **`db_in_controller`** | Query raccolta email admin nel controller (vs `AvailabilityNotificationService` esistente) | Basso: refactoring incompleto |

### Anomalie False Positive (Non Critiche)

La maggior parte delle 31 anomalie `missing_authorization` rilevate automaticamente sono **false positive**: le route sono protette dai middleware di gruppo `admin_or_superadmin`, `super_admin`, `referee_or_admin`. Il detector statico non vede i middleware di gruppo nei file route modularizzati.

### Pattern Architetturali da Osservare

Il sistema usa correttamente `HasZoneVisibility` come single source of truth per la visibilità basata su zona, ma esistono ancora alcune query di visibilità hardcodate nei controller (es. `Admin\DashboardController`). Il refactoring è parzialmente completato.

`AssignmentObserver` (non analizzato in dettaglio) gestisce la sincronizzazione automatica dei dati sulle `TournamentNotification` — pattern corretto che elimina il precedente N+1 bug.

---

## Modelli Coinvolti

| Modello | Ruolo | Relazioni chiave |
|---------|-------|-----------------|
| `User` | Arbitri e admin | `zone`, `assignments`, `availabilities`, `careerHistory` |
| `Tournament` | Torneo di golf | `club`, `zone`, `tournamentType`, `assignments`, `availabilities`, `notification` |
| `TournamentType` | Tipo torneo (zonale/nazionale) | `tournaments`; `is_national` controlla intera logica di visibilità |
| `Assignment` | Assegnazione arbitro→torneo con ruolo | `user`, `tournament`, `assignedBy` |
| `Availability` | Dichiarazione disponibilità arbitro | `user`, `tournament` |
| `TournamentNotification` | Stato e dati convocazione | `tournament`; tiene referee_list, status, documenti |
| `Club` | Circolo golf ospitante | `zone`, `tournaments` |
| `Zone` | Zona geografica | `users`, `clubs` |
| `InstitutionalEmail` | Email istituzionali per CC notifiche | — |
| `NotificationClause` | Clausole legali per lettere convocazione | — |
| `Communication` | Comunicazioni sistema (annunci) | `author`, `zone` |

---

*Report generato con Laravel Flow Analyzer su progetto golf-arbitri-clean — Laravel 12*
