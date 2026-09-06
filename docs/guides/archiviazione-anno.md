# Archiviazione di fine anno (career history)

L'applicazione tiene in `tournaments`, `assignments` e `availabilities` **solo gli anni
non ancora archiviati**. A fine stagione i dati vengono condensati per arbitro in
`referee_career_history` (colonne JSON `tournaments_by_year`, `assignments_by_year`,
`availabilities_by_year`, `career_stats`) e le righe sorgente vengono eliminate.

È per questo che nei filtri delle liste **non c'è un selettore per anno**: sarebbe una
scelta tra opzioni che il modello dati non ha. Ci sono invece il periodo (tutti / da
giocare / già giocati) e il tipo di torneo.

## Dove si fa

**`/admin/career-history/archive`** — riservata al **super_admin**.

Funziona su qualsiasi ambiente, Aruba compresa: è una pagina web, non richiede SSH né
riga di comando. È l'unica strada per il remoto.

Il form ha tre campi:

| campo | chi lo vede | effetto |
|---|---|---|
| **Anno da archiviare** | tutti | anno corrente e precedenti fino al 2020 |
| **Arbitro** | vuoto = «Tutti gli arbitri» **solo per super_admin** | un admin di zona è obbligato a sceglierne uno della propria zona |
| **Svuota tabelle dopo archiviazione** | **solo super_admin** | cancella le righe sorgente |

Le stesse regole sono applicate lato server in `CareerHistoryController::processArchive()`,
non solo nascondendo i campi: chi non è super_admin riceve «Solo il super admin può
svuotare le tabelle» o «Devi selezionare un arbitro specifico», e l'accesso a un arbitro
di un'altra zona dà 403.

---

## Procedura per archiviare il 2025

### 1. Backup del database

`Svuota tabelle` è **irreversibile** e cancella righe da tre tabelle, più tutto quello che
ci pende in cascata (vedi sotto). Su Aruba il backup si fa da phpMyAdmin → Esporta.

### 2. Anteprima

Nel form, scegliendo l'anno la pagina mostra i conteggi di quell'anno: tornei,
assegnazioni, disponibilità. Se spunti «Svuota tabelle» compare l'elenco esplicito di
quanto verrebbe eliminato. È il momento di leggerlo, non di scorrerlo.

### 3. Archiviazione senza svuotare

Lascia **Arbitro** vuoto (= tutti) e la checkbox **non** spuntata → invia.

Popola `referee_career_history` lasciando intatti i dati sorgente. Dopo questo passo i due
mondi coesistono: è il momento giusto per aprire qualche curriculum arbitro e verificare
che il 2025 ci sia e sia corretto.

### 4. Solo quando il punto 3 è verificato: svuotamento

Stesso form, stesso anno, questa volta con la checkbox spuntata.

---

## Cosa cancella esattamente lo svuotamento

`CareerHistoryService::clearSourceData()` elimina, per l'anno indicato:

- le **assegnazioni** dei tornei di quell'anno
- le **disponibilità** dei tornei di quell'anno
- i **tornei** stessi (`WHERE YEAR(start_date) = anno`)

E per effetto delle foreign key `cascadeOnDelete` sui tornei spariscono anche:

- `tournament_notifications` (e con esse `notification_clause_selections`)
- i record `documents` collegati al torneo

Due cose da sapere:

- vengono cancellati **tutti** i tornei dell'anno, anche quelli senza alcuna
  assegnazione: la tabella tiene solo gli anni vivi, non un archivio
- i file DOCX su disco (`storage/app/docs/...`) **non** vengono toccati: sparisce il
  record, non il file. Se vuoi anche quelli, vanno rimossi a mano

## Rete di sicurezza già presente

`CareerHistoryService::archiveYear()` esegue la cancellazione solo se l'archiviazione è
andata a buon fine per tutti:

```php
if ($clearSourceData && empty($stats['errors'])) {
    $this->clearSourceData($year);
}
```

Un solo errore su un arbitro e le sorgenti restano dov'erano. Il messaggio di esito
riporta il numero di errori.

## Archiviare un solo arbitro

Stesso form, scegliendo l'arbitro dal menu. È la modalità disponibile anche agli admin di
zona, limitata ai propri arbitri, e **non** consente lo svuotamento: quello avviene solo
nel giro completo.

---

## Nota storica

Fino al 2026-09-06 esisteva anche un comando `php artisan career:archive-year`, che
chiamava gli stessi metodi del service. È stato **rimosso**: era un duplicato non
schedulato, senza test e mai invocato, e non era comunque utilizzabile in produzione
(su Aruba non c'è SSH). Aveva inoltre un'anteprima fuorviante — `--dry-run --user=`
contava le assegnazioni per `assigned_at`, mentre l'archiviazione vera usa la data del
torneo. L'archiviazione è ora una sola, quella del super_admin da interfaccia.
