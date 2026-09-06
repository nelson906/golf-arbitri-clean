# Deploy su Aruba — 2026-09-06

Sessione di qualità: PHPStan livello 9 senza soppressioni (era 6 con otto
categorie soppresse), 479 test verdi, Infection MSI 100%.
**193 file toccati**, quindi conviene procedere nell'ordine e verificare in mezzo.

---

## 1. Build locale di `vendor/`

```
cd ~/Sites/golf-arbitri-clean
composer install --no-dev --optimize-autoloader
```

`--no-dev`, non `--dev`: altrimenti in produzione finiscono phpunit, phpstan,
larastan, infection e faker — decine di MB inutili e codice di test raggiungibile
dal web.

Composer aggiunge una sola dipendenza rispetto all'ultimo deploy
(`phpstan/phpstan-phpunit`), ed è **dev**: con `--no-dev` non viene inclusa, che è
esattamente ciò che si vuole.

Dopo aver zippato, per tornare a lavorare in locale:

```
composer install
```

---

## 2. Cosa NON caricare

| percorso | perché |
|---|---|
| `bootstrap/cache/*.php` | generati con i pacchetti dev presenti: in remoto Laravel proverebbe a registrare service provider che in `vendor/` non ci sono più → 500 all'avvio |
| `phpstan.neon`, `phpstan-strict.neon`, `eslint.config.js`, `tsconfig.json` | solo sviluppo |
| `tests/`, `resources/js/types/` | solo sviluppo |
| `qa-*.txt` | report di lavoro, da tenere fuori |
| `.env` | quello di Aruba è diverso e va lasciato dov'è |

---

## 3. File NUOVI da caricare

Non esistono ancora sul server, quindi un sync FTP "solo modificati" potrebbe
saltarli:

- `app/Support/Untrusted.php`
- `app/Services/FedergolfCompetitionsClient.php`
- `app/Http/Concerns/InteractsWithAuthUser.php`
- `database/migrations/2026_09_06_000001_make_tournaments_club_id_nullable.php`

## 4. File da CANCELLARE a mano sul server

L'FTP non cancella: questi restano finché non li togli tu.

- `app/Console/Commands/CreateMissingFigData.php` — patch usa-e-getta con payload
  cablato, già applicata, rimossa anche da `bootstrap/app.php`
- `tests/Unit/ExampleTest.php` — solo se avevi caricato `tests/`

## 5. Cartelle da caricare

`app/`, `routes/`, `database/migrations/`, `database/seeders/`, `database/factories/`,
`resources/views/`, `resources/js/`, `public/build/`, `bootstrap/app.php`,
`composer.json`, `composer.lock`, `vendor/` (zippata).

**Nota sulle migration già applicate:** otto file in `database/migrations/` risultano
modificati, ma sono cambiamenti puramente cosmetici di una sessione precedente
(`onDelete('cascade')` → `cascadeOnDelete()`, che Laravel traduce nella stessa SQL).
Una migration già eseguita non cambia il database se la modifichi: caricarli è
innocuo, non caricarli anche.

---

## 6. Database — phpMyAdmin

Esegui `aruba-2026-09-06.sql`, **un blocco alla volta**, leggendo l'output di
ciascuno prima di procedere. Il blocco 0 fotografa lo stato di partenza: conservalo.

Due cose che fa:

1. `tournaments.club_id` diventa nullable — serve ai tornei T.B.A. (data e zona
   note, circolo ancora da assegnare), che prima non erano rappresentabili e che il
   seeder del calendario scartava.
2. Registra nella tabella `migrations` le sette del 2025-08-29, le cui tabelle
   esistono già ma non risultano applicate perché lo schema è arrivato da un dump.

Gli INSERT sono condizionati: rilanciare il blocco non duplica nulla.

---

## 7. Dopo l'upload

Dal pannello `/aruba-admin`:

1. **Cache → svuota** (config, route, view). Necessario: `bootstrap/app.php` è
   cambiato (lista comandi) e le route pure — `FedergolfController::searchCompetitions`
   è stata rimossa perché non aveva alcuna route.
2. Il pulsante **Composer** non serve e non funzionerebbe comunque: su Aruba il
   binario `composer` non c'è e `exec()` è di norma disabilitato. L'autoload lo hai
   già generato in locale con `--optimize-autoloader`.

## 8. Verifica in produzione

- login e dashboard admin
- elenco tornei con i filtri (ricerca, zona, mese) — molti sono passati da
  `$request->prop` agli accessor tipizzati
- creazione di un torneo **senza circolo**: deve salvarsi e restare visibile
  all'admin della sua zona
- una notifica in anteprima, con il nome del circolo nel corpo
- `/user/federgolf` → caricamento gare: passa dal nuovo
  `FedergolfCompetitionsClient`, unico punto di contatto con `competitions-search`

---

## Punto aperto per dopo il deploy

`Tournaments2026Seeder` importa i T.B.A. solo se il CSV indica la zona, e per
risolverla prova prima `zones.code` poi `zones.name`. Il formato reale del campo
`zona_circolo` non l'ho potuto verificare — `calendari_2026_consolidato.csv` non è nel
repository. Alla prossima importazione, se i T.B.A. finiscono negli "⊘ Saltati" con
il warning «T.B.A. senza zona utilizzabile», il mapping va corretto lì.
