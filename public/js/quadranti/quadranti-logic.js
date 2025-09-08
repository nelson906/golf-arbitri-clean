/**
 * Quadranti Logic Module
 * Contains the core business logic for calculating and generating tee times
 */

import { 
  DATEPICKER_IT, 
  TABLE_COLORS,
  COMPETITION_TYPES,
  ROUND_TYPES,
  COMPACT_TYPES
} from './config.js';

import { 
  range, 
  addTime, 
  halfTime, 
  storage,
  formatDate,
  chunkArray
} from './utils.js';

/**
 * Class representing the quadranti logic for tee time calculations
 */
export class QuadrantiLogic {
  constructor(config) {
    this.config = config;
    this.tableHTML = '';
  }

  /**
   * Updates configuration
   * @param {Object} newConfig - New configuration values
   */
  updateConfig(newConfig) {
    this.config = { ...this.config, ...newConfig };
  }

  /**
   * Initializes datepicker with Italian localization
   * @param {jQuery} $ - jQuery instance
   */
  initializeDatepicker($) {
    $.datepicker.regional['it'] = DATEPICKER_IT;
    $.datepicker.setDefaults($.datepicker.regional['it']);
    
    $('.datepicker').datepicker({
      dateFormat: 'dd-mm-yy'
    });
  }

  /**
   * Fetches sunrise and sunset times via AJAX
   * @param {string} geoArea - Geographic area
   * @param {string} date - Date in DD-MM-YYYY format
   * @returns {Promise<Object>} Promise resolving to ephemeris data
   */
  async fetchEphemerisData(geoArea, date) {
    try {
      const response = await $.ajax({
        url: '/CDN/coordinate-ajax.php',
        type: 'POST',
        dataType: 'json',
        data: { geo_area: geoArea, start: date }
      });
      return response;
    } catch (error) {
      console.error('Error fetching ephemeris data:', error);
      return { sunrise: 'N/A', sunset: 'N/A' };
    }
  }

  /**
   * Sets nominativo array based on configuration
   * @returns {Object} Object containing atleti and atlete arrays
   */
  getPlayerArrays() {
    const nominativo = this.config.nominativo;
    const players = parseInt(this.config.players);
    const proette = parseInt(this.config.proette);
    
    let atlete = storage.get('atlete', range(0, proette));
    let atleti = storage.get('atleti', range(0, players));
    
    if (nominativo !== 'On') {
      atlete = range(0, proette);
      atleti = range(0, players);
    }
    
    return { atleti, atlete };
  }

  /**
   * Adjusts array for mod correction by inserting empty slots
   * @param {Array} array - Array to correct
   * @param {number} mod - Modulo value (players per flight)
   * @param {string} direction - 'arrow_up' or 'arrow_down'
   */
  modCorrection(array, mod, direction) {
    const remainder = array.length % mod;
    
    if (remainder === 0) return;
    
    const emptySlots = mod - remainder;
    
    if (direction === 'arrow_up') {
      // Insert empty slots at regular intervals from the beginning
      for (let i = 0; i < emptySlots; i++) {
        array.splice((i + 1) * mod - 1, 0, '');
      }
    } else {
      // Insert empty slots at the end
      for (let i = 0; i < emptySlots; i++) {
        array.push('');
      }
    }
  }

  /**
   * Creates range array with nominative support
   * @param {number} start - Start index
   * @param {number} end - End index
   * @param {Array} sourceArray - Source array for names
   * @param {boolean} reverse - Whether to reverse the order
   * @param {number} mod - Players per flight
   * @returns {Array} Processed array
   */
  createRangeArray(start, end, sourceArray, reverse, mod) {
    const output = [];
    let direction = 'arrow_down';
    
    if (typeof end === 'undefined') {
      end = start;
      start = 0;
    }
    
    if (!reverse) {
      for (let i = start; i <= end; i++) {
        output.push(sourceArray[i] || '');
      }
    } else {
      for (let i = end; i >= start; i--) {
        output.push(sourceArray[i] || '');
      }
      direction = 'arrow_up';
    }
    
    this.modCorrection(output, mod, direction);
    return output;
  }

