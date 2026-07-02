# Audit Comunicazioni Esterne — bug latenti
*2026-07-02 — analisi statica mirata su flusso notifiche (zonale + nazionale + disponibilità). Verifiche fatte contro codice vendor Laravel 12 e blade reali, non solo commenti.*

> **STATO FIX (2026-07-02):** C1–C3 e M1–M5 **risolti** in giornata:
> - C1 → rimossa transazione in `sendWithTransaction()` (invio inline, try/catch SMTP vivo)
> - C2 → `ClubNotificationMail::content()` ora passa `message_content` alla view
> - C3 → hidden input `send_to_club=0` in `prepare_notification.blade.php`
> - M1 → validation `exists`/`array` sui `cc_*` + filtri `user_type`/`is_active` nel builder
> - M2 → nuovo disk privato `docs` (`storage/app/docs`), `config('golf.documents.disk')`;
>   niente più `/storage/convocazioni/...` pubblico
> - M3 → allegati registrati ma assenti dal disco → `last_error` + status `partial`
> - M4 → preview (JS + `prepareEmailPreview`) mostra zona, istituzionali attivi, email aggiuntive
> - M5 → zero destinatari → status `failed` + messaggio errore (anche in `redirectAfterSend`)
>
> Regression test aggiunti: messaggio admin nel body (C2), allegato mancante→partial (M3),
> zero destinatari→failed (M5). Test esistenti allineati al disk `docs`.
> **Suite lanciata su MAMP: tutta verde (2026-07-02).**
>
> **DEPLOY (Aruba, FTP):** spostare `storage/app/public/convocazioni` →
> `storage/app/docs/convocazioni` e rimuovere la vecchia cartella (i file lì restano
> pubblici finché non vengono spostati). Se la config è cachata, rigenerare la cache.
>
> Minori (m1–m11): ancora aperti.

---

## 🔴 CRITICI

### C1 — Error handling invio zonale MORTO: stato `sent` scritto PRIMA dell'invio SMTP reale

Catena verificata:

1. `QUEUE_CONNECTION=sync` (`.env`)
2. Tutti i Mailable = `ShouldQueue` + `afterCommit()` (FIX A3/A4)
3. `NotificationTransactionService::sendWithTransaction()` apre `DB::beginTransaction()`
4. Dentro: `NotificationService::send()` → `Mail::to(...)->send(ClubNotificationMail)` → `PendingMail` vede `ShouldQueue` → **accoda**, non invia
5. `SyncQueue::push()` (verificato in vendor): con `afterCommit` + transazione attiva → `db.transactions->addCallback(...)` → il job SMTP parte **solo dentro `DB::commit()`**
6. Ma prima del commit, `NotificationService` ha già eseguito `$notification->update(['status' => 'sent', 'success_count' => N])`

Conseguenze:

- Il `try/catch` attorno a `$mailer->send()` (`NotificationService.php:137-162`) **non può mai catturare errori SMTP** — a quel punto la mail è solo accodata. `errorCount` per errori di invio = sempre 0.
- Errore SMTP reale → eccezione esplode dentro `DB::commit()` → catch di `sendWithTransaction` → `rollBack()` inutile (già committato) → l'admin vede "Errore nell'invio" ma **il DB dice `sent`** con `success_count` pieno.
- FIX D3 (redirect partial) copre solo "circolo senza email", mai i fallimenti SMTP.
- Al "reinvio" il record risulta già inviato → storico inaffidabile.

Nota: flusso nazionale NON affetto — `sendNationalNotification` invia PRIMA di aprire la transazione (nessuna transazione attiva → SyncQueue esegue subito → il try/catch per destinatario funziona).

**Fix suggerito**: nel flusso zonale, spostare l'invio fuori dalla transazione (stato `sending` → invio → update finale), oppure aggiornare lo stato in un callback post-invio. Con sync + afterCommit l'attuale architettura è auto-contraddittoria.

File: `app/Services/NotificationTransactionService.php:28-57`, `app/Services/NotificationService.php:96-182`

---

### C2 — Il messaggio scritto dall'admin NON arriva mai nella mail zonale

