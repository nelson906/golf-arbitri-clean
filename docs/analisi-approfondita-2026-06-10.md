# Analisi Approfondita — Golf Arbitri
*Generata il: 2026-06-10 — Laravel 12 — flow analysis + audit stratificazioni/dead code*

> Aggiorna e integra `docs/laravel-flow-analysis.md` (2026-03-25). Combina scansione statica automatica (67 route) con lettura manuale di controller, service, trait e model dei workflow core, più audit dedicato a codice morto e stratificazioni.

---

## Riepilogo

| Metrica | Valore |
|---------|--------|
| Route analizzate | 67 (su 28 file route) |
| Controller | 40 file (Admin 13, User 7, SuperAdmin 7, Auth 9, core 4) |
| Service | 13 (+ sottocartelle Monitoring, Statistics) |
| Model | 15 |
| View Blade | 145 |
| Test | 58 file |
| Anomalie script | 48 (34 critiche, ~20 stimate falsi positivi da middleware) |
| **Anomalie reali confermate a lettura** | **6 critiche, 5 medie** |
| Codice morto confermato | 1 classe intera + ~8 metodi service + 1 model quasi orfano |

**Giudizio sintetico**: l'architettura del dominio è più sana di quanto la storia del progetto faccia temere. La visibilità zona/ruolo è centralizzata (`HasZoneVisibility` → `TournamentVisibility` come single source of truth), `tournamentType.is_national` è usato coerentemente come fonte di verità, e il workflow notifiche è ben commentato. I problemi veri sono: **autorizzazione mancante su 4 azioni mutanti delle assegnazioni**, **invio email sincrono e dentro transazioni DB**, e **uno strato di service notifiche duplicato/morto** che confonde chi legge.

---

## 1. Architettura e workflow core

### Catena di business

```
Arbitro dichiara disponibilità → Admin (Zona o CRC) assegna ai tornei → Notifica a circoli + arbitri + indirizzi istituzionali
```

### Separazione Zona vs CRC (consolidata e coerente)

| Aspetto | Zonale | Nazionale (CRC) |
|---|---|---|
| Fonte di verità | `tournamentType.is_national = false` | `is_national = true` |
| `notification_type` | `null` | `crc_referees` + `zone_observers` |
| Notifica disponibilità | SZR riceve solo tornei zonali | CRC riceve solo tornei nazionali |
| Arbitri proposti in assegnazione | tutti, stessa zona | solo livello Nazionale/Internazionale |
| Invio notifica torneo | form `prepare_notification` con allegati DOCX | `sendNationalNotification` senza allegati, TO/CC via builder |
| Reinvio | sempre via form `prepare_notification` | idem (redirect unificato in `resend()`) |

Questa logica è applicata in modo coerente in `AvailabilityController::sendSeparatedAdminNotifications()`, `NotificationController::index()` (raggruppamento per torneo), `NotificationPreparationService::prepareNotification()` e nel guard di `sendNationalNotification()`.

### Flusso 1 — Disponibilità (User)

`routes/user/availability.php` → `User\AvailabilityController`

- **`tournaments()` / `store()` / `saveBatch()`**: l'arbitro vede i tornei futuri filtrati per zona/livello (trait), dichiara/rimuove disponibilità singola o batch. `saveBatch` è "page-scoped": cancella e ricrea solo le disponibilità dei tornei visibili nella pagina corrente, con verifica anti-IDOR per torneo.
- **Notifiche**: memo all'arbitro + notifica separata SZR (zonali) / CRC (nazionali) via `BatchAvailabilityNotification` / `BatchAvailabilityAdminNotification`.
- **Sequenza**: Route → Controller → `Availability` (Eloquent diretto) → `Mail::to()->send()` sincrono.

### Flusso 2 — Assegnazioni (Admin)

`routes/admin/assignments.php` → `Admin\AssignmentController` (861 righe, 20 azioni)