  /**
   * Builds array of flights
   * @param {number} start - Start index
   * @param {number} end - End index
   * @param {Array} sourceArray - Source array
   * @param {boolean} reverse - Whether to reverse
   * @returns {Array[]} Array of flight arrays
   */
  buildFlightArray(start, end, sourceArray, reverse = false) {
    const mod = parseInt(this.config.playersPerFlight);
    const items = this.createRangeArray(start - 1, end - 1, sourceArray, reverse, mod);
    return chunkArray(items, mod);
  }

  /**
   * Builds HTML rows for the tee time table
   * @param {Array[]} leftFlights - Flights for left side (tee 1)
   * @param {Array[]} rightFlights - Flights for right side (tee 10)
   * @param {string} playerColor - Color for player names
   * @param {string} leftTeeColor - Background color for left tee
   * @param {string} rightTeeColor - Background color for right tee
   * @param {string} startTime - Starting time
   * @param {string} gap - Time gap between flights
   * @returns {string} HTML string for table rows
   */
  buildTableRows(leftFlights, rightFlights, playerColor, leftTeeColor, rightTeeColor, startTime, gap) {
    let html = '';
    let currentTime = startTime;
    const mod = parseInt(this.config.playersPerFlight);
    const maxFlights = Math.max(leftFlights.length, rightFlights.length);
    
    for (let i = 0; i < maxFlights; i++) {
      html += '<tr>';
      
      // Flight number
      html += `<td style="background-color:${leftTeeColor}; color: black">${i + 1}</td>`;
      
      // Left tee
      html += '<td style="color: black">1</td>';
      
      // Left flight players
      if (leftFlights[i]) {
        leftFlights[i].forEach(player => {
          html += `<td style="color: ${playerColor}">${player}</td>`;
        });
      } else {
        html += `<td colspan="${mod}"></td>`;
      }
      
      // Time
      html += `<td>${currentTime}</td>`;
      
      // Right flight players
      if (rightFlights[i]) {
        rightFlights[i].forEach(player => {
          html += `<td style="color: ${playerColor}">${player}</td>`;
        });
      } else {
        html += `<td colspan="${mod}"></td>`;
      }
      
      // Right tee
      html += '<td style="color: black">10</td>';
      
      // Flight number
      const rightFlightNum = leftFlights.length + i + 1;
      html += `<td style="background-color:${rightTeeColor}; color: black">${rightFlightNum}</td>`;
      
      html += '</tr>';
      
      currentTime = addTime(currentTime, gap);
    }
    
    return html;
  }

  /**
   * Calculates lower flights for double tee configuration
   * @param {number} playerCount - Number of players
   * @param {number} mod - Players per flight
   * @returns {number} Number of lower flights
   */
  calculateLowerFlights(playerCount, mod) {
    const totalFlights = Math.ceil(playerCount / mod);
    let lowerFlights = Math.floor(totalFlights / 2);
    
    // Ensure lower flights are even
    if (mod === 3) {
      switch (playerCount % (2 * mod)) {
        case 1:
        case 2:
        case 3:
          if (lowerFlights % 2 === 1) lowerFlights++;
          break;
        default:
          if (lowerFlights % 2 === 1) lowerFlights--;
      }
    } else if (mod === 4) {
      switch (playerCount % (4 * mod)) {
        case 1:
        case 2:
        case 3:
        case 4:
          if (lowerFlights % 2 === 1) lowerFlights++;
          break;
        default:
          if (lowerFlights % 2 === 1) lowerFlights--;
      }
    }
    
    return lowerFlights;
  }

  /**
   * Calculates quadrant limits for men
   * @param {number} playerCount - Number of players
   * @param {number} mod - Players per flight
   * @returns {Array} Array of limits [limit1, limit2, limit3]
   */
  calculateQuadrantLimits(playerCount, mod) {
    const lowerFlights = this.calculateLowerFlights(playerCount, mod);
    const upperFlights = Math.ceil(playerCount / mod) - lowerFlights;
    const leftFlights = Math.ceil(upperFlights / 2);
    
    const limit1 = (mod * lowerFlights) / 2;
    const limit2 = mod * lowerFlights;
    const limit3 = mod * lowerFlights + mod * leftFlights;
    
    return [limit1, limit2, limit3];
  }