- Form salva `metadata['message']` → `NotificationService` lo passa a `ClubNotificationMail($tournament, $content, ...)`
- Il Mailable lo tiene in `public $content` → Laravel lo espone alla view come `$content`
- La view `emails/tournament_assignment_generic.blade.php:140` controlla **`$message_content`** — variabile mai passata da nessuno (grep su tutto il repo: unico riferimento è la view stessa)
- Risultato: `@if(!empty($message_content))` sempre falso → parte sempre il testo di default "Vi comunichiamo gli arbitri assegnati…"

Il testo personalizzato (istruzioni logistiche, orari, variazioni) viene **silenziosamente scartato** per circolo, arbitri e istituzionali. Aggravante: la preview (`prepareEmailPreview`) mostra il messaggio → l'admin crede che parta.

**Fix**: nella view usare `$content`, o nel Mailable passare `'message_content' => $this->content` nel `with`.

File: `app/Mail/ClubNotificationMail.php:64-89`, `resources/views/emails/tournament_assignment_generic.blade.php:140-141`

---

### C3 — Deselezionare "Invia notifica al circolo" non ha alcun effetto

- Blade `prepare_notification.blade.php:696`: checkbox `send_to_club` **senza hidden input** di fallback
- Checkbox deselezionata → chiave assente dalla request
- Controller: `$request->boolean('send_to_club', true)` → chiave assente → **default `true`**
- Il circolo riceve la mail comunque; `metadata.recipients.club` salvato `true` contro l'intento

Stesso pattern OK invece per `send_to_section` (default `false` → deselezione funziona).

**Fix**: `<input type="hidden" name="send_to_club" value="0">` prima della checkbox, oppure default `false` + `checked` gestito da `old()`.

File: `app/Http/Controllers/Admin/NotificationController.php:569,573`, `resources/views/admin/notifications/prepare_notification.blade.php:696`

---

## 🟠 MEDI

### M1 — `sendNationalNotification`: ID destinatari CC non validati

`cc_zone_admins`, `cc_referees`, `cc_national_admins`, `cc_observers` non compaiono in `$request->validate()`. Effetti:

- Nessun check `exists`, nessun filtro `user_type`/`zone`/`is_active`: i metodi `*ByIds` del builder fanno `User::whereIn('id', $ids)` nudo → un admin può mettere in CC **qualunque utente del DB** (form manipolato)
- Input scalare (es. `cc_referees=abc`) → `$request->input()` ritorna stringa → `addRefereesByIds(array)` → **TypeError 500**

File: `app/Http/Controllers/Admin/NotificationController.php:693-731`, `app/Services/NotificationRecipientBuilder.php:165-209`

### M2 — DOCX convocazioni/lettere pubblicamente scaricabili senza login

- Documenti salvati su disk `public` (`storage/app/public/convocazioni/SZR{n}/generated/`)
- Symlink `public/storage` presente → URL diretto `https://…/storage/convocazioni/SZR6/generated/convocazione_123_Nome_Torneo.docx`
- Nomi file **prevedibili**: `convocazione_{tournament_id}_{slug50}.docx` (`DocumentGenerationService.php:80-82`)
- Contengono dati personali arbitri. Il download autenticato via `downloadDocument` esiste, ma il file resta raggiungibile bypassando auth e check di zona

**Fix**: disk privato + streaming solo via route autenticata.

### M3 — Allegati mancanti = mail parte senza convocazione, in silenzio

Doppio skip silenzioso: `NotificationService::buildAttachments()` (`file_exists` → continue) e `ClubNotificationMail::attachments()` (solo `Log::warning`). File cancellato/rinominato/regeneration fallita → il circolo riceve la mail **senza lettera né convocazione**, stato `sent`, nessun `partial`, admin ignaro. La view mostra pure il box "📎 Documenti allegati" basato su `count($attachmentPaths)` (path richiesti, non file reali).

**Fix**: allegato richiesto ma assente → errore tracciato (`errorCount++`) o blocco pre-invio.

File: `app/Services/NotificationService.php:229-245`, `app/Mail/ClubNotificationMail.php:94-122`

### M4 — Preview email non corrisponde all'invio reale

