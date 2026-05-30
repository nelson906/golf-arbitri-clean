# Analisi Finale — Funzionalità reale e rischi di pubblicazione

*Golf Arbitri (Laravel 12) — generato il 2026-05-30*
*Scope: funzionamento effettivo del prodotto e rischio di andare in produzione data la carenza di test funzionali*
*Metodo: lettura diretta del codice + verifica dello stato dei finding storici. La suite non è stata eseguita in questa sessione (ambiente sandbox senza PHP/MySQL), ma i fatti d'ambiente sono stati verificati.*

> **Nota di revisione (2026-05-30, post-feedback utente).** Tre asserzioni della prima stesura sono state corrette con i fatti forniti dall'utente:
> 1. **Deploy reale**: il codice è in produzione su Aruba, sottodominio `arbitrigolf.golfrating.it`, con invio via SMTP Aruba (`smtps.aruba.it`). Non è quindi un progetto pre-lancio: è già live. Questo *alza* la posta in gioco delle sezioni R1–R3.
> 2. **Le notifiche SONO testate in locale con Mailpit** (`.env`: `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`). Questo è importante: Mailpit è un vero server SMTP, quindi il path di invio — incluso il parsing RFC degli indirizzi che `Mail::fake()` non esercita — *viene* collaudato manualmente. È esattamente così che il bug del formato CC del 10 maggio è stato trovato. La mia affermazione "il send è cieco" era troppo forte: il send è **collaudato manualmente, ma non protetto da regressione automatica**.
> 3. **Il repo GitHub esiste ed è aggiornato** (`github.com/nelson906/golf-arbitri-clean`, branch `main` + branch di lavoro). Manca però ancora il **workflow CI**: nessun `.github/workflows/`. Il repo c'è, il cancello automatico no — quindi il punto §5.1 resta valido ed è ora ancora più economico (il repo è già su GitHub, serve solo aggiungere la Action).

---

## 0. Verdetto sintetico

**🟡 GIALLO — già in produzione su Aruba; il rischio non è "se pubblicare" ma "regressione silenziosa del già-live". Va messa la rete di sicurezza automatica, non fermato il lancio.**

Il prodotto **funziona davvero** ed è già online: il workflow centrale (arbitro dichiara disponibilità → admin assegna → sistema notifica) è implementato end-to-end, con un service layer reale e diversi bug critici storici già chiusi. Non è un prototipo, è un sistema in esercizio.

Il rischio **non è architetturale e non è di sicurezza**: è concentrato in un punto solo, ed è esattamente quello che l'utente ha intuito. Il **processo più importante — la notifica a circoli, arbitri e indirizzi istituzionali — è collaudato manualmente in locale (Mailpit) ma non protetto da test di regressione automatici**, ed è anche quello con lo storico di bug più infidi (formato CC, dati sporchi). Manca il workflow CI: nessun cancello automatico ferma una regressione prima che la incontri un utente reale in produzione.

In una frase: **non si romperà l'applicazione, si romperà — silenziosamente — l'invio di una notifica dopo un commit che nessun test ha controllato**. E nel dominio arbitri di golf, su un sistema già live, una convocazione non partita o partita all'indirizzo sbagliato è il fallimento più costoso possibile.

---

## 1. Cosa funziona davvero (verificato sul codice, non sulla documentazione)

Questa sezione è la parte ottimista, ma è fondata su lettura diretta.

Il **service layer è reale e non decorativo**: 13 service specializzati (`NotificationPreparationService`, `NotificationDocumentService`, `NotificationTransactionService`, `NotificationRecipientBuilder`, `AssignmentValidationService`, più i gruppi `Statistics/*` e `Monitoring/*`). La pipeline di notifica è separata in fasi reali, non è logica spalmata nei controller.

La **distinzione zona ↔ CRC**, che le istruzioni di progetto indicano come il cuore della gestione, è codificata e — soprattutto — **testata**: `NotificationNationalZonalClassificationTest` contiene 12 test che coprono la classificazione zonale/nazionale, l'auto-creazione del tipo corretto (`null` per zonale, `crc` per nazionale), il caso di metadati corrotti, e il `mark_notified`. Questa è la parte di dominio meglio protetta del progetto, ed è quella giusta da proteggere.