  /**
   * Calculates quadrant limits for NT (Technical Rules) configuration
   * @param {number} menCount - Number of men
   * @param {number} womenCount - Number of women
   * @param {number} mod - Players per flight
   * @returns {Object} Object containing limits
   */
  calculateQuadrantLimitsNT(menCount, womenCount, mod) {
    const totalFlights = Math.ceil(menCount / mod) + Math.ceil(womenCount / mod);
    
    let lowerFlights = Math.ceil(totalFlights / 2);
    if (lowerFlights % 2 === 1) lowerFlights++;
    
    const upperFlightsMen = Math.ceil(menCount / mod) - lowerFlights;
    const leftFlightsMen = Math.ceil(upperFlightsMen / 2);
    
    const flightsWomen = Math.ceil(womenCount / mod);
    const leftFlightsWomen = Math.ceil(flightsWomen / 2);
    
    return {
      limit1: mod * leftFlightsWomen,
      limit2: mod * lowerFlights,
      limit3: mod * lowerFlights + mod * leftFlightsMen
    };
  }

  /**
   * Generates double tee configuration
   * @param {string} round - 'prima' or 'seconda'
   * @returns {string} HTML table content
   */
  generateDoubleTee(round) {
    const { atleti, atlete } = this.getPlayerArrays();
    const mod = parseInt(this.config.playersPerFlight);
    const players = parseInt(this.config.players);
    const proette = parseInt(this.config.proette);
    const { limit1, limit2, limit3 } = this.calculateQuadrantLimitsNT(players, proette, mod);
    
    let tableHTML = '';
    let startTime = this.config.startTime;
    const gap = this.config.gap;
    const compatto = this.config.compatto;
    const gara = this.config.garaNT;
    const roundTime = this.config.round;
    
    // Build flight arrays
    const arrays = this.buildQuadrantArrays(
      { limit1, limit2, limit3 }, 
      atleti, 
      atlete, 
      players, 
      proette,
      gara === COMPETITION_TYPES.GARA_36 && round === ROUND_TYPES.SECOND
    );
    
    // Generate table based on competition type and round
    if (gara === COMPETITION_TYPES.GARA_54) {
      tableHTML = this.generate54HoleTable(arrays, round, startTime, gap, compatto, roundTime);
    } else {
      tableHTML = this.generate36HoleTable(arrays, round, startTime, gap, compatto, roundTime);
    }
    
    return `<table>${tableHTML}</table>`;
  }

  /**
   * Builds quadrant arrays for double tee
   */
  buildQuadrantArrays(limits, atleti, atlete, players, proette, reverse) {
    const direction = reverse ? 'inverse' : '';
    
    const firstArray = this.buildFlightArray(1, limits.limit1, atlete, direction);
    const secondArray = this.buildFlightArray(limits.limit1 + 1, proette, atlete, direction);
    const thirdArray = this.buildFlightArray(limits.limit2 + 1, limits.limit3, atleti, direction);
    const fourthArray = this.buildFlightArray(limits.limit3 + 1, players, atleti, direction);
    const fifthArray = this.buildFlightArray(1, limits.limit2 / 2, atleti, direction);
    const sixthArray = this.buildFlightArray(limits.limit2 / 2 + 1, limits.limit2, atleti, direction);
    
    // Apply reversals based on direction
    if (direction !== 'inverse') {
      firstArray.reverse();
      fourthArray.reverse();
      fifthArray.reverse();
    } else {
      secondArray.reverse();
      thirdArray.reverse();
      sixthArray.reverse();
    }
    
    return { firstArray, secondArray, thirdArray, fourthArray, fifthArray, sixthArray };
  }

