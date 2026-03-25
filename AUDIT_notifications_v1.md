# Audit Workflow Notifiche — golf-arbitri-clean
**Data:** 2026-03-24
**Scope:** Analisi approfondita del sistema di notifiche email
**File analizzati:** 6 servizi, 1 controller (870 righe), 4 modelli, 2 migrazioni, 10 classi Mail, 2 file di test, 1 file di route

---

## Indice

- [Mappa del sistema](#mappa-del-sistema)
- [CRITICO — Crash / Perdita dati](#critico)
- [ALTO — Comportamento scorretto](#alto)
- [MEDIO — Architettura / Duplicazione](#medio)
- [BASSO — Performance / Odori](#basso)
- [Test Coverage](#test-coverage)
- [Piano d'azione prioritizzato](#piano-dazione)

---

## Mappa del sistema

Il sistema notifiche è composto da **due sottosistemi paralleli** che coesistono senza una chiara separazione:

```
┌─────────────────────────────────────────────────────────────────┐
│  SISTEMA 1 — Notifiche Torneo Zonale/Nazionale                  │
│                                                                 │
│  NotificationController (870 righe)                             │
│    ├── NotificationPreparationService   (prepara bozza)         │
│    ├── NotificationTransactionService  (invia + salva)          │
│    ├── NotificationDocumentService     (genera DOCX)            │
│    ├── NotificationRecipientBuilder    (NON iniettato)          │
│    └── NotificationService             (NON iniettato)          │
│                                                                 │
│  Modello: TournamentNotification (tabella tournament_notifications)│
│  Clausole: NotificationClause + NotificationClauseSelection     │
│                                                                 │
│  Mail usate: TournamentNotificationMail, ClubNotificationMail,  │
│              RefereeAssignmentMail, InstitutionalNotificationMail│
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  SISTEMA 2 — Notifiche Disponibilità Arbitri                    │
│                                                                 │
│  AvailabilityController                                         │
│    └── AvailabilityNotificationService  (invia email)           │
│                                                                 │
│  Modello: Notification (tabella notifications)                  │
│           + AssignmentNotification (usa Notification model)     │
│                                                                 │
│  Mail usate: BatchAvailabilityAdminNotification,                │
│              RefereeAvailabilityConfirmation ⚠️ ZOMBIE          │
│              NationalAvailabilityNotification ⚠️ ZOMBIE         │
│              ZonalAvailabilityNotification    ⚠️ ZOMBIE         │
└─────────────────────────────────────────────────────────────────┘
```

I due sistemi si sovrappongono: `TournamentNotification::individualNotifications()` tenta di collegare i record delle due tabelle tramite una finestra temporale di ±5/10 minuti su `sent_at`.

---

## CRITICO

### C-1 — Tre classi Mail zombie che crashano in produzione

**File:** `app/Mail/NationalAvailabilityNotification.php`, `RefereeAvailabilityConfirmation.php`, `ZonalAvailabilityNotification.php`

**Problema:** Tutte e tre le classi hanno costruttori vuoti e puntano a una view inesistente:
```php
// Esempio — identico per tutte e tre
public function content(): Content
{
    return new Content(view: 'view.name');  // 'view.name' NON ESISTE
}
```
Se queste classi venissero istanziate e inviate, Laravel lancerebbe `InvalidArgumentException: View [view.name] not found.` in produzione, senza alcun errore visibile a compile-time.

**Impatto:** Crash immediato se richiamate.
**Azione:** Eliminare i tre file o completarli con view e costruttore reali. Se non sono mai usate, eliminare.

---

### C-2 — `resendNationalNotification()` scrive su una colonna inesistente

**File:** `app/Http/Controllers/Admin/NotificationController.php`, riga ~444

**Problema:** Il metodo `resendNationalNotification()` chiama:
```php
$notification->update([
    ...
    'total_recipients' => $totalRecipients,  // ❌ colonna NON nella migrazione
    ...
]);
```
La colonna `total_recipients` **non esiste nella tabella `tournament_notifications`** (verificato nella migrazione `2025_08_29_000004`). Poiché `total_recipients` non è nemmeno in `$fillable`, Eloquent ignora silenziosamente la chiave. Il dato viene perso senza errori.

Il metodo gemello `sendNationalNotification()` (riga ~847) salva correttamente `total_recipients` dentro il JSON `details`, ma il reinvio no.

**Impatto:** Perdita silenziosa di dati. Il campo `total_recipients` nel reinvio risulta sempre 0/null nella UI.
**Azione:** Sostituire la riga con `'details' => array_merge($metadata['details'] ?? [], ['total_recipients' => $totalRecipients])`.

---

### C-3 — FK mancante su `notification_clause_selections`

**File:** `database/migrations/2025_08_29_000005_create_notification_clauses_table.php`

**Problema:** La migrazione usa `foreignId()` senza `->constrained()`:
```php
$table->foreignId('tournament_notification_id')->unsigned();  // ❌ nessun FK
$table->foreignId('clause_id')->unsigned();                   // ❌ nessun FK
```
Non c'è vincolo di integrità referenziale. Eliminare una `TournamentNotification` (via `NotificationController::destroy()`) non rimuove le clausole correlate. MySQL con InnoDB non scatena cascade, creando record orfani che consumano spazio e producono errori se si tenta di caricarli.

**Impatto:** Data corruption progressiva.
**Azione:** Aggiungere una migration correttiva che aggiunga i FK con `onDelete('cascade')`.

---

## ALTO

### A-1 — Email di zona hardcoded in `AvailabilityNotificationService`

**File:** `app/Services/AvailabilityNotificationService.php`, righe ~220-231

**Problema:** Il metodo `getZoneEmail()` usa un array PHP statico per mappare ID zona → email:
```php
private function getZoneEmail(int $zoneId): string
{
    $zoneEmails = [
        1 => 'szr1@federgolf.it',
        2 => 'szr2@federgolf.it',
        // ...
    ];
    return $zoneEmails[$zoneId] ?? 'zona@federgolf.it';
}
```
La tabella `zones` ha già una colonna `email`. Cambiare l'email di una zona richiede una modifica al codice sorgente e un nuovo deploy. Il dato nel DB è ignorato.

**Impatto:** Disallineamento tra DB e comportamento applicativo.
**Azione:** Sostituire con `Zone::find($zoneId)?->email ?? config('golf.emails.fallback_zone')`.

---

### A-2 — `getCrcEmail()` ignora la configurazione

**File:** `app/Services/AvailabilityNotificationService.php`

**Problema:**
```php
private function getCrcEmail(): string
{
    return 'crc@federgolf.it';  // ❌ hardcoded
}
```
`config/golf.php` già definisce `'emails' => ['crc' => '...']`. La configurazione esiste ma non viene usata.

**Impatto:** Cambio email CRC richiede modifica al codice, non alla config.
**Azione:** `return config('golf.emails.crc', 'crc@federgolf.it');`

---

### A-3 — `sendNationalNotification()` e `resendNationalNotification()` usano `Mail::raw()`

**File:** `app/Http/Controllers/Admin/NotificationController.php`, righe ~772, ~409

**Problema:** Entrambi i metodi compongono ed inviano email nazionali usando `Mail::raw($message, ...)` — email in testo puro, senza classe Mailable, senza template, senza branding, senza allegati, senza tracciabilità nel modello `Notification`. Il messaggio è inserito dal form come testo libero.

Mentre le notifiche zonali usano `TournamentNotificationMail` (con template Blade, allegati DOCX, branding), le notifiche nazionali escono come raw text. Comportamento incoerente e non estendibile.

**Impatto:** Email nazionali differiscono visualmente dalle zonali. Nessun allegato possibile. Nessun record in `notifications` table.
**Azione:** Creare `NationalTournamentNotificationMail` (o unificare `TournamentNotificationMail`) e usarlo per entrambe le tipologie.

---

### A-4 — `TournamentNotification::individualNotifications()` join via finestra temporale

**File:** `app/Models/TournamentNotification.php`, righe 117-125

**Problema:**
```php
public function individualNotifications(): HasMany
{
    return $this->hasMany(Notification::class, 'tournament_id', 'tournament_id')
        ->when($this->sent_at, function ($query) {
            $sentAt = $this->sent_at->copy();
            $query->where('created_at', '>=', $sentAt->subMinutes(5))
                  ->where('created_at', '<=', $sentAt->addMinutes(10));
        });
}
```
La relazione non usa una FK diretta (`tournament_notification_id`), ma filtra per `tournament_id` + finestra di 15 minuti attorno a `sent_at`. Se più notifiche vengono inviate per lo stesso torneo a distanza ravvicinata, i record verranno mischiati. Se il server è lento, record legittimi potrebbero cadere fuori dalla finestra.

**Impatto:** Correlazione dati inaffidabile; la UI di dettaglio notifica mostra potenzialmente record sbagliati.
**Azione:** Aggiungere `tournament_notification_id` FK alla tabella `notifications`, oppure eliminare questa relazione se non usata.

---

### A-5 — `getStatsAttribute()` dual-format produce dati incorretti

**File:** `app/Models/TournamentNotification.php`, righe 188-218

**Problema:** L'accessor gestisce due formati JSON storici per il campo `details`:
- **Formato vecchio:** `{"sent": 4, "arbitri": 3, "club": 1}`
- **Formato nuovo:** `{"club": {"sent": 1, "failed": 0}, "referees": {"sent": 3, "failed": 0}, ...}`

Il formato vecchio viene rilevato da `isset($details['sent'])` e restituisce `club_failed: 0`, `referees_failed: 0`, `success_rate: 100.0` — sempre, indipendentemente dagli errori reali. Record storici salvati con il vecchio formato mostrano sempre "100% successo" in UI anche se ci sono stati fallimenti.

**Impatto:** Dashboard notifiche mostra statistiche errate per dati storici.
**Azione:** Migrare i dati esistenti al nuovo formato (migration di dati), poi rimuovere il ramo vecchio.

---

## MEDIO

### M-1 — `prepareNotification()` duplicata in due servizi

**File:** `app/Services/NotificationService.php` (righe 27-48) + `app/Services/NotificationPreparationService.php` (righe 21-33)

**Problema:** Entrambi i servizi implementano `prepareNotification(Tournament $tournament)` chiamando `TournamentNotification::firstOrCreate(...)`. Differenze sottili:

| | `NotificationService` | `NotificationPreparationService` |
|---|---|---|
| Default fields | `recipients`, `status` | `recipients`, `content`, `documents`, `metadata`, `status`, `workflow_status` |
| `total_recipients` in details | ✅ | ✅ |
| Usato da | `NotificationServiceTest`, `NotificationCycleTest` | `NotificationController::sendAssignmentWithConvocation()` |

Se `NotificationService::prepareNotification()` crea una bozza e poi il controller chiama `NotificationPreparationService::prepareNotification()`, il secondo `firstOrCreate` recupera il record esistente e può sovrascrivere `content` e `metadata` con valori vuoti.

**Azione:** Unificare in `NotificationPreparationService`. `NotificationService` deve delegare.

---

### M-2 — `saveClauseSelections()` duplicata

**File:** `app/Services/NotificationPreparationService.php` (righe ~180-210) + `app/Services/NotificationTransactionService.php` (righe ~150-180)

**Problema:** La logica di `delete + recreate` delle clausole è copiata in entrambi. Viene chiamata in contesti diversi (AJAX via `saveClauses` endpoint → `PreparationService`; invio completo → `TransactionService`) ma fa esattamente la stessa cosa.

**Azione:** Spostare in `NotificationPreparationService`, far delegare `NotificationTransactionService`.

---

### M-3 — `sendToZone()` e `sendToCrc()` sono metodi identici

**File:** `app/Services/AvailabilityNotificationService.php`

**Problema:** I due metodi differiscono solo per il messaggio di log. Entrambi inviano `BatchAvailabilityAdminNotification`. Ogni modifica va replicata manualmente.

**Azione:** Unificare in `sendToAdminRecipient(string $email, string $label): void`.

---

### M-4 — Quattro classi Mail per lo stesso scopo (notifica torneo)

**File:** `app/Mail/`

| Classe | API | Stato |
|--------|-----|-------|
| `TournamentNotificationMail` | Moderna (Envelope/Content) | Completa, attiva |
| `ClubNotificationMail` | Legacy | Duplica TournamentNotificationMail per clubs |
| `RefereeAssignmentMail` | Legacy | Duplica TournamentNotificationMail per arbitri |
| `InstitutionalNotificationMail` | Legacy | Duplica TournamentNotificationMail per istituzionali |

`TournamentNotificationMail` è stata scritta per unificarle ma le vecchie non sono state rimosse. `NotificationService::send()` usa `TournamentNotificationMail`; alcune path secondarie usano ancora le legacy.

**Azione:** Verificare tutti i riferimenti alle classi legacy, migrare al nuovo, eliminare le tre legacy.

---

### M-5 — `NotificationController` bypass del service layer per notifiche nazionali

**File:** `app/Http/Controllers/Admin/NotificationController.php`

**Problema:** Il controller inietta 3 servizi nel costruttore ma ignora `NotificationService` e `NotificationRecipientBuilder` — proprio i servizi pensati per costruire destinatari e inviare email. Per le notifiche nazionali (`sendNationalNotification`, `resendNationalNotification`) il controller costruisce manualmente gli array destinatari e chiama `Mail::raw()` direttamente. `NotificationRecipientBuilder` (con il suo pattern fluent) non viene mai usato per il caso nazionale.

**Impatto:** Logica di business nel controller. Impossibile testare unitariamente. Duplicazione dei pattern di costruzione destinatari.
**Azione:** Iniettare `NotificationService` e usarlo per delegare l'invio; `NotificationRecipientBuilder` per costruire i destinatari.

---

### M-6 — `AssignmentNotification` usa il modello sbagliato

**File:** `app/Mail/AssignmentNotification.php`

**Problema:** Questo Mailable accetta `App\Models\Notification` nel costruttore — il modello della tabella `notifications` (log per-email) — mentre tutti gli altri Mailable per tornei accettano `TournamentNotification`. Crea confusione sul quale modello trasporta i dati verso le view.

**Azione:** Standardizzare: tutti i Mailable di torneo ricevono `TournamentNotification` o parametri primitivi (non modelli di log interni).

---

## BASSO

### B-1 — N+1 con side-effect in `NotificationController::index()`

**File:** `app/Http/Controllers/Admin/NotificationController.php`, righe 51-53

**Problema:**
```php
foreach ($allNotifications as $notification) {
    $this->preparationService->updateRecipientInfo($notification);  // N query + N UPDATE
}
```
Per ogni notifica nella lista viene eseguita una query + eventuale UPDATE. Si tratta di un side-effect (scrittura DB) in una action di sola lettura. Se ci sono 100 notifiche, sono 100+ query aggiuntive ad ogni caricamento della pagina.

**Azione:** Spostare `updateRecipientInfo` fuori dal loop di lista (chiamarlo solo in `show`/`edit`), oppure rendere il metodo read-only e aggiornare i dati tramite job asincrono.

---

### B-2 — `@property int $total_recipients` nel docblock di `TournamentNotification` è una colonna fantasma

**File:** `app/Models/TournamentNotification.php`, riga 14

**Problema:** La property `$total_recipients` appare nel docblock PHPDoc ma la colonna non esiste nella migrazione. Il dato è ora salvato in `details->total_recipients` (JSON). Il docblock errato genera falsi positivi in IDE e analisi statica.

**Azione:** Rimuovere `@property int $total_recipients` dal docblock.

---

### B-3 — `canBeResent()` restituisce sempre `true`

**File:** `app/Models/TournamentNotification.php`, righe 278-285

**Problema:**
```php
public function canBeResent(): bool
{
    // Permetti sempre reinvio dopo 1 ora per testing
    if ($this->sent_at && $this->sent_at->lt(now()->subHour())) {
        return true;
    }
    return true;  // ← restituisce sempre true
}
```
La condizione del primo `if` è irrilevante perché il metodo ritorna `true` comunque. Il commento "per testing" è rimasto in produzione. Notifiche inviate 5 secondi fa possono essere reinviate immediatamente.

**Azione:** Decidere la logica corretta e implementarla; rimuovere il commento di debug.

---

### B-4 — `getGlobalStats()` conta record, non email inviate

**File:** `app/Models/TournamentNotification.php`, righe 337-350

**Problema:**
```php
'total_recipients_reached' => self::where('status', 'sent')->count(),
```
Questa stat conta il numero di record `TournamentNotification` con status 'sent', non il numero di email effettivamente consegnate. Il nome `total_recipients_reached` è fuorviante.

**Azione:** Rinominare in `total_notifications_sent` o calcolare la somma di `details->total_recipients` su tutti i record.

---

## Test Coverage

### Stato attuale

| Test | Suite | Copertura |
|------|-------|-----------|
| `NotificationCycleTest` (15 test) | Feature | ⚠️ 12/15 saltati senza DB live. Solo 3 girano con SQLite |
| `NotificationServiceTest` (9 test) | Unit | ✅ Girano con SQLite, buona copertura di `prepareNotification` |
| `AvailabilityNotificationServiceTest` | Unit | Parziale |

### Gap critici

1. **`sendNationalNotification()`** — zero test. Il metodo più complesso del controller (90 righe) non ha un singolo test.
2. **`resendNationalNotification()`** — zero test. Il bug C-2 (`total_recipients`) non sarebbe rilevato da alcun test.
3. **Zombie Mail stubs** — non ci sono test che verifichino che queste classi NON vengano chiamate.
4. **`getZoneEmail()` con array hardcoded** — nessun test verifica il disallineamento con il DB.
5. **`NotificationCycleTest`** usa `requireDatabase()` come guard per 12 test → in CI (SQLite) questi test vengono ignorati silenziosamente.

---

## Piano d'azione

### Fase 1 — Fix immediati (senza refactoring)

| # | Azione | File | Priorità |
|---|--------|------|----------|
| 1 | Eliminare i 3 zombie Mail stub | `app/Mail/National*.php`, `RefereeAvailability*.php`, `Zonal*.php` | CRITICO |
| 2 | Fix `resendNationalNotification()`: salvare `total_recipients` nel JSON `details` | `NotificationController.php` ~riga 444 | CRITICO |
| 3 | Aggiungere FK migration per `notification_clause_selections` | Nuova migration | CRITICO |
| 4 | Sostituire `getZoneEmail()` con query su `Zone::find()` | `AvailabilityNotificationService.php` | ALTO |
| 5 | Sostituire `getCrcEmail()` con `config('golf.emails.crc')` | `AvailabilityNotificationService.php` | ALTO |
| 6 | Rimuovere `@property int $total_recipients` dal docblock | `TournamentNotification.php` | BASSO |
| 7 | Correggere `canBeResent()` | `TournamentNotification.php` | BASSO |

### Fase 2 — Consolidamento (con test)

| # | Azione | Servizi coinvolti |
|---|--------|-------------------|
| 8 | Unificare `prepareNotification()` in `NotificationPreparationService` | `NotificationService`, `NotificationPreparationService` |
| 9 | Unificare `saveClauseSelections()` | `NotificationPreparationService`, `NotificationTransactionService` |
| 10 | Unificare `sendToZone()` / `sendToCrc()` | `AvailabilityNotificationService` |
| 11 | Scrivere test per `sendNationalNotification` e `resendNationalNotification` | `NotificationController` |
| 12 | Migrazione dati: normalizzare formato `details` JSON | DB + `TournamentNotification` |

### Fase 3 — Refactoring architetturale (ADR richiesto)

| # | Azione |
|---|--------|
| 13 | Decidere il destino della tabella `notifications`: eliminarla (unificare su `tournament_notifications`) o formalizzarne il ruolo come log per-email |
| 14 | Iniettare `NotificationService` + `NotificationRecipientBuilder` nel controller e delegare l'invio nazionale |
| 15 | Eliminare i 3 Mailable legacy (`ClubNotificationMail`, `RefereeAssignmentMail`, `InstitutionalNotificationMail`) dopo migrazione a `TournamentNotificationMail` |
| 16 | Aggiungere FK diretta `tournament_notification_id` alla tabella `notifications` per rimpiazzare il join temporale |

---

## Riepilogo severity

| Livello | Numero problemi |
|---------|----------------|
| CRITICO | 3 |
| ALTO | 5 |
| MEDIO | 6 |
| BASSO | 4 |
| **Totale** | **18** |

I 3 problemi CRITICI (zombie Mail, colonna fantasma, FK mancante) sono correggibili in poche righe ciascuno e dovrebbero essere applicati prima del prossimo deploy.
