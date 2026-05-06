# Export Excel del modulo Quadranti

Documenta la soluzione adottata per l'export `.xlsx` della tabella delle partenze e il motivo del passaggio da `jquery-table2excel` a `SheetJS`.

---

## 1. Problema

All'apertura del file scaricato dal pulsante "Excel" del modulo Quadranti, Microsoft Excel mostrava la finestra di dialogo:

> Il formato e l'estensione di `Simulatore_Partenze.xls` non corrispondono. Il file potrebbe essere danneggiato o non sicuro. Se non si considera attendibile l'origine, non aprirlo. Aprire comunque il file?

Cliccando "Sì" il file si apriva comunque, ma il warning compariva ad ogni apertura e creava confusione agli utenti finali (referee, CRC, segreterie di circolo).

---

## 2. Causa tecnica

L'export precedente usava la libreria [`jquery-table2excel`](https://github.com/rainabba/jquery-table2excel) (versione 1.1.0).

Questa libreria **non genera un vero file Excel**: produce un documento HTML con un'intestazione MIME `application/vnd.ms-excel` e lo salva con estensione `.xls`. È una pratica diffusa fino al 2010 circa perché Excel sa interpretare HTML, ma:

- Excel 2007 e successivi confrontano la firma binaria del file con l'estensione del nome.
- L'header binario di un HTML (`<!DOCTYPE html>` o `<html>`) non corrisponde alla firma di un file `.xls` (un OLE Compound File, magic bytes `D0 CF 11 E0`).
- Da qui l'avviso *"Il formato e l'estensione non corrispondono"*, sollevato a fini di sicurezza (apertura di HTML scaricati può eseguire script).

Inoltre la URL della CDN che serviva la libreria — `cdn.rawgit.com/rainabba/jquery-table2excel/...` — è dismessa dal 2018: il file non veniva neppure caricato, e `$.fn.table2excel` risultava `undefined` con conseguente alert *"funzione di esportazione non disponibile"*.

---

## 3. Opzioni valutate

| Opzione | Pro | Contro |
|---|---|---|
| Mantenere `table2excel` su CDN attiva (jsDelivr) | impatto minimo, 1 riga di fix | l'avviso Excel resta perché il file è ancora HTML mascherato |
| Rinominare l'estensione in `.html` | nessun avviso | l'utente non riconosce il file come spreadsheet, doppio click apre il browser |
| Generare CSV | zero dipendenze, niente avvisi | perde colori, layout doppio tee non rappresentabile bene |
| **Generare vero `.xlsx` con SheetJS** ✓ | apertura pulita in Excel/LibreOffice/Numbers, nessun avviso | dipendenza JS aggiuntiva (~900 KB minified) |

Scelta: **SheetJS**. Produce file XLSX conformi (zip con XML interni secondo OpenXML), nessun warning, supporto cross-suite.

---

## 4. Soluzione adottata

### 4.1 Libreria

[SheetJS Community Edition](https://github.com/SheetJS/sheetjs) versione `0.18.5` (ultima release community sotto Apache-2.0), caricata da jsDelivr:

```html
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
```

### 4.2 Modifica HTML — `resources/views/user/quadranti/index.blade.php`

Due cambi nella view:

**a) sostituzione dello script di export**

```diff
- <script src="//cdn.rawgit.com/rainabba/jquery-table2excel/1.1.0/dist/jquery.table2excel.min.js"></script>
+ <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
```

**b) `#first_table` da `<table>` a `<div>`**

```diff
- <table class="min-w-full divide-y divide-gray-200" id="first_table">
- </table>
+ <div id="first_table" class="min-w-full">
+ </div>
```

Motivazione: in modalità doppio tee, `generateDoubleTee()` ritorna `<div info> + <table>...</table>`. Mettere questo dentro un `<table>` esistente attivava il **foster parenting** dell'HTML parser: il browser estraeva la `<table>` interna e la metteva come *sibling* di `#first_table`. La pagina era visivamente corretta, ma `document.querySelector('#first_table table')` restituiva `null` perché la tabella vera non era figlia ma adiacente. Cambiare il wrapper esterno in `<div>` ripristina la gerarchia attesa e permette a SheetJS di trovare la tabella in modo deterministico.

### 4.3 Modifica JS — `resources/js/quadranti/quadranti.js`

**a) `generateTable()` wrappa coerentemente il tee unico**

```js
generateTable() {
  const doppiePartenze = this.config.doppiePartenze;
  const giornata = this.config.giornata;
  let html;

  if (doppiePartenze === TEE_TYPES.DOUBLE) {
    html = this.logic.generateDoubleTee(giornata);
  } else {
    html = `<table class="min-w-full divide-y divide-gray-200">${this.logic.generateSingleTee(giornata)}</table>`;
  }

  $('#first_table').html(html);
}
```