  /**
   * Generates table for 54-hole competition
   */
  generate54HoleTable(arrays, round, startTime, gap, compatto, roundTime) {
    let html = '';
    const colors = TABLE_COLORS.teeColors;
    
    if (round === ROUND_TYPES.FIRST) {
      html += this.buildTableRows(
        arrays.firstArray, 
        arrays.secondArray, 
        TABLE_COLORS.women, 
        'transparent', 
        'transparent',
        startTime,
        gap
      );
      
      startTime = addTime(startTime, '00:10');
      html += this.buildTableRows(
        arrays.thirdArray, 
        arrays.fourthArray, 
        TABLE_COLORS.men, 
        colors.orange, 
        colors.lightGreen,
        startTime,
        gap
      );
      
      html += '<tr><td colspan="20">&nbsp;</td></tr>';
      
      if (compatto === COMPACT_TYPES.EARLY_LATE) {
        startTime = addTime(startTime, halfTime(roundTime));
      }
      startTime = addTime(startTime, '00:10');
      
      html += this.buildTableRows(
        arrays.fifthArray, 
        arrays.sixthArray, 
        TABLE_COLORS.men, 
        colors.lightGray, 
        colors.yellow,
        startTime,
        gap
      );
    } else {
      // Second round - reverse order
      html += this.buildTableRows(
        arrays.sixthArray, 
        arrays.fifthArray, 
        TABLE_COLORS.men, 
        colors.lightGray, 
        colors.yellow,
        startTime,
        gap
      );
      
      html += '<tr><td colspan="20">&nbsp;</td></tr>';
      
      if (compatto === COMPACT_TYPES.EARLY_LATE) {
        startTime = addTime(startTime, halfTime(roundTime));
      }
      startTime = addTime(startTime, '00:10');
      
      html += this.buildTableRows(
        arrays.secondArray, 
        arrays.firstArray, 
        TABLE_COLORS.women, 
        'transparent', 
        'transparent',
        startTime,
        gap
      );
      
      startTime = addTime(startTime, '00:10');
      html += this.buildTableRows(
        arrays.fourthArray, 
        arrays.thirdArray, 
        TABLE_COLORS.men, 
        colors.orange, 
        colors.lightGreen,
        startTime,
        gap
      );
    }
    
    return html;
  }

  /**
   * Generates table for 36-hole competition
   */
  generate36HoleTable(arrays, round, startTime, gap, compatto, roundTime) {
    let html = '';
    const colors = TABLE_COLORS.teeColors;
    
    if (round === ROUND_TYPES.FIRST) {
      html += this.buildTableRows(
        arrays.sixthArray, 
        arrays.fifthArray, 
        TABLE_COLORS.men, 
        colors.orange, 
        colors.lightGreen,
        startTime,
        gap
      );
      
      html += '<tr><td colspan="20">&nbsp;</td></tr>';
      
      if (compatto === COMPACT_TYPES.EARLY_LATE) {
        startTime = addTime(startTime, halfTime(roundTime));
      }
      startTime = addTime(startTime, '00:10');
      
      html += this.buildTableRows(
        arrays.secondArray, 
        arrays.firstArray, 
        TABLE_COLORS.women, 
        'none', 
        'none',
        startTime,
        gap
      );
      
      startTime = addTime(startTime, '00:10');
      html += this.buildTableRows(
        arrays.fourthArray, 
        arrays.thirdArray, 
        TABLE_COLORS.men, 
        colors.lightGray, 
        colors.yellow,
        startTime,
        gap
      );
    } else {
      // Second round
      html += this.buildTableRows(
        arrays.firstArray, 
        arrays.secondArray, 
        TABLE_COLORS.women, 
        'none', 
        'none',
        startTime,
        gap
      );
      
      startTime = addTime(startTime, '00:10');
      html += this.buildTableRows(
        arrays.thirdArray, 
        arrays.fourthArray, 
        TABLE_COLORS.men, 
        colors.lightGray, 
        colors.yellow,
        startTime,
        gap
      );
      
      html += '<tr><td colspan="20">&nbsp;</td></tr>';
      
      if (compatto === COMPACT_TYPES.EARLY_LATE) {
        startTime = addTime(startTime, halfTime(roundTime));
      }
      startTime = addTime(startTime, '00:10');
      
      html += this.buildTableRows(
        arrays.fifthArray, 
        arrays.sixthArray, 
        TABLE_COLORS.men, 
        colors.orange, 
        colors.lightGreen,
        startTime,
        gap
      );
    }
    
    return html;
  }

