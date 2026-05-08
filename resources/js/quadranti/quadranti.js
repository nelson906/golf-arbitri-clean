/**
 * Quadranti Application
 * Main entry point for the Quadranti (Starting Times Simulator) application
 */

import {
  DEFAULT_CONFIG,
  TEE_TYPES,
  ROUND_TYPES,
  COMPETITION_TYPES
} from './config.js';

import {
  storage,
  formatDate,
  addTime,
  halfTime,
  debounce
} from './utils.js';

import { QuadrantiLogic, mergeFedergolfResponses, normalizeGaraTitle } from './quadranti-logic.js';

/**
 * Main Quadranti Application Class
 */
class QuadrantiApp {
  constructor() {
    this.config = this.loadConfiguration();
    this.logic = new QuadrantiLogic(this.config);
    this.isInitialized = false;
  }

  /**
   * Loads configuration from localStorage with defaults
   * @returns {Object} Configuration object
   */
  loadConfiguration() {
    const config = {};

    // Load each config value from storage with default fallback
    Object.keys(DEFAULT_CONFIG).forEach(key => {
      config[key] = storage.get(key, DEFAULT_CONFIG[key]);
    });

    return config;
  }

  /**
   * Saves configuration to localStorage
   */
  saveConfiguration() {
    Object.keys(this.config).forEach(key => {
      storage.set(key, this.config[key]);
    });
  }

  /**
   * Initializes the application
   */
  async init() {
    if (this.isInitialized) return;

    try {
      this.initializeUI();
      this.attachEventHandlers();
      await this.updateEphemerisData();
      this.generateTable();
      this.isInitialized = true;
    } catch (error) {
      console.error('Error initializing Quadranti app:', error);
    }
  }

  /**
   * Initializes UI elements with stored values
   */
  initializeUI() {
    // Initialize datepicker
    this.logic.initializeDatepicker($);

    // Set form values from configuration
    $('#geo_area').val(this.config.geoArea);
    $('#start').val(storage.get('start', formatDate(new Date())));
    $('#gara_NT').val(this.config.garaNT);
    $('#players').val(this.config.players);
    $('#proette').val(this.config.proette);
    $('#players_x_flight').val(this.config.playersPerFlight);
    $('#giornata').val(this.config.giornata);
    $('#round').val(this.config.round);
    $('#start_time').val(this.config.startTime);
    $('#gap').val(this.config.gap);
    $('#compatto').val(this.config.compatto);
    $('#doppie_partenze').val(this.config.doppiePartenze);

    // Show/hide compact option based on player count
    this.toggleCompactOption();

    // Update cross time display
    this.updateCrossTime();

    // Show/hide nominativo buttons
    this.updateNominativoButtons();

    // Update title based on round
    this.updateTableTitle();
  }

  /**
   * Attaches event handlers to UI elements
   */
  attachEventHandlers() {
    // Geographic area change
    $('#geo_area').on('change', () => this.handleGeoAreaChange());

    // Date change
    $('.datepicker').on('change', () => this.handleDateChange());

    // Form input changes (debounced to prevent rapid reloads)
    const handleFormChange = debounce(() => this.handleFormChange(), 300);
    $('input[type="text"], select').on('change', handleFormChange);

    // Specific handlers for numeric inputs
    $('#players, #proette').on('input change', handleFormChange);

    // Button clicks
    $('#refresh').on('click', () => this.handleReset());
    $('#excel').on('click', () => this.handleExcelExport());
    $('#pdf').on('click', () => this.handlePdfPrint());
    $('#btnClick').on('click', (e) => this.handleNominativoToggle(e, 'On'));
    $('#btnClock').on('click', (e) => this.handleNominativoToggle(e, 'Off'));

    // File upload form submit
    $('#upload-form').on('submit', (e) => this.handleFileUpload(e));

    // Pulsante carica gare Federgolf
$('#load-federgolf-btn').on('click', () => this.handleLoadFedergolfGare());

// Selezione gara
$('#federgolf-gare-select').on('change', () => this.handleFedergolfGaraSelected());

// Rimozione di un singolo nominativo dalla tabella (modalità Nominativo).
// Delegato sul container perché la tabella viene rigenerata da generateTable()
// e i pulsanti × vengono ricreati a ogni render.
$('#first_table').on('click', '.qd-remove', (e) => this.handleRemovePlayer(e));

  }