`generateDoubleTee` produce già `<div info><table>…</table>`; `generateSingleTee` produce solo `<thead>…<tbody>…` quindi va wrappato esplicitamente.

**b) `handleExcelExport()` con SheetJS**

```js
handleExcelExport() {
  if (typeof XLSX === 'undefined') {
    alert('La libreria di esportazione Excel non è caricata.');
    return;
  }

  // Cerca la <table> dentro #first_table
  const target = document.querySelector('#first_table table');
  if (!target) {
    alert('Nessuna tabella da esportare.');
    return;
  }

  // Cloniamo per rimuovere i pulsanti × dal foglio Excel senza toccare la UI
  const clone = target.cloneNode(true);
  clone.querySelectorAll('.qd-remove').forEach(el => el.remove());

  const wb = XLSX.utils.table_to_book(clone, { sheet: 'Partenze' });

  // Nome file parlante (giornata + data)
  const giornata = this.config.giornata === 'seconda' ? 'Seconda' : 'Prima';
  const date = ($('#start').val() || '').replace(/\//g, '-');
  const filename = `Partenze_${giornata}Giornata${date ? '_' + date : ''}.xlsx`;

  XLSX.writeFile(wb, filename);
}
```

Punti chiave:

- **Clone difensivo**: `target.cloneNode(true)` evita di modificare la tabella visibile nella pagina. La rimozione dei pulsanti `.qd-remove` avviene sul clone.
- **Esclusione pulsanti ×**: i pulsanti di rimozione iscritto, presenti in modalità Nominativo, vengono eliminati dal foglio Excel. Hanno anche la classe `excludeThisClass` come safety-net per future variazioni.
- **`table_to_book`**: SheetJS legge l'HTML della `<table>`, ne estrae celle/righe e costruisce il workbook.
- **`writeFile`**: scrive il `.xlsx` triggerando il download del browser.
- **Nome file**: `Partenze_PrimaGiornata_05-05-2026.xlsx` o simile, ricavato da `config.giornata` e dal valore del datepicker `#start`.

---

## 5. Comportamento atteso

1. Click su **Excel** nel pannello configurazione del modulo Quadranti.
2. Download di un file `Partenze_<Prima|Seconda>Giornata_<dd-mm-yyyy>.xlsx`.
3. Doppio click sul file → apertura diretta in Excel / LibreOffice / Numbers / Google Sheets.
4. **Nessun avviso** sul formato. **Nessun click "Sì" di conferma**.

I dati nel foglio rispecchiano la tabella visibile a schermo: numero match, tee, nominativi/numeri, orario, e — in doppio tee — la riga vuota fra Early e Late. I pulsanti `×` non compaiono nel foglio.

> Nota: SheetJS Community estrae solo testo e struttura. I colori di sfondo dei quadranti (Q1 arancio, Q2 verde, Q3 grigio, Q4 giallo) **non** vengono trasportati nel `.xlsx`. Per una stampa con i colori dei quadranti usare il pulsante **Stampa / PDF** (vedi `README.md` §12).

---

## 6. Test e regressione

La modifica non tocca la logica di calcolo dei quadranti. La suite Vitest esistente (`resources/js/quadranti/quadranti-logic.test.js`, 34 test) continua a passare invariata:

```bash
npx vitest run resources/js/quadranti/quadranti-logic.test.js
# ✓ 34 tests | 0 failed
```

Verifiche manuali consigliate:

- doppio tee, giornata 1, modalità Nominativo, 102 uomini + 42 donne → export, riapertura: tabella completa, no pulsanti ×, no avvisi.
- doppio tee, giornata 2, modalità Numerico → export: numeri 1..N nelle posizioni rotate.
- tee unico, gara 36 buche → export: singola colonna di flight.
- caricamento iscritti da Federgolf, rimozione di 1 nominativo via pulsante ×, export → tabella ridisegnata con N−1, file `.xlsx` coerente.

---

## 7. File modificati

```
resources/views/user/quadranti/index.blade.php   (script CDN + #first_table div)
resources/js/quadranti/quadranti.js              (generateTable + handleExcelExport)
resources/js/quadranti/README.md                 (§12 export aggiornato)
```

Nessuna migrazione database, nessun nuovo endpoint, nessuna dipendenza npm aggiunta a `package.json` (SheetJS è caricato via `<script>` dalla CDN, allineato all'attuale modalità di caricamento di jQuery e jQuery UI nel layout).

---

## 8. Riferimenti

- SheetJS docs — <https://docs.sheetjs.com/docs/api/utilities/html#html-table-input>
- HTML5 foster parenting (motivo del refactor `#first_table`) — <https://html.spec.whatwg.org/multipage/parsing.html#foster-parenting>
- Excel file format detection — Microsoft KB *"Different file format than file extension indicates"*