  /**
   * Generates single tee configuration
   * @param {string} symmetric - 'Simmetrico' or 'Asimmetrico'
   * @param {string} round - 'prima' or 'seconda'
   * @returns {string} HTML table content
   */
  generateSingleTee(symmetric, round) {
    const { atleti, atlete } = this.getPlayerArrays();
    const mod = parseInt(this.config.playersPerFlight);
    const players = parseInt(this.config.players);
    const proette = parseInt(this.config.proette);
    
    const upperFlightsMen = Math.ceil(players / mod / 2);
    const lowerFlightsMen = Math.ceil(players / mod) - upperFlightsMen;
    const limit = mod * lowerFlightsMen;
    
    // Build arrays
    const firstArray = this.buildFlightArray(limit + 1, players, atleti, true);
    const secondArray = this.buildFlightArray(1, proette, atlete, true);
    const thirdArray = this.buildFlightArray(1, limit, atleti, true);
    
    let tableHTML = '<table>';
    tableHTML += this.generateSingleTeeTable(
      firstArray, 
      secondArray, 
      thirdArray, 
      symmetric, 
      round,
      this.config.startTime,
      this.config.gap
    );
    tableHTML += '</table>';
    
    return tableHTML;
  }

  /**
   * Generates single tee table rows
   */
  generateSingleTeeTable(firstArray, secondArray, thirdArray, symmetric, round, startTime, gap) {
    let html = '';
    let currentTime = startTime;
    const mod = parseInt(this.config.playersPerFlight);
    
    const womenStyle = 'style="font-style:italic; color:red"';
    const menStyle = '';
    
    let primaryArray = symmetric === 'Asimmetrico' ? secondArray : firstArray;
    let secondaryArray = symmetric === 'Asimmetrico' ? firstArray : secondArray;
    let primaryStyle = symmetric === 'Asimmetrico' ? womenStyle : menStyle;
    let secondaryStyle = symmetric === 'Asimmetrico' ? menStyle : womenStyle;
    
    const generateFlightRows = (array, style, startFlight = 1) => {
      let rows = '';
      array.forEach((flight, i) => {
        rows += `<tr ${style}>`;
        rows += `<td>${startFlight + i}</td>`;
        rows += '<td>1</td>';
        flight.forEach(player => {
          rows += `<td>${player}</td>`;
        });
        rows += `<td>${currentTime}</td>`;
        rows += '</tr>';
        currentTime = addTime(currentTime, gap);
      });
      return rows;
    };
    
    if (round === ROUND_TYPES.FIRST) {
      html += generateFlightRows(primaryArray, primaryStyle);
      currentTime = addTime(currentTime, '00:10');
      
      html += generateFlightRows(secondaryArray, secondaryStyle);
      currentTime = addTime(currentTime, '00:10');
      
      html += generateFlightRows(thirdArray, '', primaryArray.length + secondaryArray.length + 1);
    } else {
      html += generateFlightRows(thirdArray, secondaryStyle);
      currentTime = addTime(currentTime, '00:10');
      
      html += generateFlightRows(secondaryArray, secondaryStyle);
      currentTime = addTime(currentTime, '00:10');
      
      html += generateFlightRows(primaryArray, primaryStyle, thirdArray.length + secondaryArray.length + 1);
    }
    
    return html;
  }

  /**
   * Generates table header
   * @param {boolean} doubleTee - Whether double tee configuration
   * @returns {string} HTML table header
   */
  generateTableHeader(doubleTee) {
    const mod = parseInt(this.config.playersPerFlight);
    let header = '<tr>';
    header += '<td>Flight</td>';
    header += '<td>Tee</td>';
    header += `<td colspan="${mod}">Nome</td>`;
    header += '<td>Orario</td>';
    
    if (doubleTee) {
      header += `<td colspan="${mod}">Nome</td>`;
      header += '<td>Tee</td>';
      header += '<td>Flight</td>';
    }
    
    header += '</tr>';
    return header;
  }
}