- **`assignReferees()` / `storeMultiple()`**: il cuore operativo. Tre liste: disponibili (hanno dichiarato), possibili (stessa zona senza dichiarazione), nazionali (per tornei nazionali / admin CRC). `storeMultiple` crea le assegnazioni in transazione e **auto-crea la `TournamentNotification` draft** con `notification_type` derivato da `is_national` (hook post-commit, non bloccante).
- **Modulo validazione** (`assignment-validation/*`): conflitti date, requisiti mancanti, arbitri sovra/sotto-assegnati, fix automatici — delegato a `AssignmentValidationService`. Supporta l'obiettivo di "omogeneizzare" i carichi tra arbitri per zona/categoria.

### Flusso 3 — Notifiche (Admin) — il processo più importante

`routes/admin/notifications.php` + route in `admin/tournaments.php` → `Admin\NotificationController` (736 righe)

- **Zonale**: `showAssignmentForm()` → `prepare_notification` (form) → `sendAssignmentWithConvocation()` con azioni `save|preview|send` → `NotificationTransactionService::sendWithTransaction()` → `NotificationService::send()` → mail a circolo (`ClubNotificationMail` + lettera DOCX), arbitri (`RefereeAssignmentMail` + convocazione DOCX), istituzionali (`InstitutionalNotificationMail`).
- **Nazionale**: `sendNationalNotification()` con `notification_type ∈ {crc_referees, zone_observers}`, destinatari costruiti da `NotificationRecipientBuilder` (TO Ufficio Campionati, CC zona/CRC/admin/arbitri/osservatori; formato CC canonico `array<{email,name}>` — fix documentato del bug RFC 2822). Elimina la bozza zonale residua e fa `updateOrCreate` del record nazionale in transazione.
- **Documenti**: `NotificationDocumentService` (CRUD su DOCX generati/caricati) sopra `DocumentGenerationService` (generazione effettiva con clausole).

### Connessioni tra flussi (punti di effetto collaterale)

- `Assignment` è condiviso da: assegnazioni, validazione, calendario user, notifiche (recipients enforcement in `NotificationService::send` interseca con assignments correnti), `AssignmentObserver` (aggiorna `referee_list`/`total_recipients`).
- `TournamentNotification` è creata da **tre** percorsi: hook in `storeMultiple`, `prepareNotification()` (form), `updateOrCreate` in `sendNationalNotification`. Coerenti tra loro, ma una modifica alla chiave (`tournament_id` + `notification_type`) va propagata in tutti e tre.
- `Tournament.zone_id` è un **accessor** che preferisce `club.zone_id` ma ricade sulla colonna DB: chi filtra via SQL (`where('zone_id', ...)` in validazione) dipende dalla colonna, chi legge il model dipende dal club. Tenere sincronizzati i due valori al salvataggio è critico.

---

## 2. Anomalie reali (confermate a lettura, non falsi positivi)

### 🔴 A1 — Autorizzazione zona mancante su 4 azioni mutanti delle assegnazioni

`Admin\AssignmentController`: `store()`, `storeMultiple()`, `removeFromTournament()` e il form `assignReferees()` **non chiamano** `checkTournamentAccess()`/`canAccessTournament()`. Il middleware `admin_or_superadmin` verifica solo il ruolo, non la zona: un admin di zona può assegnare/rimuovere arbitri su tornei di **altre zone** indicando l'ID nel form (IDOR). `update()`, `destroy()`, `confirm()`, `edit()`, `show()` invece il check lo hanno — l'asimmetria suggerisce regressione/stratificazione. **Fix**: aggiungere `$this->checkTournamentAccess($tournament)` in testa alle 4 azioni.

### 🔴 A2 — Stessa lacuna su NotificationController

`showAssignmentForm()`, `sendAssignmentWithConvocation()`, `sendNationalNotification()`, `destroyTournament()` e le azioni documento non verificano l'accesso zona al torneo/notifica (`index()` filtra correttamente, ma le azioni puntuali no). Un admin zonale può inviare o eliminare notifiche di tornei fuori zona.

### 🔴 A3 — Email sincrone nel ciclo di richiesta HTTP