Il **workflow disponibilità arbitro** ha copertura funzionale solida: `AvailabilityManagementTest` (10 test) verifica isolamento per zona, blocco oltre deadline, divieto di doppia dichiarazione, autorizzazione (admin/guest non possono), timestamp automatico. `AssignmentManagementTest` (10 test) copre i ruoli, il divieto di doppia assegnazione, l'autorizzazione.

**Diversi finding critici storici sono effettivamente chiusi** (verificato nel codice attuale, non solo nei report):

| Finding storico | Stato reale oggi |
|---|---|
| `Mail::raw()` nel controller (XSS/injection email) | ✅ Sostituito da `NationalNotificationMail` + view con `nl2br(e($body))` |
| `Carbon::parse(null)` su `end_date` → falsi conflitti a cascata | ✅ Corretto in `AssignmentValidationService::datesOverlap()` (righe 358-364) |
| `AvailabilityRequest::authorize()===false` (trappola dead code) | ✅ File eliminato |
| Formato CC associativo → crash RFC 2822 in invio reale | ✅ `build()` ora restituisce `array<{email,name}>` canonico |
| Email malformate in DB bloccano l'intera notifica | ✅ Validazione difensiva `filter_var` con skip + `Log::warning` |
| XSS da `{!! session() !!}` | ✅ Pattern rimosso dai template |

Numericamente: **49 file di test, ~920 asserzioni**. Per un gestionale verticale costruito a tappe, è una base migliore della media. Il problema non è "non ci sono test" — è *dove* non ci sono.

---

## 2. Il vincolo che amplifica ogni altro rischio: nessuna rete di sicurezza automatica

Due fatti strutturali, da soli, spostano il verdetto da verde a giallo:

**Non esiste CI.** Nessun `.github/workflows/`. I test esistono ma vengono eseguiti solo quando qualcuno se li ricorda, a mano.

**La suite è vincolata a una sola macchina.** `.env.testing` punta a `DB_CONNECTION=mysql` su `127.0.0.1:8889` con socket `/Applications/MAMP/tmp/mysql/mysql.sock`. I test non girano in nessun ambiente che non sia il MAMP di quello specifico computer — non su un server, non in un container, non in pipeline. La nota operativa nell'audit lo conferma indirettamente ("MAMP usa OPcache aggressivo... serve restart Apache").

La conseguenza è la cosa importante, non il dettaglio tecnico: **le 920 asserzioni proteggono il progetto solo nei momenti in cui qualcuno decide manualmente di eseguirle, sull'unica macchina dove girano.** In pratica, fra una sessione di lavoro e la successiva, il sistema *in produzione* è privo di rete di sicurezza. Ogni commit sui quadranti o sull'import FIG (gli ultimi 10 commit sono quasi tutti lì) potrebbe aver rotto la notifica senza che nulla lo segnali finché un circolo non riceve l'email.

Il collaudo manuale con **Mailpit** in locale è reale e prezioso — è il motivo per cui il bug del CC è stato trovato — ma è discrezionale e non versionato: dipende dal fatto che lo sviluppatore si ricordi di provare l'invio prima di un deploy. Non è un controllo, è un'abitudine. La CI serve a trasformarlo in un controllo.

> Prima del go-live, l'intervento a più alto rendimento di tutto il progetto non è scrivere nuovi test: è **rendere eseguibili quelli esistenti fuori da MAMP** (un connection `sqlite`/`:memory:` per il testing, o un MySQL in container) e **agganciarli a una GitHub Action**. Costo: poche ore. Beneficio: trasforma 920 asserzioni latenti in un cancello reale.

---

## 3. Dove si romperà in produzione — rischi ordinati per probabilità × impatto

Non un elenco di difetti astratti, ma una previsione di *cosa* cederà *prima*.

### 🔴 R1 — La notifica zonale parte ma silenziosamente incompleta (alta probabilità, alto impatto)

È il rischio numero uno e merita di essere capito bene.

Il processo più importante del prodotto — invio a **circolo + arbitri** (zonale) o a **CRC + osservatori di zona** (nazionale) — costruisce i destinatari a runtime dal DB tramite `NotificationRecipientBuilder`. La copertura di test su questo punto è **cieca dove conta**:

- `NotificationServiceTest` e `NotificationCycleTest` usano `Mail::fake()` e **mockano** il `DocumentGenerationService`. Verificano che *qualcosa* venga preparato, non *a chi* arriva e *con che contenuto*.
- L'unico test che tocca il dispatch reale (`NationalNotificationMailDispatchTest`) verifica che il Mailable nazionale venga spedito con subject/body integri — utile, ma è il ramo *nazionale*, ed è ancora `Mail::fake()`.
- **Non esiste un test di integrazione che parta da un torneo zonale reale, costruisca i destinatari veri dal DB (email circolo + arbitri assegnati), e asserisca che quei destinatari precisi sono nella lista di invio.**

Perché questo è il rischio peggiore e non un cavillo: l'audit stesso documenta che il bug del formato CC del 10 maggio **è esploso solo al primo invio reale**, perché `Mail::fake()` (usato dalla suite automatica) non esercita il parsing degli indirizzi di Symfony. Cito testualmente il finding: *"Test unit con `Mail::fake()` hanno copertura cieca sul rendering effettivo della view e sul parsing degli address."* Il collaudo manuale via Mailpit *vede* quella classe di bug — ma solo quando lo si esegue, a mano. La classe di bug più probabile in produzione è proprio quella che la suite **automatica**, per costruzione, non può vedere, e che oggi è coperta solo da un collaudo discrezionale. Il send registra lo stato `partial`/`failed`, quindi un destinatario perso **non genera errore** — genera una convocazione che il circolo non riceve e nessuno se ne accorge finché l'arbitro si presenta a un torneo che il circolo non sapeva.

> Per chiudere questo punto al livello giusto, i test consegnati con questa analisi (§6) usano due livelli complementari: `Mail::fake()` per asserire *chi* riceve (targeting, logica di intersezione), e l'assemblaggio reale del messaggio per il *parsing degli indirizzi* (la classe di bug che `fake` non vede). È la stessa distinzione che il collaudo Mailpit fa a mano, resa automatica e versionata.

### 🟠 R2 — Dati sporchi che diventano notifiche sbagliate (alta probabilità, impatto medio-alto)

Non è un rischio di codice, è un rischio di *dato*, e il codice oggi lo **nasconde** invece di segnalarlo.

L'audit documenta due categorie di sporco già presenti nel DB di produzione: **zone con il nome al posto dell'email** (`zones.email = "Sezione Zonale..."`) e **record importati da FIG con `documents=NULL` e `metadata.subject/message` assenti**. La difesa attuale (`filter_var` → skip + `Log::warning`) evita il crash, ma il comportamento risultante è: *la notifica parte, semplicemente senza quel destinatario, e l'unica traccia è una riga di log che nessuno legge.* In termini di prodotto, "fallire rumorosamente" sarebbe più sicuro di "riuscire silenziosamente a metà". Non c'è oggi nessun controllo che, prima dell'invio, dica all'admin "attenzione: 2 destinatari verranno saltati perché l'email non è valida".

### 🟡 R2-bis — Un'email circolo mancante blocca l'intera notifica zonale (probabilità bassa, impatto alto quando capita)

Emerso scrivendo i test di questa sessione. In `NotificationService::send()` l'invio agli arbitri e agli istituzionali è protetto da un `try/catch` per destinatario (un fallimento isolato non ferma gli altri). L'invio al **circolo no**: `sendToClub()` è chiamato fuori da un try/catch interno e lancia `Club email not found` se il circolo non ha email. Quell'eccezione risale al catch esterno → stato `failed` per l'intera notifica, **e gli arbitri non ricevono nulla**, perché il send al circolo precede il loop arbitri. In pratica: un solo circolo senza email impedisce la convocazione a tutti gli arbitri di quel torneo. La colonna `clubs.email` è NOT NULL, quindi lo stato si presenta come **stringa vuota** (dato sporco realistico), che `if (! $club->email)` tratta come mancante. È raro ma il fallimento è totale invece che parziale. Il test `ZonalNotificationSendTest::test_missing_club_email_blocks_entire_notification` documenta il comportamento attuale; il fix (avvolgere `sendToClub` in un try/catch come gli altri) è di poche righe.

### 🟠 R3 — Il ramo "reinvio" recente, poco esercitato (probabilità media, impatto alto sul caso d'uso)

