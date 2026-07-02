# Storico audit e interventi — Golf Arbitri

*Condensato il 2026-06-11. Sostituisce 12 documenti superati (eliminati): `AUDIT_report.md` v1/v2/v3, `SPEC_ricostruzione.md`, `AUDIT_notifications_v1.md`, `DeepTest_Report.md`, `dead_code_report.md`, `AUDIT_architect_review.md`, `PIANO_INTERVENTO.md`, `RISK_ASSESSMENT.md`, `docs/laravel-flow-analysis.md`, `docs/guides/GUIDA_GIT_WORKFLOW.md`, `ANALISI_CALENDARI_2026.md`, `IMPORT_TORNEI_2026.md`, più la cartella `_audit_archive/`.*

**Documento corrente di riferimento: `docs/analisi-approfondita-2026-06-10.md`.**

---

## Timeline

### 2026-03-21 — Audit iniziale + SPEC ricostruzione (site-analyzer)
Backend giudicato sano (model puliti, Enum, Service layer). Stratificazione concentrata nel frontend: due sistemi di navigazione paralleli, `app.blade.php` senza CSS compilati, Alpine.js caricato 3 volte, file debug in produzione. Problemi deploy Aruba (no SSH): code email mai processate, script pubblici. La SPEC per ricostruzione da zero fu scritta ma **la ricostruzione è stata abbandonata**: si è scelto il refactoring mirato.

### 2026-03-22 — DeepTest (4 livelli)
15 bug (4 critici). Highlights: `sendNationalNotification()` CC ~18 e ~60 righe duplicate con `resendNationalNotification()`; `Carbon::parse(null)` su `end_date` in `datesOverlap()` → falsi conflitti (poi ridimensionato: colonna NOT NULL, caso non raggiungibile dal DB; fix difensivo applicato comunque); 3 classi Mail zombie con `view: 'view.name'` inesistente.

### 2026-03-24/25 — Audit v2/v3, audit notifiche, dead code, flow analysis
Due sottosistemi notifiche paralleli senza separazione chiara (TournamentNotification vs Notification, collegati per finestra temporale ±5/10 min su `sent_at`). Mail zombie (`RefereeAvailabilityConfirmation`, `National/ZonalAvailabilityNotification`) → crash se istanziate. Bug reinvio nazionale (record FIG cadevano nel ramo zonale). Dead code report: 824 finding regex-based (molti falsi positivi); top reali: `AvailabilityRequest`, middleware/FormRequest mai referenziati. Flow analysis: 63 route mappate.

### 2026-04-07 — Architect review + Piano intervento (consolidamento)
Finding consolidati: `Mail::raw()` ×3 nel controller, `AvailabilityRequest::authorize()===false` (trappola), XSS `{!! session() !!}` ×5 template, N+1 in `ClubController::export()` e `storeMultiple()`, 2 god controller (Assignment 860 righe, Notification 847). Piano INT-01…10: 6 interventi rimossi con motivazione (refactoring senza beneficio misurabile), 4 mantenuti. Route API pubbliche `/api/internal/tournaments/*` verificate **intenzionali** (solo dati completed/assigned, no PII).