Nessun Mailable implementa `ShouldQueue` (verificato su tutti i file in `app/Mail/`), benché `QUEUE_CONNECTION=database` sia previsto in `.env.example`. `saveBatch` può inviare 3+ email, `NotificationService::send` ne invia N (circolo + ogni arbitro + istituzionali) una alla volta con allegati. Su hosting Aruba con SMTP lento = timeout della richiesta e invii parziali. **Fix**: `implements ShouldQueue` sui Mailable + worker, oppure `Mail::queue()`.

### 🔴 A4 — Invio email dentro transazione DB

`NotificationTransactionService::sendWithTransaction()` apre `DB::beginTransaction()` e dentro invia le email. Le email non sono transazionali: se l'update finale fallisce e si fa rollback, le email **restano inviate** ma il DB dice che non lo sono (stato incoerente, rischio doppio invio al retry). Inoltre tiene la connessione DB occupata per tutta la durata SMTP. **Fix**: separare — prima transazione DB (stato `sending`), poi invii fuori transazione, poi update finale.

### 🔴 A5 — `AvailabilityController::destroy()` non notifica

La rimozione disponibilità via `DELETE /user/availability/{availability}` cancella e basta; la rimozione via `store(available=false)` invece notifica arbitro + SZR/CRC. Due percorsi UI per la stessa operazione di business con effetti diversi: l'admin può non venire mai a sapere che un arbitro ha ritirato la disponibilità. **Fix**: chiamare `handleSingleNotification($user, $tournament, 'removed')` anche in `destroy()`.

### 🔴 A6 — `generateDocument`/`uploadDocument` aggiornano `documents` senza lock

Read-modify-write del JSON `documents` (`json_decode` → modifica chiave → `update`) senza lock né `lockForUpdate()`: due richieste AJAX concorrenti (genera convocazione + carica lettera) possono perdersi a vicenda l'aggiornamento. Probabilità bassa (singolo admin), impatto medio (documento "sparito" dal record ma presente su disco).

### 🟡 M1 — `sendNationalNotification` fa troppe cose (180 righe)

Validazione, costruzione destinatari, loop invio con fallback CC→TO, transazione di pulizia bozze, doppio formato messaggi flash. La logica di invio+persistenza meriterebbe un service simmetrico a `NotificationTransactionService` (oggi il percorso nazionale bypassa del tutto il layer transazionale usato dallo zonale).

### 🟡 M2 — Paginazione manuale in memoria su `NotificationController::index()`

`$query->get()` carica **tutte** le notifiche poi `groupBy` + `forPage` in collection. Con anni di storico la pagina degrada linearmente. Mitigato dal filtro anno, ma il filtro è opzionale.

### 🟡 M3 — Query in loop

- `saveBatch`: `Tournament::find()` per ogni torneo selezionato nel foreach (fino a 20 query evitabili con `whereIn` precaricato).
- `collectZoneAdminEmails`: una query per torneo invece di raggruppare gli `zone_id` e fare una sola `whereIn`.

### 🟡 M4 — `validate()` inline ovunque nei controller core

Esistono FormRequest (`AssignmentRequest`, `TournamentRequest`...) ma `AssignmentController` e `NotificationController` usano `$request->validate()` inline. Non è un bug, ma `AssignmentRequest` risulta **mai usato** (vedi §3) — segno che la migrazione a FormRequest è stata iniziata e abbandonata.

### 🟡 M5 — `documentsStatus()` riusato come API interna

`generateDocument`/`deleteDocument`/`uploadDocument` chiamano `$this->documentsStatus($notification)->getData()` — un controller che invoca un proprio endpoint JSON e ne deserializza la risposta. Funziona, ma il punto giusto è chiamare direttamente `$this->documentService->getDocumentsStatus()`.

---

## 3. Audit stratificazioni e codice morto

### Codice morto confermato (nessun riferimento in app/routes/resources)