  /**
   * Handles geographic area change
   */
  async handleGeoAreaChange() {
    this.config.geoArea = $('#geo_area').val();
    storage.set('geo_area', this.config.geoArea);
    storage.set('start', $('#start').val());
    await this.updateEphemerisData();
  }

  /**
   * Handles date change
   */
  async handleDateChange() {
    storage.set('start', $('#start').val());
    await this.updateEphemerisData();
  }

  /**
   * Handles form input changes
   */
  handleFormChange() {
    // Update configuration from form values - handle empty fields
    const playersVal = $('#players').val();
    const proetteVal = $('#proette').val();
    
    this.config.players = playersVal === '' ? 0 : playersVal;
    this.config.proette = proetteVal === '' ? 0 : proetteVal;
    this.config.playersPerFlight = $('#players_x_flight').val();
    this.config.giornata = $('#giornata').val();
    this.config.garaNT = $('#gara_NT').val();
    this.config.round = $('#round').val();
    this.config.startTime = $('#start_time').val();
    this.config.gap = $('#gap').val();
    this.config.compatto = $('#compatto').val();
    this.config.doppiePartenze = $('#doppie_partenze').val();

  // Save configuration
  this.saveConfiguration();

  // Update UI elements (IMPORTANTE: toggleCompactOption usa doppiePartenze!)
  this.toggleCompactOption();
  this.updateCrossTime();
  this.updateTableTitle();

  // Update logic configuration
  this.logic.updateConfig(this.config);

  // Regenerate table
  this.generateTable();
}

  /**
   * Handles reset button click
   */
  handleReset() {
    if (confirm('Sei sicuro di voler cancellare tutti i dati?')) {
      storage.clear();
      window.location.reload();
    }
  }

