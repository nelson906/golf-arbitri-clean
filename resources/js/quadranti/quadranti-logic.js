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
        url: '/user/quadranti/coordinates',
        type: 'POST',
        dataType: 'json',
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
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
    
    let atlete = storage.get('atlete', []);
    let atleti = storage.get('atleti', []);
    
    // Check if stored arrays have the correct length or if nominativo is off
    const storedPlayersCount = storage.get('storedPlayersCount', 0);
    const storedProetteCount = storage.get('storedProetteCount', 0);
    
    if (nominativo !== 'On' || 
        atleti.length !== players || 
        atlete.length !== proette ||
        storedPlayersCount !== players ||
        storedProetteCount !== proette) {
      // Generate numeric arrays
      atlete = range(1, proette);
      atleti = range(1, players);
      
      // If nominativo is off, clear stored names
      if (nominativo !== 'On') {
        storage.remove('atlete');
        storage.remove('atleti');
        storage.remove('storedPlayersCount');
        storage.remove('storedProetteCount');
      }
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
  buildTableRows(leftFlights, rightFlights, playerColor, leftTeeColor, rightTeeColor, startTime, gap, leftOffset = 0, rightOffset = 0) {
    let html = '';
    let currentTime = startTime;
    const mod = parseInt(this.config.playersPerFlight);
    const maxFlights = Math.max(leftFlights.length, rightFlights.length);
    
    for (let i = 0; i < maxFlights; i++) {
      html += '<tr>';
      
      // Flight number for left side
      const leftFlightNum = leftOffset + i + 1;
      html += `<td class="text-center px-2 py-1 border border-gray-300 font-medium" style="background-color:${leftTeeColor}">${leftFlightNum}</td>`;
      
      // Left tee
      html += '<td class="text-center px-2 py-1 border border-gray-300 font-medium">1</td>';
      
      // Left flight players
      if (leftFlights[i]) {
        leftFlights[i].forEach(player => {
          html += `<td class="text-center px-2 py-1 border border-gray-300" style="color: ${playerColor}">${player}</td>`;
        });
      } else {
        html += `<td colspan="${mod}" class="text-center px-2 py-1 border border-gray-300"></td>`;
      }
      
      // Time
      html += `<td class="text-center px-2 py-1 border border-gray-300 font-medium">${currentTime}</td>`;
      
      // Right flight players
      if (rightFlights[i]) {
        rightFlights[i].forEach(player => {
          html += `<td class="text-center px-2 py-1 border border-gray-300" style="color: ${playerColor}">${player}</td>`;
        });
      } else {
        html += `<td colspan="${mod}" class="text-center px-2 py-1 border border-gray-300"></td>`;
      }
      
      // Right tee
      html += '<td class="text-center px-2 py-1 border border-gray-300 font-medium">10</td>';
      
      // Flight number for right side
      const rightFlightNum = rightOffset + i + 1;
      html += `<td class="text-center px-2 py-1 border border-gray-300 font-medium" style="background-color:${rightTeeColor}">${rightFlightNum}</td>`;
      
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
    
    // Calculate flight counts for proper numbering
    let womenFlightCount = 0;
    let menFlightCount = 0;
    
    if (round === ROUND_TYPES.FIRST) {
      // Women - order: Q1 -> Q3 (empty) -> Q2 -> Q4 (empty)
      // Since women only have 2 quadrants, Q1 is left-top, Q2 is right-top
      const q1WomenFlights = arrays.firstArray.length;
      const q2WomenFlights = arrays.secondArray.length;
      
      html += this.buildTableRows(
        arrays.firstArray,  // Q1 women
        arrays.secondArray, // Q2 women 
        TABLE_COLORS.women, 
        'transparent', 
        'transparent',
        startTime,
        gap,
        0,  // Q1 women starts at 1
        q1WomenFlights  // Q2 women continues after Q1 (since no Q3 for women)
      );
      
      startTime = addTime(startTime, '00:10');
      
      // Men - calculate all quadrant sizes first
      const q1MenFlights = arrays.thirdArray.length;   // Q1 upper left
      const q2MenFlights = arrays.fourthArray.length;  // Q2 upper right
      const q3MenFlights = arrays.fifthArray.length;   // Q3 lower left
      const q4MenFlights = arrays.sixthArray.length;   // Q4 lower right
      
      // Upper row - Q1 and Q2
      html += this.buildTableRows(
        arrays.thirdArray,  // Q1 men (left)
        arrays.fourthArray, // Q2 men (right)
        TABLE_COLORS.men, 
        colors.orange, 
        colors.lightGreen,
        startTime,
        gap,
        0,  // Q1 starts at 1
        q1MenFlights + q3MenFlights  // Q2 starts after Q1->Q3 (vertical order)
      );
      
      html += '<tr><td colspan="20" class="py-2">&nbsp;</td></tr>';
      
      if (compatto === COMPACT_TYPES.EARLY_LATE) {
        startTime = addTime(startTime, halfTime(roundTime));
      }
      startTime = addTime(startTime, '00:10');
      
      // Lower row - Q3 and Q4
      html += this.buildTableRows(
        arrays.fifthArray,  // Q3 men (left)
        arrays.sixthArray,  // Q4 men (right)
        TABLE_COLORS.men, 
        colors.lightGray, 
        colors.yellow,
        startTime,
        gap,
        q1MenFlights,  // Q3 starts after Q1
        q1MenFlights + q3MenFlights + q2MenFlights  // Q4 starts after Q1->Q3->Q2
      );
    } else {
      // Second round - calculate all sizes first
      const q1MenFlights = arrays.thirdArray.length;
      const q2MenFlights = arrays.fourthArray.length;
      const q3MenFlights = arrays.fifthArray.length;
      const q4MenFlights = arrays.sixthArray.length;
      
      // Men bottom row first (reverse order: Q4->Q2->Q3->Q1)
      html += this.buildTableRows(
        arrays.sixthArray,  // Q4 men
        arrays.fifthArray,  // Q3 men
        TABLE_COLORS.men, 
        colors.lightGray, 
        colors.yellow,
        startTime,
        gap,
        0,  // Q4 starts at 1
        q4MenFlights + q2MenFlights  // Q3 starts after Q4->Q2 (reverse vertical)
      );
      
      html += '<tr><td colspan="20" class="py-2">&nbsp;</td></tr>';
      
      if (compatto === COMPACT_TYPES.EARLY_LATE) {
        startTime = addTime(startTime, halfTime(roundTime));
      }
      startTime = addTime(startTime, '00:10');
      
      // Women Q2 and Q1 (reverse order)
      const q2WomenFlights = arrays.secondArray.length;
      const q1WomenFlights = arrays.firstArray.length;
      
      html += this.buildTableRows(
        arrays.secondArray,  // Q2 women
        arrays.firstArray,   // Q1 women
        TABLE_COLORS.women, 
        'transparent', 
        'transparent',
        startTime,
        gap,
        0,  // Q2 women starts at 1 (separate from men)
        q2WomenFlights  // Q1 women continues after Q2
      );
      
      startTime = addTime(startTime, '00:10');
      
      // Men upper row (Q2 and Q1)
      html += this.buildTableRows(
        arrays.fourthArray,  // Q2 men
        arrays.thirdArray,   // Q1 men
        TABLE_COLORS.men, 
        colors.orange, 
        colors.lightGreen,
        startTime,
        gap,
        q4MenFlights,  // Q2 starts after Q4
        q4MenFlights + q2MenFlights + q3MenFlights  // Q1 starts after Q4->Q2->Q3
      );
    }
    
    return html + '</tbody>';
  }

  /**
   * Generates table for 36-hole competition
   */
  generate36HoleTable(arrays, round, startTime, gap, compatto, roundTime) {
    let html = '';
    const colors = TABLE_COLORS.teeColors;
    
    if (round === ROUND_TYPES.FIRST) {
      // Men Q1 and Q2 (using sixth and fifth arrays)
      const q1MenFlights = arrays.sixthArray.length;
      const q2MenFlights = arrays.fifthArray.length;
      
      html += this.buildTableRows(
        arrays.sixthArray,  // Q1 men
        arrays.fifthArray,  // Q2 men
        TABLE_COLORS.men, 
        colors.orange, 
        colors.lightGreen,
        startTime,
        gap,
        0,  // Q1 men starts at 1
        q1MenFlights  // Q2 men continues after Q1
      );
      
      html += '<tr><td colspan="20" class="py-2">&nbsp;</td></tr>';
      
      if (compatto === COMPACT_TYPES.EARLY_LATE) {
        startTime = addTime(startTime, halfTime(roundTime));
      }
      startTime = addTime(startTime, '00:10');
      
      // Women Q1 and Q2
      const q1WomenFlights = arrays.secondArray.length;
      const q2WomenFlights = arrays.firstArray.length;
      
      html += this.buildTableRows(
        arrays.secondArray,  // Q1 women
        arrays.firstArray,   // Q2 women
        TABLE_COLORS.women, 
        'none', 
        'none',
        startTime,
        gap,
        0,  // Q1 women starts at 1
        q1WomenFlights  // Q2 women continues after Q1
      );
      
      startTime = addTime(startTime, '00:10');
      
      // Men Q3 and Q4 (lower row)
      const q3MenFlights = arrays.fourthArray.length;
      const q4MenFlights = arrays.thirdArray.length;
      
      html += this.buildTableRows(
        arrays.fourthArray,  // Q3 men
        arrays.thirdArray,   // Q4 men
        TABLE_COLORS.men, 
        colors.lightGray, 
        colors.yellow,
        startTime,
        gap,
        q1MenFlights,  // Q3 starts after Q1 (vertical order)
        q1MenFlights + q3MenFlights + q2MenFlights  // Q4 starts after Q1->Q3->Q2
      );
    } else {
      // Second round - women first
      // Women Q2 and Q1 (reverse order)
      const q2WomenFlights = arrays.firstArray.length;
      const q1WomenFlights = arrays.secondArray.length;
      
      html += this.buildTableRows(
        arrays.firstArray,   // Q2 women
        arrays.secondArray,  // Q1 women
        TABLE_COLORS.women, 
        'none', 
        'none',
        startTime,
        gap,
        0,  // Q2 women starts at 1
        q2WomenFlights  // Q1 women continues after Q2
      );
      
      startTime = addTime(startTime, '00:10');
      
      // Men Q1 and Q2
      const q1MenFlights = arrays.thirdArray.length;
      const q2MenFlights = arrays.fourthArray.length;
      
      html += this.buildTableRows(
        arrays.thirdArray,   // Q1 men
        arrays.fourthArray,  // Q2 men
        TABLE_COLORS.men, 
        colors.lightGray, 
        colors.yellow,
        startTime,
        gap,
        0,  // Q1 men starts at 1 (separate from women)
        q1MenFlights  // Q2 men continues after Q1
      );
      
      html += '<tr><td colspan="20" class="py-2">&nbsp;</td></tr>';
      
      if (compatto === COMPACT_TYPES.EARLY_LATE) {
        startTime = addTime(startTime, halfTime(roundTime));
      }
      startTime = addTime(startTime, '00:10');
      
      // Men Q3 and Q4
      const q3MenFlights = arrays.fifthArray.length;
      const q4MenFlights = arrays.sixthArray.length;
      
      html += this.buildTableRows(
        arrays.fifthArray,   // Q3 men
        arrays.sixthArray,   // Q4 men
        TABLE_COLORS.men, 
        colors.orange, 
        colors.lightGreen,
        startTime,
        gap,
        q1MenFlights,  // Q3 starts after Q1 (vertical order)
        q1MenFlights + q3MenFlights + q2MenFlights  // Q4 starts after Q1->Q3->Q2
      );
    }
    
    return html + '</tbody>';
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
    
    let tableHTML = this.generateTableHeader(false);
    tableHTML += this.generateSingleTeeTable(
      firstArray, 
      secondArray, 
      thirdArray, 
      symmetric, 
      round,
      this.config.startTime,
      this.config.gap
    );
    tableHTML += '</tbody>';
    
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
        rows += `<tr>`;
        rows += `<td class="text-center px-2 py-1 border border-gray-300 font-medium">${startFlight + i}</td>`;
        rows += '<td class="text-center px-2 py-1 border border-gray-300 font-medium">1</td>';
        flight.forEach(player => {
          rows += `<td class="text-center px-2 py-1 border border-gray-300" ${style}>${player}</td>`;
        });
        rows += `<td class="text-center px-2 py-1 border border-gray-300 font-medium">${currentTime}</td>`;
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
    let header = '<thead class="bg-gray-50"><tr>';
    header += '<th class="text-center px-2 py-2 border border-gray-300">Flight</th>';
    header += '<th class="text-center px-2 py-2 border border-gray-300">Tee</th>';
    header += `<th colspan="${mod}" class="text-center px-2 py-2 border border-gray-300">Nome</th>`;
    header += '<th class="text-center px-2 py-2 border border-gray-300">Orario</th>';
    
    if (doubleTee) {
      header += `<th colspan="${mod}" class="text-center px-2 py-2 border border-gray-300">Nome</th>`;
      header += '<th class="text-center px-2 py-2 border border-gray-300">Tee</th>';
      header += '<th class="text-center px-2 py-2 border border-gray-300">Flight</th>';
    }
    
    header += '</tr></thead><tbody>';
    return header;
  }
}
