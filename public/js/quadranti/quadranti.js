/**
 * Quadranti Application
 * Main entry point for the Quadranti (Starting Times Simulator) application
 */

import { 
  DEFAULT_CONFIG, 
  TEE_TYPES,
  LAYOUT_TYPES,
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

import { QuadrantiLogic } from './quadranti-logic.js';

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
    $('#simmetrico').val(this.config.simmetrico);
    
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
    
    // Button clicks
    $('#refresh').on('click', () => this.handleReset());
    $('#excel').on('click', () => this.handleExcelExport());
    $('#btnClick').on('click', (e) => this.handleNominativoToggle(e, 'On'));
    $('#btnClock').on('click', (e) => this.handleNominativoToggle(e, 'Off'));
    
    // File upload
    $('#upload').on('change', (e) => this.handleFileUpload(e));
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
    // Update configuration from form values
    this.config.players = $('#players').val();
    this.config.proette = $('#proette').val();
    this.config.playersPerFlight = $('#players_x_flight').val();
    this.config.giornata = $('#giornata').val();
    this.config.garaNT = $('#gara_NT').val();
    this.config.round = $('#round').val();
    this.config.startTime = $('#start_time').val();
    this.config.gap = $('#gap').val();
    this.config.compatto = $('#compatto').val();
    this.config.doppiePartenze = $('#doppie_partenze').val();
    this.config.simmetrico = $('#simmetrico').val();
    
    // Save configuration
    this.saveConfiguration();
    
    // Update UI elements
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
   * Handles Excel export
   */
  handleExcelExport() {
    if (typeof $.fn.table2excel !== 'undefined') {
      $('#first_table').table2excel({
        exclude: '.excludeThisClass',
        name: 'Foglio di Lavoro',
        filename: 'Simulatore_Partenze.xls',
        preserveColors: true
      });
    } else {
      alert('La funzione di esportazione Excel non è disponibile');
    }
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
   * Handles file upload for player names
   */
  async handleFileUpload(e) {
    e.preventDefault();
    
    const file = $('#file')[0].files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('file', file);
    
    try {
      const response = await $.ajax({
        url: '/CDN/load_excel.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false
      });
      
      const data = JSON.parse(response);
      
      if (data && data[0] && data[1]) {
        const atlete = Object.values(data[0]);
        const atleti = Object.values(data[1]);
        
        storage.set('atlete', atlete);
        storage.set('atleti', atleti);
        
        this.config.nominativo = 'On';
        storage.set('nominativo', 'On');
        
        this.updateNominativoButtons();
        this.logic.updateConfig(this.config);
        this.generateTable();
        
        alert('File caricato con successo!');
      }
    } catch (error) {
      console.error('Error uploading file:', error);
      alert('Errore durante il caricamento del file');
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
    const totalPlayers = parseInt(this.config.players) + parseInt(this.config.proette);
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
   * Generates and displays the tee time table
   */
  generateTable() {
    let tableHTML = '';
    const doppiePartenze = this.config.doppiePartenze;
    const simmetrico = this.config.simmetrico;
    const giornata = this.config.giornata;
    const mod = parseInt(this.config.playersPerFlight);
    
    // Add table header
    if (doppiePartenze === TEE_TYPES.DOUBLE) {
      tableHTML = this.logic.generateTableHeader(true);
      
      if (simmetrico === LAYOUT_TYPES.SYMMETRIC) {
        // Symmetric layout not implemented in original code
        // Would need to implement tee_doppio_simmetrico logic
        tableHTML += this.logic.generateDoubleTee(giornata);
      } else {
        tableHTML += this.logic.generateDoubleTee(giornata);
      }
    } else {
      tableHTML = this.logic.generateTableHeader(false);
      tableHTML = this.logic.generateSingleTee(simmetrico, giornata);
    }
    
    $('#first_table').html(tableHTML);
  }
}

// Initialize application when DOM is ready
$(document).ready(() => {
  const app = new QuadrantiApp();
  app.init();
  
  // Make app available globally for debugging
  window.quadrantiApp = app;
});