  /**
   * Esporta la tabella delle partenze in un vero file .xlsx tramite SheetJS.
   * Sostituisce la vecchia implementazione con jquery-table2excel, che produceva
   * un .xls "fasullo" (HTML rinominato) facendo apparire l'avviso di Excel
   * "il formato e l'estensione non corrispondono".
   */
  handleExcelExport() {
    if (typeof XLSX === 'undefined') {
      alert('La libreria di esportazione Excel non è caricata.');
      return;
    }

    // Cerca la <table> dentro #first_table (in tee unico è figlia diretta;
    // in doppio tee è figlia di #first_table dopo il box riepilogo).
    const target = document.querySelector('#first_table table');
    if (!target) {
      alert('Nessuna tabella da esportare.');
      return;
    }

    // Cloniamo per rimuovere i pulsanti × dal foglio Excel senza toccare la UI.
    const clone = target.cloneNode(true);
    clone.querySelectorAll('.qd-remove').forEach(el => el.remove());

    const wb = XLSX.utils.table_to_book(clone, { sheet: 'Partenze' });

    // Nome file parlante (giornata + data)
    const giornata = this.config.giornata === 'seconda' ? 'Seconda' : 'Prima';
    const date = ($('#start').val() || '').replace(/\//g, '-');
    const filename = `Partenze_${giornata}Giornata${date ? '_' + date : ''}.xlsx`;

    XLSX.writeFile(wb, filename);
  }

  /**
   * Apre il dialogo di stampa del browser sulla sola area #print-area.
   * L'utente può scegliere "Salva come PDF" come destinazione per ottenere
   * un PDF della tabella senza dipendenze esterne. Il document.title viene
   * temporaneamente sostituito con un nome significativo perché molti
   * browser lo usano come nome file di default in "Salva come PDF".
   */
  handlePdfPrint() {
    const originalTitle = document.title;
    const giornata = this.config.giornata === 'seconda' ? 'Seconda' : 'Prima';
    const date = ($('#start').val() || '').replace(/\//g, '-');
    const newTitle = `Partenze_${giornata}Giornata${date ? '_' + date : ''}`;
    document.title = newTitle;

    const restore = () => {
      document.title = originalTitle;
      window.removeEventListener('afterprint', restore);
    };
    window.addEventListener('afterprint', restore);
    // Fallback nel caso afterprint non venga emesso (alcune versioni di Safari)
    setTimeout(restore, 5000);

    window.print();
  }

  /**
   * Handles nominativo/numeric toggle
   */
  handleNominativoToggle(e, mode) {
    e.preventDefault();

    this.config.nominativo = mode;
    storage.set('nominativo', mode);

    if (mode === 'On') {
      storage.set('display1', 'false');
      storage.set('display2', 'true');
    } else {
      storage.set('display1', 'true');
      storage.set('display2', 'false');
    }

    this.updateNominativoButtons();
    this.logic.updateConfig(this.config);
    this.generateTable();
  }

  /**
   * Rimuove un singolo iscritto dall'array atleti/atlete e ridisegna lo schema.
   * In modalità Nominativo (è l'unica in cui viene mostrato il pulsante ×).
   * Lo schema viene ricalcolato da zero con N-1 giocatori: i flight, gli orari
   * e i quadranti si riassestano in automatico tramite generateTable().
   */
  handleRemovePlayer(e) {
    e.preventDefault();
    if (this.config.nominativo !== 'On') return;

    const $btn = $(e.currentTarget);
    const cat  = $btn.data('cat');                  // 'M' | 'F'
    const idx  = parseInt($btn.data('idx'), 10);
    const key  = cat === 'F' ? 'atlete' : 'atleti';
    const list = (storage.get(key, []) || []).slice();

    if (!Number.isInteger(idx) || idx < 0 || idx >= list.length) {
      console.warn('handleRemovePlayer: indice non valido', { cat, idx, listLength: list.length });
      return;
    }

    const removedName = list[idx];
    if (!confirm(`Rimuovere "${removedName}" dall'elenco?\nLo schema verrà ridisegnato con ${list.length - 1} ${cat === 'F' ? 'atlete' : 'atleti'}.`)) {
      return;
    }

    list.splice(idx, 1);
    storage.set(key, list);

    if (cat === 'F') {
      storage.set('storedProetteCount', list.length);
      storage.set('proette', list.length);
      this.config.proette = list.length;
      $('#proette').val(list.length);
    } else {
      storage.set('storedPlayersCount', list.length);
      storage.set('players', list.length);
      this.config.players = list.length;
      $('#players').val(list.length);
    }

    this.logic.updateConfig(this.config);
    this.toggleCompactOption();
    this.generateTable();
  }

  /**
   * Handles file upload for player names
   */
  async handleFileUpload(e) {
    e.preventDefault();

    const file = $('#file')[0].files[0];
    if (!file) {
      alert('Seleziona un file Excel');
      return;
    }

    const formData = new FormData();
    formData.append('file', file);

    try {
      const response = await $.ajax({
        url: ($('meta[name="base-url"]').attr('content') || '') + '/user/quadranti/upload-excel',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
      });

      // Response is already JSON from Laravel
      const data = response;

      if (data && data[0] && data[1]) {
        const atlete = data[0]; // Già un array, non serve Object.values
        const atleti = data[1]; // Già un array, non serve Object.values

        storage.set('atlete', atlete);
        storage.set('atleti', atleti);
        storage.set('storedPlayersCount', atleti.length);
        storage.set('storedProetteCount', atlete.length);

        // Aggiorna anche i contatori nell'interfaccia
        $('#players').val(atleti.length);
        $('#proette').val(atlete.length);
        this.config.players = atleti.length;
        this.config.proette = atlete.length;
        storage.set('players', atleti.length);
        storage.set('proette', atlete.length);

        this.config.nominativo = 'On';
        storage.set('nominativo', 'On');

        this.updateNominativoButtons();
        this.logic.updateConfig(this.config);
        this.generateTable();

        alert(`File caricato con successo!\n${atleti.length} atleti\n${atlete.length} atlete`);
      }
    } catch (error) {
      console.error('Error uploading file:', error);
      if (error.responseJSON && error.responseJSON.message) {
        alert('Errore: ' + error.responseJSON.message);
      } else {
        alert('Errore durante il caricamento del file');
      }
    }
  }

  /**
   * Updates ephemeris data (sunrise/sunset)
   */
  async updateEphemerisData() {
    const geoArea = this.config.geoArea;
    const date = $('#start').val() || formatDate(new Date());

    const data = await this.logic.fetchEphemerisData(geoArea, date);

    $('#sunrise').html(data.sunrise);
    $('#sunset').html(data.sunset);
  }

  /**
   * Toggles compact option visibility based on player count
   */
  toggleCompactOption() {
    const players = parseInt(this.config.players) || 0;
    const proette = parseInt(this.config.proette) || 0;
    const totalPlayers = players + proette;
    const mod = parseInt(this.config.playersPerFlight);
    
    if (totalPlayers <= mod * 32) {
      $('.compatto').show();
    } else {
      $('.compatto').hide();
    }
  }

  /**
   * Updates cross time display
   */
  updateCrossTime() {
    const crossTime = addTime(this.config.startTime, halfTime(this.config.round));
    $('#cross').html(crossTime);
  }

  /**
   * Updates nominativo/numeric button visibility
   */
  updateNominativoButtons() {
    const displayNominativo = storage.get('display1', 'true');
    const displayNumerico = storage.get('display2', 'false');

    if (displayNominativo === 'true') {
      $('#2').hide();
      $('#1').show();
    } else {
      $('#1').hide();
      $('#2').show();
    }
  }

  /**
   * Updates table title based on configuration
   */
  updateTableTitle() {
    const giornata = this.config.giornata;
    const gara = this.config.garaNT;

    let title = '';

    if (giornata === ROUND_TYPES.FIRST) {
      title = 'Prima Giornata';
    } else if (giornata === ROUND_TYPES.SECOND) {
      title = 'Seconda Giornata';
      if (gara === COMPETITION_TYPES.GARA_36) {
        title += ' per classifica';
      }
    }

    $('#titolo_giornata').html(title);
  }

  /**
   * Generates and displays the tee time table.
   *
   * Note: #first_table è ora un <div>. generateDoubleTee ritorna già
   * (infoBox + <table>...</table>); generateSingleTee ritorna solo
   * <thead>...<tbody>... e va wrappato esplicitamente in <table>.
   */
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

/**
 * Carica TUTTE le gare da Federgolf
 */
async handleLoadFedergolfGare() {
  try {
    // Mostra loading
    $('#load-federgolf-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Caricamento...');

    const response = await $.ajax({
      url: ($('meta[name="base-url"]').attr('content') || '') + '/user/federgolf/load-all',
      type: 'POST',
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    });

    $('#load-federgolf-btn').prop('disabled', false).html('<i class="fas fa-globe mr-2"></i> Carica da Federgolf');

    if (response.success && response.gare.length > 0) {
      this.populateFedergolfDropdown(response.gare);
      alert(`Trovate ${response.gare.length} gare`);
    } else {
      alert('Nessuna gara disponibile');
    }
  } catch (error) {
    $('#load-federgolf-btn').prop('disabled', false).html('<i class="fas fa-globe mr-2"></i> Carica da Federgolf');
    console.error('Errore:', error);
    alert('Errore nel caricamento delle gare');
  }
}

/**
 * Popola dropdown con gare trovate
 */
populateFedergolfDropdown(gare) {
  const $dropdown = $('#federgolf-gare-select');
  $dropdown.empty().append('<option value="">-- Seleziona una gara --</option>');

  // Raggruppa gare per nome base (senza MASCHILE/FEMMINILE).
  // REGRESSIONE: usa normalizeGaraTitle per essere tollerante alla punteggiatura
  // attorno alla parola-chiave di genere (es. "TROFEO X - MASCHILE 2026" e
  // "TROFEO X FEMMINILE 2026" devono produrre la stessa chiave per essere
  // raggruppati come [M+F] nel dropdown). Il `nome` mostrato all'utente resta
  // quello "umano" della prima variante incontrata, per non stravolgere la UI.
  const gruppi = {};
  gare.forEach(gara => {
    const chiave = `${normalizeGaraTitle(gara.title)}_${gara.date}`;
    // Versione human-friendly del nome (rimuove solo la parola-chiave + spazi)
    const nomeUmano = gara.title.replace(/\s*(MASCHILE|FEMMINILE)\s*/gi, ' ').replace(/\s+/g, ' ').trim();

    if (!gruppi[chiave]) {
      gruppi[chiave] = {
        nome: nomeUmano,
        data: gara.date, // già in formato dd/mm/yyyy
        maschile: null,
        femminile: null,
        club: gara.club
      };
    }

    if (gara.tipo === 'MASCHILE') {
      gruppi[chiave].maschile = gara.id;
    } else if (gara.tipo === 'FEMMINILE') {
      gruppi[chiave].femminile = gara.id;
    } else {
      gruppi[chiave].maschile = gara.id;
    }
  });

  // Crea opzioni raggruppate
  Object.values(gruppi).forEach(gruppo => {
    let label = `${gruppo.nome} - ${gruppo.data}`;
    if (gruppo.club) {
      label += ` (${gruppo.club})`;
    }

    let value = '';
    let tipo = '';

    if (gruppo.maschile && gruppo.femminile) {
      label += ' [M+F]';
      value = `${gruppo.maschile},${gruppo.femminile}`;
      tipo = 'MISTA';
    } else if (gruppo.maschile) {
      label += ' [M]';
      value = gruppo.maschile;
      tipo = 'MASCHILE';
    } else if (gruppo.femminile) {
      label += ' [F]';
      value = gruppo.femminile;
      tipo = 'FEMMINILE';
    }

    if (value) {
      $dropdown.append(`<option value="${value}" data-tipo="${tipo}">${label}</option>`);
    }
  });

  $('#federgolf-container').show();
  $dropdown.show();
}

  /**
   * Carica iscritti dalla gara selezionata
   */
  async handleFedergolfGaraSelected() {
    const $dropdown = $('#federgolf-gare-select');
    const value = $dropdown.val();
    if (!value) return;

    const ids = value.split(',');
    const tipo = $dropdown.find(':selected').data('tipo');

    try {
      // Carica prima gara (maschile o mista)
      const response1 = await $.ajax({
        url: ($('meta[name="base-url"]').attr('content') || '') + '/user/federgolf/iscritti',
        type: 'POST',
        data: { gara_id: ids[0] },
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
      });

      // Se ci sono due gare (M+F), carica anche la seconda
      let response2 = null;
      if (ids.length > 1) {
        response2 = await $.ajax({
          url: ($('meta[name="base-url"]').attr('content') || '') + '/user/federgolf/iscritti',
          type: 'POST',
          data: { gara_id: ids[1] },
          headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
        });
      }

      // ── Errore di rete / timeout federgolf.it ───────────────────────────
      // Il controller restituisce {success:false, message:...} quando la chiamata
      // a federgolf.it fallisce (es. cURL timeout 28 per gare con molti
      // iscritti come "Quercia d'Oro"). In quel caso l'utente vede un alert
      // diagnostico e il flow viene interrotto SENZA toccare lo storage.
      const failed1 = response1 && response1.success === false;
      const failed2 = response2 && response2.success === false;
      if (failed1 || failed2) {
        const msg = (failed1 && response1.message) ||
                    (failed2 && response2.message) ||
                    'Errore nel caricamento degli iscritti da Federgolf.it. Riprovare tra qualche secondo.';
        alert('⚠ ' + msg);
        $dropdown.val('');
        return;
      }

      // ── Combina le risposte (logica pura, testabile in isolamento). ──────
      // REGRESSIONE FIX: in MISTA non abortire se UNA SOLA delle due gare ha
      // iscrizioni aperte; carica il genere chiuso (e avvisa). Prima di questo
      // fix, se solo le donne avevano iscrizioni ancora aperte, l'intera
      // selezione M+F veniva annullata, obbligando l'utente a caricare solo M.
      const merged = mergeFedergolfResponses(response1, response2, tipo);

      if (merged.abort) {
        alert('⚠ ' + merged.warning);
        // Ripristina la voce "Seleziona…" senza far ripartire il flusso.
        $dropdown.val('');
        return;
      }

      if (merged.warning) {
        alert('⚠ ' + merged.warning);
      }

      const { atleti, atlete } = merged;

      // Anche con icona-ammesso presente potrebbero esserci 0 nomi
      // (es. gara con sole donne caricata come maschile): avvisa e non
      // sovrascrivere lo stato corrente.
      if (atleti.length === 0 && atlete.length === 0) {
        alert('⚠ Nessun nominativo trovato per la gara selezionata. ' +
              'Controllare il sito federgolf.it.');
        $dropdown.val('');
        return;
      }

      // Salva e aggiorna come fa l'upload Excel
      storage.set('atlete', atlete);
      storage.set('atleti', atleti);
      storage.set('storedPlayersCount', atleti.length);
      storage.set('storedProetteCount', atlete.length);

      $('#players').val(atleti.length);
      $('#proette').val(atlete.length);
      this.config.players = atleti.length;
      this.config.proette = atlete.length;
      storage.set('players', atleti.length);
      storage.set('proette', atlete.length);

      this.config.nominativo = 'On';
      storage.set('nominativo', 'On');

      this.updateNominativoButtons();
      this.logic.updateConfig(this.config);
      this.generateTable();

      alert(`Iscritti caricati!\n${atleti.length} atleti\n${atlete.length} atlete`);

    } catch (error) {
      console.error('Errore caricamento iscritti:', error);
      alert('Errore nel caricamento degli iscritti');
    }
  }

}
// Initialize application when DOM is ready
$(document).ready(() => {
  const app = new QuadrantiApp();
  app.init();

  // Make app available globally for debugging
  window.quadrantiApp = app;
});