`prepareEmailPreview()` mostra solo club + referees + institutional. Mancano: **sezione di zona** (`send_to_section`) e **email aggiuntive** (`additional`) — entrambe inviate davvero. Inoltre institutional in preview senza filtro `is_active` (l'invio filtra). Combinato con C2 (mostra un messaggio che non parte) la preview è doppiamente ingannevole.

File: `app/Services/NotificationPreparationService.php:184-211`

### M5 — Invio a zero destinatari termina `sent`

`NotificationService::send()`: se il builder finisce vuoto (es. tutti gli arbitri selezionati nel frattempo rimossi dalle assegnazioni, club non richiesto/da metadata legacy) → `isEmpty` → nessuna mail, `errorCount=0` → `status='sent'`, `success_count=0`. L'unico errore tracciato è "club richiesto senza email". Nessuna comunicazione parte ma lo storico dice inviato.

**Fix**: `isEmpty` → status `failed` o redirect con errore.

File: `app/Services/NotificationService.php:136-166`

---

## 🟡 MINORI

| # | Problema | Dove |
|---|----------|------|
| m1 | `attach_convocation`: nessun campo nel form (grep blade: assente) → sempre `true`. Il "flag onorato" del refactor 2026-06 è irraggiungibile dalla UI | `prepare_notification.blade.php` |
| m2 | `sendAssignmentWithConvocation` prende `latest()` senza filtro `notification_type` e senza guard `is_national` → su torneo nazionale può trasformare il record `crc_referees` in bozza zonale e inviare mail stile-circolo | `NotificationController.php:548-550` |
| m3 | Dedup email solo intra-lista: stesso indirizzo può stare in TO e CC (es. email circolo anche tra le aggiuntive) | `NotificationRecipientBuilder.php:296-305` |
| m4 | `checkNotificationAccess()` salta il check se `tournament` null → notifiche orfane accessibili/eliminabili da qualunque admin | `NotificationController.php:46-51` |
| m5 | `addRefereesByIds`/`addZoneAdminsByIds`/`addObserversByIds` senza `is_active` (incoerente con `addInstitutionalsByIds`) | `NotificationRecipientBuilder.php:165-209` |
| m6 | `ClubNotificationMail::content()`: `ZoneHelper::getEmailPattern(int)` con `zone_id` null → TypeError → invio fallisce (torneo con club senza zona) | `ClubNotificationMail.php:83` |
| m7 | `successCount += count($all)`: conta destinatari di UNA mail come N invii riusciti — statistiche gonfie prima ancora dell'invio reale (v. C1) | `NotificationService.php:154` |
| m8 | Password SMTP Aruba in chiaro nei commenti di `.env` — se il file gira in backup/condivisioni, credenziale esposta. Ruotarla se già circolata | `.env` |
| m9 | `loadFormData` mostra le InstitutionalEmail di TUTTE le zone (esiste `scopeForZone`, mai usato qui) → admin zonale può notificare istituzionali di altre zone | `NotificationPreparationService.php:105-108` |
| m10 | `uploadDocument`: filename = nome originale client (solo spazi→underscore) → upload con stesso nome in zone uguali si sovrascrivono tra notifiche | `NotificationDocumentService.php:202-220` |
| m11 | Batch disponibilità: errori invio mail solo loggati, l'arbitro riceve sempre "successo"; admin di zone diverse ricevono un'unica mail cumulativa con TO condiviso (indirizzi visibili tra loro) | `AvailabilityController.php:414-565` |

---

## Priorità intervento

1. **C1** — rompe il contratto "il DB riflette cosa è stato inviato": è la premessa di tutto il workflow di notifica
2. **C2 + C3** — l'admin crede di controllare contenuto e destinatari, il sistema fa altro
3. **M2** — privacy dati arbitri (prodotto live su Aruba)
4. **M1, M3–M5** — robustezza
5. Minori a seguire

*Nota metodo: C1 verificato leggendo `vendor/laravel/framework/src/Illuminate/Queue/SyncQueue.php::push()` e `SendQueuedMailable` (copia `afterCommit` dal Mailable). C2/C3 verificati con grep su repo e lettura blade. Nessun test eseguito (suite vincolata a MAMP, v. STORICO).*