| Elemento | Evidenza | Azione consigliata |
|---|---|---|
| `App\Services\AvailabilityNotificationService` (249 righe) | zero usage; la logica vive duplicata e commentata «no servizio esterno» dentro `AvailabilityController::sendSeparatedAdminNotifications()` | **eliminare** (o, in alternativa, reintrodurlo e svuotare il controller — scegliere una delle due) |
| `NotificationService::prepareNotification()` + `generateDocuments()` | nessun caller; duplicano `NotificationPreparationService::prepareNotification()` e `NotificationDocumentService` | eliminare i 2 metodi, lasciare solo `send()` |
| `NotificationTransactionService::prepareAndSend()` | nessun caller | eliminare |
| `NotificationPreparationService::updateNotificationMetadata()`, `markAsPrepared()`, `validateTournamentForNotification()`, `updateRecipientInfo()` | nessun caller (`updateRecipientInfo` rimosso esplicitamente per N+1, ora gestito da `AssignmentObserver`) | eliminare |
| `NotificationRecipientBuilder::addZoneAdmins()`, `addNationalAdmins()`, `addAssignedReferees()`, `addObservers()` | solo le varianti `*ByIds()` sono usate da `sendNationalNotification` | eliminare o marcare @api se previsti per uso futuro |
| `App\Models\Notification` (357 righe) | usato solo da `TournamentNotification::individualNotifications()` (relazione mai letta altrove) e da un `count()` in `TournamentStatsService` | candidato a rimozione; la stat può contare su `tournament_notifications` |
| `App\Http\Requests\AssignmentRequest` | mai referenziato | usarlo in `store()/update()` oppure eliminare |

### Stratificazioni rilevate (non bug, ma debito)

1. **Tre nomi per "notifica"**: `Notification` (legacy, quasi morto), `TournamentNotification` (attivo), `Communication` (bacheca). Chi entra nel progetto non sa quale aprire. Rinominare/eliminare il legacy chiarirebbe.
2. **Doppio service layer notifiche**: `NotificationService` (legacy, solo `send()` vivo) + quartetto nuovo (`Preparation`/`Document`/`Transaction`/`RecipientBuilder`). La direzione è giusta — completare la migrazione spostando `send()` (rinominato es. `NotificationDispatchService`) e cancellando il resto.
3. **Naming `referee.*` per view di controller `User\*`**: `User\AvailabilityController` renderizza `referee.availabilities.*`; route legacy `referee/` mantenute come redirect. Coerente con la migrazione documentata nei commenti, ma da completare (rinominare cartella view o creare alias).
4. **`routes/dev/` ~1.300 righe** (view-helpers 671, view-routes 357, view-preview 206): tool di sviluppo gated su `local/staging` — corretto, ma `view-test-all.php` è incluso nel gate mentre il file pesa nel conteggio anomalie dello script. Valutare estrazione in package/comando artisan.
5. **`maintenance.php` (ArubaTools)**: protetto da `auth` + `super_admin` (i 🔴 dello script qui sono falsi positivi), ma espone `phpinfo`, clear log, fix permessi, composer da web. Accettabile per hosting Aruba senza SSH; tenere monitorato.
6. **Commenti-cronaca nel codice** («SPOSTATO da inline», «RINOMINATO da statistic.php», «RIMOSSO: ...»): utili oggi, rumore tra un anno. Spostare la cronologia nel changelog/git.
7. **`api/v1/*.php` vuoti (0 righe)**: scheletro API versionata mai iniziato. Eliminare i require o i file.
8. **Migrations “consolidate”**: 11 migration per 15+ tabelle (schema compresso in poche migration datate 2025-08-29). Tipico di reset storico: ok, ma documentare che il DB di produzione non è ricostruibile da zero senza seed/dump.

### Falsi positivi dello script (da non correggere)

La maggior parte dei 34 🔴 `missing_authorization` riguarda route protette da middleware di gruppo (`admin_or_superadmin`, `super_admin`) definiti in `web.php`/`maintenance.php` che lo script non risolve. I casi **reali** sono solo quelli in §2 (A1, A2), dove il middleware c'è ma non basta perché non scoping per zona.

---

## 4. Interventi — ESEGUITI il 2026-06-10