### 2026-05-09/10 — Sessione di fix
- XSS flash → `{{ }}` (chiuso anche information disclosure da `$e->getMessage()`).
- `AvailabilityRequest` eliminato.
- `Mail::raw` → `NationalNotificationMail` (Mailable + view `nl2br(e($body))`). Tre tentativi: i primi due fallivano per **bug latente formato CC** — `NotificationRecipientBuilder::build()` restituiva `[email => name]`; `PendingMail::parseAddresses()` mappa sui values → name al parser RFC 2822. Fix: formato canonico `array<{email, name}>` (vedi memoria progetto: regola da non perdere).
- Validazione difensiva `filter_var` in `addCc/addTo` (skip + `Log::warning`) per dati sporchi in DB (zone con nome al posto dell'email).
- Reinvio unificato: tutti i record (zonali/nazionali/FIG) → form `prepare_notification`; eliminato `resendNationalNotification()` (~90 righe dead).
- Icona reinvio anche su status `partial`/`failed`.
- Test regressione: `NotificationRecipientBuilderEmailValidationTest`, `NationalNotificationMailDispatchTest`.
- Creato `_audit_archive/` (ora soppresso, condensato qui).
- Nota operativa MAMP: OPcache aggressivo → dopo modifiche a service/controller serve restart Apache completo.

### 2026-05-30 — Risk assessment go-live
Verdetto 🟡: prodotto **già live** su Aruba (`arbitrigolf.golfrating.it`, SMTP `smtps.aruba.it`). Rischio principale: nessuna CI, suite vincolata a MAMP (`.env.testing` → socket MAMP); 49 file test / ~920 asserzioni eseguibili solo a mano su una macchina. R1: send zonale coperto solo da `Mail::fake()` (cieco su parsing indirizzi e rendering). R2: dati sporchi → notifiche silenziosamente incomplete. R2-bis: `sendToClub()` fuori try/catch → circolo senza email blocca l'intera notifica. Consegnati: `.github/workflows/ci.yml`, `ZonalNotificationSendTest`, `NationalNotificationCorruptZoneEmailTest`, `AssignmentDateConflictNullEndDateTest`. Checklist: CI, test send reale, pre-flight destinatari nella UI, bonifica dati, soft-launch presidiato.

### 2026-06-10 — Analisi approfondita + refactor mail unica (documento vivo)
Vedi `docs/analisi-approfondita-2026-06-10.md`: difetti D1–D4 su reinvii/record FIG, refactor "lettera al circolo (TO) + interessati in CC", campi form `send_to_section`/`additional_emails` finalmente letti dal backend, dead code purgato (`RefereeAssignmentMail`, `InstitutionalNotificationMail`, `addAssignedReferees`, `addObservers`, parametro `$force`), flag `attach_convocation` onorato, segregazione DOCX di test via `config('golf.documents.*')`.

### 2026-07-02 — Audit comunicazioni esterne: C1–C3 + M1–M5 fixati (suite verde)
Vedi `docs/audit-comunicazioni-esterne-2026-07-02.md`. Critici: **C1** QUEUE=sync + afterCommit + transazione = try/catch SMTP morto e status `sent` committato prima dell'invio reale (fix: niente transazione attorno agli invii — regola da non violare); **C2** messaggio admin mai renderizzato (`$message_content` vs `$content`); **C3** checkbox circolo senza hidden input. Medi: cc_* nazionali validati, DOCX spostati su disk privato `docs` (deploy: FTP move `storage/app/public/convocazioni` → `storage/app/docs/convocazioni`), allegati mancanti → `partial`, preview fedele all'invio, zero destinatari → `failed`. Restano aperti gli 11 minori del report. Suite verde su MAMP 2026-07-02.

---

## Documenti operativi assorbiti (traccia)

- **Guida git workflow** (apr 2026): workflow standard a 5 passi (branch da main → commit → merge → cleanup). Nulla di specifico del progetto; eliminata.
- **Analisi calendari 2026 + import tornei** (apr 2026): dai 4 PDF calendario FIG 2026 estratti **189 tornei** → `calendari_2026_consolidato.csv` + `database/seeders/Tournaments2026Seeder.php`. Import eseguito; per import saltuari successivi esiste il wizard `/admin/federgolf-import` (vedi memoria progetto FedergolfImport).

---

## Aperture residue (al 2026-06-11)

- ~~INT-03~~ ✅ risolto: `ClubController` usa `withCount('tournaments')` (verificato 2026-06-11).
- ~~INT-04~~ ✅ risolto: `storeMultiple()` precarica gli user_id con pluck prima del loop (verificato 2026-06-11).
- God controller: `AssignmentController` 879 righe, `NotificationController` 846.
- Bonifica dati: script pronto in `database/bonifica/2026-06-11_bonifica_dati.sql` — da eseguire su produzione.
- Pre-flight destinatari: ✅ implementato 2026-06-11 (`NotificationPreparationService::buildPreflight()` + pannello nel form; test `PreflightRecipientsTest`). Da eseguire la suite in MAMP.
- Soft-launch: checklist in `docs/guides/SOFT_LAUNCH.md` — da eseguire.
- CI: `ci.yml` consegnato il 2026-05-30, da verificare al primo run su GitHub (ultimo della lista per scelta di Alberto, 2026-06-11).
- Opzionali (decisi di non fare per ora): rinominare `ClubNotificationMail` → `ZonalNotificationMail`; unificare percorso nazionale sullo stesso builder+service dello zonale (~1 giorno).