Il reinvio unificato (commit `441a6914`, maggio) ha cambiato il comportamento: **tutti** i record, inclusi i FIG-importati, ora passano dal form `prepare_notification`. È una modifica recente a un percorso critico, e i record FIG sono esattamente quelli con i metadati mancanti (R2). La logica è più semplice di prima, ma è giovane e poco coperta sul caso specifico FIG→reinvio. È il tipo di codice che funziona nei test sintetici e inciampa sul primo record reale importato male.

### 🟡 R4 — Concentrazione di logica non testata nei due god-controller (probabilità media, impatto contenuto)

`AssignmentController` (861 righe) e `NotificationController` (736 righe) concentrano i rami decisionali più complessi del progetto (CC ciclomatica ~18 su `sendNationalNotification` nel DeepTest). Non è un blocco alla pubblicazione di per sé — il codice gira — ma è il punto dove una modifica futura ha più probabilità di introdurre una regressione invisibile, proprio perché i suoi rami interni non sono isolati né testati singolarmente. È un rischio di *manutenzione*, non di *lancio*.

---

## 4. Cosa NON è un rischio di pubblicazione (per sgombrare il campo)

Onestà in entrambe le direzioni: alcune cose segnalate negli audit precedenti **non** devono fermare il go-live.

La **sicurezza è in stato accettabile** per un'utenza chiusa e fidata (arbitri e admin federali, non pubblico anonimo). Le route admin sono protette da middleware di gruppo; l'XSS da flash è chiuso; l'endpoint pubblico `/api/internal/tournaments/*` è stato verificato come intenzionale e serve solo dati `completed`/`assigned` senza PII. Il rischio mass-assignment su `user_type` è teorico (Enum castato, nessun endpoint `create($request->all())`).

I **god-controller, gli N+1 residui e i refactoring di service** sono questioni di qualità e manutenibilità, non di funzionamento. Gli N+1 su `ClubController::export()` e simili impattano un export CSV eseguito raramente, non il workflow quotidiano. Trascinarli nel discorso "possiamo pubblicare?" confonde il debito tecnico con il rischio operativo. Vanno affrontati *dopo*, con calma.

---

## 5. Condizioni minime per il go-live (checklist pragmatica)

Ordinate per rendimento. Le prime tre sono la differenza fra "giallo" e "verde-presidiato".

1. **Rendere la suite eseguibile fuori da MAMP + una GitHub Action.** (§2) — poche ore, sblocca tutto il resto. *Consegnato con questa analisi:* `.github/workflows/ci.yml` esegue l'intera suite a ogni push/PR su `main`, fornendo un MySQL effimero e neutralizzando i valori MAMP-specifici di `.env.testing` (socket Unix, porta 8889). Da verificare al primo run su GitHub.
2. **Un test di integrazione sul send zonale reale** (§3 R1): torneo zonale → `NotificationRecipientBuilder` reale → asserire che email-circolo e arbitri-assegnati sono nei destinatari, *senza mockare* il builder. È il singolo test che copre l'80% del rischio reale.
3. **Un "pre-flight" dei destinatari nell'UI di invio** (§3 R2): prima del send, mostrare all'admin l'elenco esatto dei destinatari e segnalare in rosso quelli scartati per email non valida. Trasforma il fallimento silenzioso in una decisione consapevole.
4. **Bonifica una tantum dei dati sporchi noti**: `UPDATE` sulle `zones.email = nome`, e una verifica sui record FIG con `documents=NULL`. Sono pochi e noti.
5. **Soft-launch presidiato**: prima campagna di notifiche reali eseguita con l'admin che verifica manualmente la ricezione su un torneo campione (uno zonale + uno nazionale) prima di affidarsi al sistema in autonomia.

Le voci 4-5 non richiedono codice: sono igiene dei dati e procedura di lancio.

---

## 6. Piano di test funzionali minimo (i 6 test che valgono di più)

Se si scrive un solo blocco di test prima di pubblicare, sia questo. Coprono il rischio operativo, non la copertura percentuale.