| # | Intervento | Stato |
|---|---|---|
| 1 | **A1** Check zona su `create/store/storeMultiple/removeFromTournament/assignReferees` | ✅ fatto |
| 2 | **A2** Check zona su tutte le azioni puntuali di `NotificationController` (helper `checkTournamentAccess`/`checkNotificationAccess`) | ✅ fatto |
| 3 | **A5** Notifica arbitro+SZR/CRC anche su `Availability::destroy` | ✅ fatto |
| 4 | **A3** `ShouldQueue` su tutti i 6 Mailable attivi — **rivisto su richiesta**: `QUEUE_CONNECTION=sync` (pochi invii, niente cron/worker), invio immediato come prima; scheduler rimosso | ✅ fatto |
| 5 | **A4** `$afterCommit = true` su tutti i Mailable: dentro `sendWithTransaction` le email sono solo accodate, partono al commit, scartate al rollback | ✅ fatto |
| 6 | **A6** Update atomico del JSON `documents` con `lockForUpdate` (`updateNotificationDocument()`) | ✅ fatto |
| 7 | **M2** Paginazione DB-side in `NotificationController::index()` (pagina i tornei, carica solo le loro notifiche; le notifiche orfane senza torneo non compaiono più) | ✅ fatto |
| 8 | **M3** `whereIn` al posto delle query in loop (`saveBatch`, `collectZoneAdminEmails`) | ✅ fatto |
| 9 | **M5** `documentsStatus` interno sostituito da chiamata diretta a `documentService->getDocumentsStatus()` | ✅ fatto |
| 10 | **Bonus** `AssignmentRequest` (FormRequest orfana) cablata in `store()`: authorize ruolo+zona, stato torneo attivo, max arbitri, livello richiesto, stessa zona per tornei zonali | ✅ fatto |
| 11 | **M1** Unificare percorso invio nazionale nel layer transazionale | ⏸ rimandato (refactor ampio, ora meno urgente: con afterCommit l'invio nazionale è già coerente) |

### Purge codice morto eseguito

Eliminati: `AvailabilityNotificationService` (249 righe), `TournamentNotificationMail`, model legacy `Notification` (357 righe, tabella `notifications` lasciata nel DB), `routes/api/v1/*` (vuoti), `NotificationService::prepareNotification()/generateDocuments()`, `NotificationTransactionService::prepareAndSend()`, `NotificationPreparationService::{updateNotificationMetadata, updateRecipientInfo, validateTournamentForNotification, markAsPrepared}`, relazione `TournamentNotification::individualNotifications()`. Fix collaterale: `TournamentStatsService::getWithNotifications()` contava sulla tabella legacy (sempre 0) → ora conta su `tournament_notifications`. I 4 metodi `add*` non-ByIds del `NotificationRecipientBuilder` mantenuti e annotati `@api` (coperti da test). Test aggiornati di conseguenza: `assertSent→assertQueued`, `assertNothingSent→assertNothingOutgoing`, `Mail::sent()→Mail::queued()`; rimossi i 2 test del service eliminato; regression test DEAD-04/BUG-04 invertiti a guardia dell'assenza.

### ⚠️ Note di deploy

1. **Email**: `QUEUE_CONNECTION=sync` in `.env` (e `.env.example`) — invio immediato e sincrono, **nessun cron/worker necessario**. Verificare che anche il `.env` di produzione abbia `sync` (attenzione: il `.env` locale aveva una chiave `QUEUE_CONNECTION` duplicata — la prima vince, duplicato rimosso). I Mailable restano `ShouldQueue` + `afterCommit`: con sync l'invio parte comunque solo al commit della transazione (protezione rollback mantenuta); se in futuro i volumi crescono basta `QUEUE_CONNECTION=database` + worker.
2. La tabella legacy `notifications` non è più letta da nessuno: droppabile con una migration dedicata quando comodo.
3. Test suite non eseguita in questo ambiente (PHP non disponibile): lanciare `php artisan test` prima del deploy.

---

## 4-bis. Bug report 2026-06-10: "notifica zonale arriva solo agli arbitri"

**Sintomo**: invio zonale dal form → arbitri raggiunti, circolo (con allegati convocazione+facsimile) e istituzionali NO.

**Diagnosi** — il percorso "fresco" (record pulito + form) è corretto e coperto dai test; il guasto sta in un cluster di 4 difetti pre-esistenti in `NotificationService::send()` che si attivano sui RE-invii e sui record importati FIG:

- **D1 — Precedenza invertita**: `$notification->recipients ?: $metadata['recipients']`. La colonna `recipients` (persistita da QUALSIASI invio precedente) shadowa per sempre le scelte fresche del form (che `saveAsDraft` scrive solo in `metadata`). Colonna con `club:false / institutional:[]` → ogni reinvio ignora circolo e istituzionali, qualunque cosa selezioni l'admin. Con arbitri presenti in colonna → "arriva solo agli arbitri".
- **D2 — Guard insufficiente**: `send()` controlla solo `empty($metadata)`. I record FIG (`MarkFigAssignmentsNotified`) hanno metadata `{source, command}` non vuoto ma SENZA `recipients` → fallback `{club:false, referees:[], institutional:[]}` → invio a NESSUNO, flash "successo", e **colonna avvelenata** → innesca D1 per sempre.
- **D3 — Fallimenti silenziosi**: circolo senza email / istituzionale mancante → catch per-destinatario, status `partial`, ma il controller flasha "inviata con successo".
- **D4 — Test ciechi**: tutti i test esistenti usano `Mail::fake` → serializzazione queue, render view, allegati e transport mai esercitati.

**Verifica sul dato reale** (per confermare quale catena è scattata):
```sql
SELECT id, notification_type, status, recipients, JSON_EXTRACT(metadata,'$.recipients') AS meta_recipients,
       JSON_EXTRACT(metadata,'$.source') AS fig_source
FROM tournament_notifications WHERE tournament_id = <ID_TORNEO>;
```
Se `recipients` contiene `"club": false` o `"institutional": []` mentre `meta_recipients` ha le scelte del form → D1 confermato. Se `fig_source` non è null → catena D2→D1. Nel log: riga `Normalized recipients for sending` mostra cosa è stato effettivamente usato.

**Regressione**: `tests/Feature/Notifications/ZonalDeliveryRegressionTest.php` — 4 test:
1. end-to-end REALE senza Mail::fake (sync queue + array transport + allegati su disco): circolo con entrambi gli allegati, arbitri, istituzionale, status coerente;
2. (SPEC, fallisce finché D1 non è fixato) il form vince sulla colonna stantia;
3. (SPEC, fallisce finché D2 non è fixato) "Invia" su record FIG → redirect al form, non invio-a-nessuno;
4. (SPEC, fallisce finché D3 non è fixato) invio parziale ≠ "successo".

## 4-ter. Modello unificato "lettera al circolo + CC interessati" — IMPLEMENTATO

**Conferma dal dato reale** (notifica id 152): `recipients` NULL, `metadata.recipients` NULL, `metadata.source = "Import batch FIG 2025"` → catena D2 confermata.

**Refactor eseguito il 2026-06-10, rivisto su indicazione di Alberto** (la prima versione a 2 mail metteva un arbitro a caso come TO della copia conoscenza — sbagliato):

- **UNA SOLA email**: **TO (competenza) = circolo**, con allegati Convocazione.docx + Lettera_Circolo.docx; **CC (conoscenza) = arbitri selezionati + istituzionali selezionati + sezione di zona + email aggiuntive**. Senza circolo, primo CC promosso a TO. NB: gli allegati raggiungono anche i CC (limite tecnico del mezzo email — impossibile allegare per-destinatario in un solo messaggio).
- **FIX form**: i campi `send_to_section` ("Invia copia alla sezione") e `additional_emails[]`/`additional_names[]` ("Email Aggiuntive") esistevano nel form ma il backend **non li ha mai letti** — per questo zona e indirizzi aggiunti non partivano. Ora finiscono in `metadata.recipients.zone/.additional` e nel CC.
- **Fix D1**: i destinatari arrivano SOLO da `metadata['recipients']` (l'intento del form); la colonna `recipients` è scritta come traccia ma mai più riletta come input.
- **Fix D2**: `send()` rifiuta metadata senza `recipients` (`ERR_MISSING_RECIPIENTS`); il pulsante "Invia" dalla lista reindirizza al form per i record FIG/incompleti.
- **Fix D3**: `redirectAfterSend()` distingue `sent` (success) da `partial` (warning con causa, es. "circolo: Club email not found").
- `RefereeAssignmentMail` e `InstitutionalNotificationMail` sono ora `@deprecated` (fuori dal flusso zonale, file mantenuti).
- `NotificationRecipientBuilder`: aggiunti `addClub()` e `addInstitutionalsByIds()`.

**Test aggiornati al nuovo modello**: ZonalDeliveryRegressionTest (incl. end-to-end reale senza Mail::fake: 2 messaggi, allegati solo sul primo), ZonalNotificationSendTest, InstitutionalNotificationSendTest, NotificationAttachmentsTest (nuovo test: copia conoscenza senza allegati), MailDispatchRegressionTest, SendAssignmentWithConvocationHttpTest, NotificationCycleTest, NotificationServiceTest.

### Proposta originale (riferimento fattibilità)

Richiesta: uniformare lo zonale al modello CRC nazionale — UNA mail con lettera nominativi al **circolo (TO)**, allegati convocazione + facsimile, e **tutti gli interessati in CC** (arbitri assegnati + istituzionali + eventuale zona).

**Fattibile, effort ~1 giorno + aggiornamento test.** I mattoni esistono già:

- `NotificationRecipientBuilder` (usato dal flusso nazionale) ha già: formato CC canonico `array<{email,name}>`, dedupe, validazione RFC con skip+log dei malformati, fallback "primo CC come TO". Vanno aggiunti due metodi: `addClub(Tournament)` (TO) e `addInstitutionalsByIds(array)` (CC).
- `ClubNotificationMail` resta l'unico Mailable del flusso zonale (con allegati); `RefereeAssignmentMail`/`InstitutionalNotificationMail` escono dal flusso zonale (restano per altri usi o si deprecano).
- `NotificationService::send()` si riduce a: costruisci destinatari dal **metadata del form** (fix D1 incluso gratis), una `Mail::to($club)->cc($interessati)->send(...)`, un solo esito → niente più `partial` ambiguo (fix D3 semplificato).

**Trade-off da decidere prima di implementare**:

1. *Privacy/visibilità*: in CC tutti vedono gli indirizzi di tutti (il flusso CRC nazionale già funziona così — culturalmente accettato in federazione).
2. *Personalizzazione persa*: oggi ogni arbitro riceve subject "Convocazione {ruolo} — {torneo}" personalizzato; col CC tutti ricevono la stessa mail intestata al circolo.
3. *Circolo senza email*: fallback come il nazionale (primo CC promosso a TO) oppure blocco con errore esplicito?
4. *Un solo invio = un solo punto di fallimento*: se l'SMTP rifiuta la mail, non riceve nessuno (oggi i fallimenti sono indipendenti per destinatario).

## 5. File chiave (mappa per chi entra nel progetto)

- **Visibilità/permessi**: `app/Support/TournamentVisibility.php` (source of truth), `app/Traits/HasZoneVisibility.php` (bridge), `app/Enums/UserType.php`, `RefereeLevel.php`
- **Disponibilità**: `app/Http/Controllers/User/AvailabilityController.php`
- **Assegnazioni**: `app/Http/Controllers/Admin/AssignmentController.php`, `app/Services/AssignmentValidationService.php`, `app/Observers/AssignmentObserver.php`
- **Notifiche**: `app/Http/Controllers/Admin/NotificationController.php`, `app/Services/Notification{Preparation,Document,Transaction}Service.php`, `NotificationRecipientBuilder.php`, `NotificationService.php` (solo `send()`), `app/Services/DocumentGenerationService.php`
- **Import FIG**: `app/Http/Controllers/Admin/FedergolfImportController.php`, `app/Services/FedergolfCommitteeService.php`
- **Modello dati**: `Tournament` (attenzione all'accessor `zone_id`), `TournamentNotification` (chiave logica `tournament_id`+`notification_type`), `Assignment`, `Availability`