| # | Test | Rischio coperto | Perché conta |
|---|---|---|---|
| T1 | Send zonale end-to-end con destinatari reali dal DB (no mock del builder), asserzione su circolo + arbitri | R1 | Il buco principale; intercetta i bug formato/parsing che `Mail::fake` non vede |
| T2 | Send con una zona avente email corrotta → asserire che il destinatario è saltato **e** che lo stato è `partial`, non `sent` | R2 | Distingue "riuscito" da "riuscito a metà" |
| T3 | Reinvio di un record FIG-importato (documents=NULL, metadata assenti) via form | R3 | Il percorso giovane e fragile |
| T4 | Render reale della view email (non `Mail::fake`) con body contenente HTML → verifica neutralizzazione | R1 | Copre il rendering, cieco oggi |
| T5 | `mark_notified` su batch misto zonale+nazionale → tipi notifica corretti | dominio | Già parzialmente coperto; consolidare |
| T6 | Conflitto date con torneo a `end_date=null` → nessun falso conflitto | regressione | Protegge il fix del bug Carbon già applicato |

T5 e T6 in parte esistono già; il valore nuovo è in **T1-T4**.

### Test consegnati con questa analisi (2026-05-30)

Tre file nuovi che coprono i rischi a più alto impatto, scritti per essere deterministici (nessuna dipendenza da file `.docx` su disco) e per girare sia su MySQL sia, una volta sbloccato §5.1, su SQLite in-memory:

| File | Copre | Rischio |
|---|---|---|
| `tests/Feature/Notifications/ZonalNotificationSendTest.php` | Send zonale reale: circolo + arbitri ricevono (T1); solo arbitri ancora assegnati (regola di business); email circolo mancante blocca tutto (R2-bis) | R1, R2-bis |
| `tests/Feature/Notifications/NationalNotificationCorruptZoneEmailTest.php` | Flusso nazionale via route con zona dall'email corrotta: nessun crash RFC 2822, destinatario sporco saltato, validi inclusi (T2/T3) | R2 |
| `tests/Unit/Services/AssignmentDateConflictNullEndDateTest.php` | Regressione del fix `Carbon::parse(null)` su `datesOverlap` (unit test in-memory via reflection): overlap stesso-giorno con `end_date` null, nessun falso conflitto su tornei distanti (T6) | regressione |

> **Precisazione emersa eseguendo i test.** La colonna `tournaments.end_date` è `date()` **NOT NULL**: un `end_date` null non può arrivare dal DB, quindi il finding del DeepTest ("falsi conflitti a cascata da `Carbon::parse(null)`") è in realtà **non raggiungibile dal flusso normale** — `detectDateConflicts()` legge dal DB, dove end_date è sempre valorizzato. Il fix resta corretto come difesa per modelli in-memory, e il test lo guarda al livello giusto (il metodo `datesOverlap`), ma la severità reale del finding originale è inferiore a quanto implicato. Stessa logica per `clubs.email` (NOT NULL): il caso "senza email" è una stringa vuota, non NULL.

Questi test usano `Mail::assertSent(...)->hasTo()/hasCc()` per asserire *chi* riceve — il livello che mancava. Restano da aggiungere, quando si vuole, T4 sul rendering reale della view (oggi coperto solo dal collaudo Mailpit manuale) e l'esecuzione su transport reale per il parsing degli indirizzi: entrambi richiedono di disattivare il `Mail::fake()` globale di `TestCase::setUp()` in una sottoclasse dedicata.

---

## 7. Conclusione

Il progetto è **più sano di quanto la sua stratificazione documentale faccia sembrare**. Funziona, il dominio difficile (zona/CRC) è proprio quello meglio protetto, e i bug critici peggiori sono già stati chiusi nel codice — non solo annotati.

Pubblicarlo **non è un azzardo**, a patto di non confondere "i test passano sulla mia macchina" con "il sistema è protetto". Oggi non lo è, perché i test non hanno un cancello automatico e perché il processo che conta di più — l'invio della notifica — è coperto proprio dove i test non possono vedere. Le tre azioni del §5 (CI, un test di integrazione sul send reale, un pre-flight dei destinatari) chiudono questo divario con un costo di ore, non di settimane, e portano il progetto da "pubblicabile con le dita incrociate" a "pubblicabile con un soft-launch tranquillo".

Il debito architetturale (god-controller, N+1, refactoring) è reale ma **non è il discorso del go-live**: è il discorso del mese dopo.

---

*Analisi fondata su lettura diretta del codice al 2026-05-30. Non sostituisce l'esecuzione della suite né un collaudo su dati reali — anzi, ne raccomanda esplicitamente l'introduzione come precondizione di pubblicazione.*
