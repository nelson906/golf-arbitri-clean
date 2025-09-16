/**
 * Quadranti Logic Module - Updated Version
 * Contains the core business logic for calculating and generating tee times
 * with improved quadrant balancing algorithm
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
   * NEW: Balances quadrants for optimal distribution
   * @param {number} players - Total number of players
   * @param {number} mod - Players per flight (3 or 4)
   * @returns {Object} Quadrant distribution
   */
  bilanciaQuadranti(players, mod = 3) {
    let totalMatches = Math.ceil(players / mod);

    // Start with equal distribution
    let Q1 = Math.floor(totalMatches / 4);
    let Q2 = Math.floor(totalMatches / 4);
    let Q3 = Math.floor(totalMatches / 4);
    let Q4 = Math.floor(totalMatches / 4);

    // Distribute remainder
    let remainder = totalMatches % 4;
    if (remainder > 0) Q1++;
    if (remainder > 1) Q2++;
    if (remainder > 2) Q4++;

    // Apply special rules based on player count modulo
    const rem12 = players % 12;

    if (mod === 3) {
      if ((rem12 === 1 || rem12 === 2 || rem12 === 3) && Q3 > 0) {
        Q3--;
        Q2++;
      }
    } else if (mod === 4) {
      const rem16 = players % 16;
      if ((rem16 === 1 || rem16 === 2 || rem16 === 3 || rem16 === 4) && Q3 > 0) {
        Q3--;
        Q2++;
      }
    }

    return { Q1, Q2, Q3, Q4 };
  }

  /**
   * NEW: Calculates quadrant limits based on balanced distribution
   * @param {number} players - Total number of players
   * @param {number} mod - Players per flight
   * @returns {Object} Limits and quadrant info
   */
  limitiQuadranti(players, mod) {
    const quadranti = this.bilanciaQuadranti(players, mod);

    // Calculate total flights
    let sumQuadranti = quadranti.Q1 + quadranti.Q2 + quadranti.Q3 + quadranti.Q4;

    // Calculate players per quadrant
    let playersQ1 = mod * quadranti.Q1;
    let playersQ2 = mod * quadranti.Q2;
    let playersQ3 = mod * quadranti.Q3;
    let playersQ4 = mod * quadranti.Q4;

    // Handle incomplete flights (difference between full capacity and actual players)
    let fullPlayers = sumQuadranti * mod;
    let difference = fullPlayers - players;

    // Remove difference from Q1 (incomplete flight handling)
    playersQ1 = playersQ1 - difference;

    // Calculate limits for player distribution
    let limit1 = playersQ2;                           // End of Q2
    let limit2 = playersQ2 + playersQ1;              // End of Q1 (Q2+Q1)
    let limit3 = players - playersQ3;                // Start of Q3

    return {
      limit1,
      limit2,
      limit3,
      players,
      quadranti,
      difference,
      playersPerQuadrant: {
        Q1: playersQ1,
        Q2: playersQ2,
        Q3: playersQ3,
        Q4: playersQ4
      }
    };
  }

  /**
   * NEW: Remaps quadrants for day 2 rotation
   * @param {string} originalQuadrant - Original quadrant (Q1-Q4)
   * @param {number} dayNumber - Day number (1 or 2)
   * @returns {string} Remapped quadrant
   */
  remapQuadrant(originalQuadrant, dayNumber) {
    if (dayNumber === 1) {
      return originalQuadrant;
    }

    // Day 2 mapping: Q1->Q4, Q2->Q3, Q3->Q1, Q4->Q2
    const mapping = {
      'Q1': 'Q4',
      'Q2': 'Q3',
      'Q3': 'Q1',
      'Q4': 'Q2'
    };

    return mapping[originalQuadrant] || originalQuadrant;
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
   * NEW: Generates player groups based on new quadrant logic
   * @param {number} players - Total players
   * @param {number} mod - Players per flight
   * @param {Array} sourceArray - Source array of player names/numbers
   * @param {string} category - 'M' for men, 'F' for women
   * @returns {Array} Array of player groups with quadrant info
   */
  generatePlayerGroups(players, mod, sourceArray, category = 'M') {
    const limits = this.limitiQuadranti(players, mod);
    const groups = [];
    const difference = limits.difference;

    // Q2: Players 1 to limit1 (descending order, from largest to smallest)
    const q2Players = sourceArray.slice(0, limits.limit1);
    const q2Groups = [];
    for (let i = q2Players.length - 1; i >= 0; i -= mod) {
      const group = [];
      for (let j = 0; j < mod && (i - j) >= 0; j++) {
        group.push(q2Players[i - j]);
      }
      if (group.length > 0) {
        q2Groups.push({
          players: group.reverse(), // Reverse to maintain ascending order within group
          quadrant: 'Q2',
          type: 'Early',
          category: category
        });
      }
    }
    groups.push(...q2Groups);

    // Q1: Players from limit1+1 to limit2 (ascending order, handling difference)
    const q1Players = sourceArray.slice(limits.limit1, limits.limit2);
    const q1Groups = [];

    if (difference === 1 && q1Players.length > 0) {
      // First group has 2 players only
      if (q1Players.length >= 2) {
        q1Groups.push({
          players: [q1Players[0], q1Players[1]],
          quadrant: 'Q1',
          type: 'Early',
          category: category
        });
      }
      // Rest in groups of mod
      for (let i = 2; i < q1Players.length; i += mod) {
        const group = q1Players.slice(i, Math.min(i + mod, q1Players.length));
        if (group.length > 0) {
          q1Groups.push({
            players: group,
            quadrant: 'Q1',
            type: 'Early',
            category: category
          });
        }
      }
    } else if (difference === 2 && q1Players.length > 0) {
      // First two groups have 2 players each
      if (q1Players.length >= 2) {
        q1Groups.push({
          players: [q1Players[0], q1Players[1]],
          quadrant: 'Q1',
          type: 'Early',
          category: category
        });
      }
      if (q1Players.length >= 4) {
        q1Groups.push({
          players: [q1Players[2], q1Players[3]],
          quadrant: 'Q1',
          type: 'Early',
          category: category
        });
      }
      // Rest in groups of mod
      for (let i = 4; i < q1Players.length; i += mod) {
        const group = q1Players.slice(i, Math.min(i + mod, q1Players.length));
        if (group.length > 0) {
          q1Groups.push({
            players: group,
            quadrant: 'Q1',
            type: 'Early',
            category: category
          });
        }
      }
    } else if (difference === 3 && mod === 3) {
      // Special case: remove last flight from Q3, not from Q1
      // Q1 keeps normal grouping
      for (let i = 0; i < q1Players.length; i += mod) {
        const group = q1Players.slice(i, Math.min(i + mod, q1Players.length));
        if (group.length > 0) {
          q1Groups.push({
            players: group,
            quadrant: 'Q1',
            type: 'Early',
            category: category
          });
        }
      }
    } else {
      // Normal grouping
      for (let i = 0; i < q1Players.length; i += mod) {
        const group = q1Players.slice(i, Math.min(i + mod, q1Players.length));
        if (group.length > 0) {
          q1Groups.push({
            players: group,
            quadrant: 'Q1',
            type: 'Early',
            category: category
          });
        }
      }
    }
    groups.push(...q1Groups);

    // Q3: Players from limit3+1 to players (descending order)
    // Special handling for difference=3 case
    let q3Players = sourceArray.slice(limits.limit3);
    if (difference === 3 && mod === 3) {
      // Remove last player(s) to avoid incomplete flight
      q3Players = q3Players.slice(0, q3Players.length - (q3Players.length % mod));
    }

    const q3Groups = [];
    for (let i = q3Players.length - 1; i >= 0; i -= mod) {
      const group = [];
      for (let j = 0; j < mod && (i - j) >= 0; j++) {
        group.push(q3Players[i - j]);
      }
      if (group.length === mod) { // Only add complete groups
        q3Groups.push({
          players: group.reverse(),
          quadrant: 'Q3',
          type: 'Late',
          category: category
        });
      }
    }
    groups.push(...q3Groups);

    // Q4: Players from limit2+1 to limit3 (ascending order)
    const q4Players = sourceArray.slice(limits.limit2, limits.limit3);
    for (let i = 0; i < q4Players.length; i += mod) {
      const group = q4Players.slice(i, Math.min(i + mod, q4Players.length));
      if (group.length > 0) {
        groups.push({
          players: group,
          quadrant: 'Q4',
          type: 'Late',
          category: category
        });
      }
    }

    return groups;
  }

  /**
   * Generates double tee configuration with new logic
   * @param {string} round - 'prima' or 'seconda'
   * @returns {string} HTML table content
   */
  generateDoubleTee(round) {
    const { atleti, atlete } = this.getPlayerArrays();
    const mod = parseInt(this.config.playersPerFlight);
    const players = parseInt(this.config.players);
    const proette = parseInt(this.config.proette);
    const dayNumber = round === ROUND_TYPES.SECOND ? 2 : 1;

    // Generate player groups with new logic
    const maleGroups = this.generatePlayerGroups(players, mod, atleti, 'M');
    const femaleGroups = proette > 0 ? this.generatePlayerGroups(proette, mod, atlete, 'F') : [];

    // Filter groups by type
    const maleEarlyGroups = maleGroups.filter(g => g.type === 'Early');
    const maleLateGroups = maleGroups.filter(g => g.type === 'Late');
    const femaleEarlyGroups = femaleGroups.filter(g => g.type === 'Early');
    const femaleLateGroups = femaleGroups.filter(g => g.type === 'Late');

    // Generate table based on competition type
    const gara = this.config.garaNT;
    let tableHTML = '';

    if (gara === COMPETITION_TYPES.GARA_54) {
      tableHTML = this.generate54HoleTableNew(
        maleEarlyGroups, maleLateGroups,
        femaleEarlyGroups, femaleLateGroups,
        dayNumber
      );
    } else {
      // GARA_36 or default
      tableHTML = this.generate36HoleTableNew(
        maleEarlyGroups, maleLateGroups,
        femaleEarlyGroups, femaleLateGroups,
        dayNumber
      );
    }

    // Calculate and display timing info
    const timingInfo = this.calculateTimingInfo(maleGroups, femaleGroups);
    const infoHTML = `
      <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; padding: 15px; background: #e8f5e8; border-radius: 8px;">
        <div style="text-align: center; padding: 10px; background: white; border-radius: 4px;">
          <strong style="display: block; font-size: 18px; color: #2c5530;">${timingInfo.lastEarlyTime}</strong>
          <span>Ultima Partenza Early</span>
        </div>
        <div style="text-align: center; padding: 10px; background: white; border-radius: 4px;">
          <strong style="display: block; font-size: 18px; color: #2c5530;">${timingInfo.firstLateTime}</strong>
          <span>Prima Partenza Late</span>
        </div>
        <div style="text-align: center; padding: 10px; background: white; border-radius: 4px;">
          <strong style="display: block; font-size: 18px; color: #2c5530;">${timingInfo.finishTime}</strong>
          <span>Fine Gara Stimata</span>
        </div>
      </div>
    `;

    return infoHTML + `<table>${tableHTML}</table>`;
  }

  /**
   * Calculates timing information for display
   */
  calculateTimingInfo(maleGroups, femaleGroups) {
    const startTime = this.config.startTime;
    const gap = this.config.gap;
    const roundTime = this.config.round;
    const compatto = this.config.compatto;

    // Calculate early groups count
    const maleEarlyCount = maleGroups.filter(g => g.type === 'Early').length;
    const femaleEarlyCount = femaleGroups.filter(g => g.type === 'Early').length;
    const totalEarlyFlights = Math.ceil((maleEarlyCount + femaleEarlyCount) / 2);

    // Calculate last early time
    let currentTime = startTime;
    for (let i = 0; i < totalEarlyFlights; i++) {
      currentTime = addTime(currentTime, gap);
    }
    const lastEarlyTime = currentTime;

    // Calculate first late time
    let firstLateTime = lastEarlyTime;
    if (compatto === COMPACT_TYPES.EARLY_LATE) {
      firstLateTime = addTime(firstLateTime, halfTime(roundTime));
    } else {
      firstLateTime = addTime(firstLateTime, halfTime(roundTime));
    }

    // Calculate finish time
    const totalGroups = maleGroups.length + femaleGroups.length;
    const totalFlights = Math.ceil(totalGroups / 2);
    let finishTime = startTime;
    for (let i = 0; i < totalFlights; i++) {
      finishTime = addTime(finishTime, gap);
    }
    finishTime = addTime(finishTime, roundTime);

    return {
      lastEarlyTime,
      firstLateTime,
      finishTime
    };
  }

  /**
   * NEW: Generates 54-hole table with new quadrant logic
   * Correct order: Early Male -> Early Female -> (wait crossing) -> Late Female -> Late Male
   */
  generate54HoleTableNew(maleEarlyGroups, maleLateGroups, femaleEarlyGroups, femaleLateGroups, dayNumber) {
    let html = this.generateTableHeader(true);
    let currentTime = this.config.startTime;
    const gap = this.config.gap;
    const compatto = this.config.compatto;
    const roundTime = this.config.round;
    const colors = TABLE_COLORS.teeColors;

    let maleMatchNumber = 1;
    let femaleMatchNumber = 1;
    let lastEarlyTime = '';
    let firstLateTime = '';

    if (dayNumber === 1) {
      // Day 1: Standard order
      // 1. EARLY MALE: Q1->Tee1, Q2->Tee10
      let maleEarlyQ1 = maleEarlyGroups.filter(g => g.quadrant === 'Q1');
      let maleEarlyQ2 = maleEarlyGroups.filter(g => g.quadrant === 'Q2');

      html += this.buildGroupTableRows(
        maleEarlyQ1,
        maleEarlyQ2,
        TABLE_COLORS.men,
        colors.orange,
        colors.lightGreen,
        currentTime,
        gap,
        'M',
        maleMatchNumber
      );

      // Calculate time correctly
      const maleEarlyMatches = Math.max(maleEarlyQ1.length, maleEarlyQ2.length);
      for (let i = 0; i < maleEarlyMatches; i++) {
        currentTime = addTime(currentTime, gap);
      }
      currentTime = addTime(currentTime, '00:10');
      maleMatchNumber += maleEarlyQ1.length + maleEarlyQ2.length;

      // 2. EARLY FEMALE: Q1->Tee1, Q2->Tee10
      if (femaleEarlyGroups.length > 0) {
        let femaleEarlyQ1 = femaleEarlyGroups.filter(g => g.quadrant === 'Q1');
        let femaleEarlyQ2 = femaleEarlyGroups.filter(g => g.quadrant === 'Q2');

        html += this.buildGroupTableRows(
          femaleEarlyQ1,
          femaleEarlyQ2,
          TABLE_COLORS.women,
          'transparent',
          'transparent',
          currentTime,
          gap,
          'F',
          femaleMatchNumber
        );

        const femaleEarlyMatches = Math.max(femaleEarlyQ1.length, femaleEarlyQ2.length);
        for (let i = 0; i < femaleEarlyMatches; i++) {
          currentTime = addTime(currentTime, gap);
        }
        femaleMatchNumber += femaleEarlyQ1.length + femaleEarlyQ2.length;
      }

      lastEarlyTime = currentTime;

      // Add spacing and wait for crossing
      html += '<tr><td colspan="20" class="py-2">&nbsp;</td></tr>';

      // Wait for early players to pass through
      if (compatto === COMPACT_TYPES.EARLY_LATE) {
        currentTime = addTime(currentTime, halfTime(roundTime));
      } else {
        // Standard wait time for crossing
        currentTime = addTime(lastEarlyTime, halfTime(roundTime));
      }

      firstLateTime = currentTime;

      // 3. LATE FEMALE: Q3->Tee1, Q4->Tee10
      if (femaleLateGroups.length > 0) {
        let femaleLateQ3 = femaleLateGroups.filter(g => g.quadrant === 'Q3');
        let femaleLateQ4 = femaleLateGroups.filter(g => g.quadrant === 'Q4');

        html += this.buildGroupTableRows(
          femaleLateQ3,
          femaleLateQ4,
          TABLE_COLORS.women,
          'transparent',
          'transparent',
          currentTime,
          gap,
          'F',
          femaleMatchNumber
        );

        const femaleLateMatches = Math.max(femaleLateQ3.length, femaleLateQ4.length);
        for (let i = 0; i < femaleLateMatches; i++) {
          currentTime = addTime(currentTime, gap);
        }
        currentTime = addTime(currentTime, '00:10');
        femaleMatchNumber += femaleLateQ3.length + femaleLateQ4.length;
      }

      // 4. LATE MALE: Q3->Tee1, Q4->Tee10
      let maleLateQ3 = maleLateGroups.filter(g => g.quadrant === 'Q3');
      let maleLateQ4 = maleLateGroups.filter(g => g.quadrant === 'Q4');

      html += this.buildGroupTableRows(
        maleLateQ3,
        maleLateQ4,
        TABLE_COLORS.men,
        colors.lightGray,
        colors.yellow,
        currentTime,
        gap,
        'M',
        maleMatchNumber
      );

} else {
  // Day 2: Rotated order
  // 1. Men Late groups (Q3,Q4) go Early: Q4->Tee1, Q3->Tee10
  let maleLateQ3 = maleLateGroups.filter(g => g.quadrant === 'Q3');
  let maleLateQ4 = maleLateGroups.filter(g => g.quadrant === 'Q4');

  html += this.buildGroupTableRows(
    maleLateQ4,  // Q4 to Tee1
    maleLateQ3,  // Q3 to Tee10
    TABLE_COLORS.men,
    colors.orange,
    colors.lightGreen,
    currentTime,
    gap,
    'M',
    maleMatchNumber
  );

  const maleLateMatches = Math.max(maleLateQ3.length, maleLateQ4.length);
  for (let i = 0; i < maleLateMatches; i++) {
    currentTime = addTime(currentTime, gap);
  }
  currentTime = addTime(currentTime, '00:10');
  maleMatchNumber += maleLateQ3.length + maleLateQ4.length;

  // 2. Female Late groups (Q3,Q4) go Early: Q4->Tee1, Q3->Tee10
  if (femaleLateGroups.length > 0) {
    let femaleLateQ3 = femaleLateGroups.filter(g => g.quadrant === 'Q3');
    let femaleLateQ4 = femaleLateGroups.filter(g => g.quadrant === 'Q4');

    html += this.buildGroupTableRows(
      femaleLateQ4,  // Q4 to Tee1
      femaleLateQ3,  // Q3 to Tee10
      TABLE_COLORS.women,
      'transparent',
      'transparent',
      currentTime,
      gap,
      'F',
      femaleMatchNumber
    );

    const femaleLateMatches = Math.max(femaleLateQ3.length, femaleLateQ4.length);
    for (let i = 0; i < femaleLateMatches; i++) {
      currentTime = addTime(currentTime, gap);
    }
    femaleMatchNumber += femaleLateQ3.length + femaleLateQ4.length;
  }

  lastEarlyTime = currentTime;

  // Add spacing and wait
  html += '<tr><td colspan="20" class="py-2">&nbsp;</td></tr>';

  if (compatto === COMPACT_TYPES.EARLY_LATE) {
    currentTime = addTime(currentTime, halfTime(roundTime));
  } else {
    currentTime = addTime(lastEarlyTime, halfTime(roundTime));
  }

  firstLateTime = currentTime;

  // 3. Female Early groups (Q1,Q2) go Late: Q2->Tee1, Q1->Tee10
  if (femaleEarlyGroups.length > 0) {
    let femaleEarlyQ1 = femaleEarlyGroups.filter(g => g.quadrant === 'Q1');
    let femaleEarlyQ2 = femaleEarlyGroups.filter(g => g.quadrant === 'Q2');

    html += this.buildGroupTableRows(
      femaleEarlyQ2,  // Q2 to Tee1
      femaleEarlyQ1,  // Q1 to Tee10
      TABLE_COLORS.women,
      'transparent',
      'transparent',
      currentTime,
      gap,
      'F',
      femaleMatchNumber
    );

    const femaleEarlyMatches = Math.max(femaleEarlyQ1.length, femaleEarlyQ2.length);
    for (let i = 0; i < femaleEarlyMatches; i++) {
      currentTime = addTime(currentTime, gap);
    }
    currentTime = addTime(currentTime, '00:10');
    femaleMatchNumber += femaleEarlyQ1.length + femaleEarlyQ2.length;
  }

  // 4. Male Early groups (Q1,Q2) go Late: Q2->Tee1, Q1->Tee10
  let maleEarlyQ1 = maleEarlyGroups.filter(g => g.quadrant === 'Q1');
  let maleEarlyQ2 = maleEarlyGroups.filter(g => g.quadrant === 'Q2');

  html += this.buildGroupTableRows(
    maleEarlyQ2,  // Q2 to Tee1
    maleEarlyQ1,  // Q1 to Tee10
    TABLE_COLORS.men,
    colors.lightGray,
    colors.yellow,
    currentTime,
    gap,
    'M',
    maleMatchNumber
  );
}
    // Store timing info for display
    this.lastEarlyTime = lastEarlyTime;
    this.firstLateTime = firstLateTime;

    return html + '</tbody>';
  }

  /**
   * NEW: Generates 36-hole table with new quadrant logic
   */
  generate36HoleTableNew(maleEarlyGroups, maleLateGroups, femaleEarlyGroups, femaleLateGroups, dayNumber) {
    let html = this.generateTableHeader(true);
    let currentTime = this.config.startTime;
    const gap = this.config.gap;
    const compatto = this.config.compatto;
    const roundTime = this.config.round;
    const colors = TABLE_COLORS.teeColors;

    let maleMatchNumber = 1;
    let femaleMatchNumber = 1;
    let lastEarlyTime = '';
    let firstLateTime = '';

    // Similar logic to 54-hole but with different arrangement
    // Implementation follows the same pattern as generate54HoleTableNew
    // but with different quadrant assignments for 36-hole competition

    if (dayNumber === 1) {
      // Day 1: Standard order
      // 1. EARLY MALE: Q1->Tee1, Q2->Tee10
      let maleEarlyQ1 = maleEarlyGroups.filter(g => g.quadrant === 'Q1');
      let maleEarlyQ2 = maleEarlyGroups.filter(g => g.quadrant === 'Q2');

      html += this.buildGroupTableRows(
        maleEarlyQ1,
        maleEarlyQ2,
        TABLE_COLORS.men,
        colors.orange,
        colors.lightGreen,
        currentTime,
        gap,
        'M',
        maleMatchNumber
      );

      // Calculate time correctly
      const maleEarlyMatches = Math.max(maleEarlyQ1.length, maleEarlyQ2.length);
      for (let i = 0; i < maleEarlyMatches; i++) {
        currentTime = addTime(currentTime, gap);
      }
      currentTime = addTime(currentTime, '00:10');
      maleMatchNumber += maleEarlyQ1.length + maleEarlyQ2.length;

      // 2. EARLY FEMALE: Q1->Tee1, Q2->Tee10
      if (femaleEarlyGroups.length > 0) {
        let femaleEarlyQ1 = femaleEarlyGroups.filter(g => g.quadrant === 'Q1');
        let femaleEarlyQ2 = femaleEarlyGroups.filter(g => g.quadrant === 'Q2');

        html += this.buildGroupTableRows(
          femaleEarlyQ1,
          femaleEarlyQ2,
          TABLE_COLORS.women,
          'transparent',
          'transparent',
          currentTime,
          gap,
          'F',
          femaleMatchNumber
        );

        const femaleEarlyMatches = Math.max(femaleEarlyQ1.length, femaleEarlyQ2.length);
        for (let i = 0; i < femaleEarlyMatches; i++) {
          currentTime = addTime(currentTime, gap);
        }
        femaleMatchNumber += femaleEarlyQ1.length + femaleEarlyQ2.length;
      }

      lastEarlyTime = currentTime;

      // Add spacing and wait for crossing
      html += '<tr><td colspan="20" class="py-2">&nbsp;</td></tr>';

      // Wait for early players to pass through
      if (compatto === COMPACT_TYPES.EARLY_LATE) {
        currentTime = addTime(currentTime, halfTime(roundTime));
      } else {
        // Standard wait time for crossing
        currentTime = addTime(lastEarlyTime, halfTime(roundTime));
      }

      firstLateTime = currentTime;

      // 3. LATE FEMALE: Q3->Tee1, Q4->Tee10
      if (femaleLateGroups.length > 0) {
        let femaleLateQ3 = femaleLateGroups.filter(g => g.quadrant === 'Q3');
        let femaleLateQ4 = femaleLateGroups.filter(g => g.quadrant === 'Q4');

        html += this.buildGroupTableRows(
          femaleLateQ3,
          femaleLateQ4,
          TABLE_COLORS.women,
          'transparent',
          'transparent',
          currentTime,
          gap,
          'F',
          femaleMatchNumber
        );

        const femaleLateMatches = Math.max(femaleLateQ3.length, femaleLateQ4.length);
        for (let i = 0; i < femaleLateMatches; i++) {
          currentTime = addTime(currentTime, gap);
        }
        currentTime = addTime(currentTime, '00:10');
        femaleMatchNumber += femaleLateQ3.length + femaleLateQ4.length;
      }

      // 4. LATE MALE: Q3->Tee1, Q4->Tee10
      let maleLateQ3 = maleLateGroups.filter(g => g.quadrant === 'Q3');
      let maleLateQ4 = maleLateGroups.filter(g => g.quadrant === 'Q4');

      html += this.buildGroupTableRows(
        maleLateQ3,
        maleLateQ4,
        TABLE_COLORS.men,
        colors.lightGray,
        colors.yellow,
        currentTime,
        gap,
        'M',
        maleMatchNumber
      );

} else {
  // Day 2: Rotated order
  // 1. Men Late groups (Q3,Q4) go Early: Q4->Tee1, Q3->Tee10
  let maleLateQ3 = maleLateGroups.filter(g => g.quadrant === 'Q3');
  let maleLateQ4 = maleLateGroups.filter(g => g.quadrant === 'Q4');

  html += this.buildGroupTableRows(
    maleLateQ4,  // Q4 to Tee1
    maleLateQ3,  // Q3 to Tee10
    TABLE_COLORS.men,
    colors.orange,
    colors.lightGreen,
    currentTime,
    gap,
    'M',
    maleMatchNumber
  );

  const maleLateMatches = Math.max(maleLateQ3.length, maleLateQ4.length);
  for (let i = 0; i < maleLateMatches; i++) {
    currentTime = addTime(currentTime, gap);
  }
  currentTime = addTime(currentTime, '00:10');
  maleMatchNumber += maleLateQ3.length + maleLateQ4.length;

  // 2. Female Late groups (Q3,Q4) go Early: Q4->Tee1, Q3->Tee10
  if (femaleLateGroups.length > 0) {
    let femaleLateQ3 = femaleLateGroups.filter(g => g.quadrant === 'Q3');
    let femaleLateQ4 = femaleLateGroups.filter(g => g.quadrant === 'Q4');

    html += this.buildGroupTableRows(
      femaleLateQ4,  // Q4 to Tee1
      femaleLateQ3,  // Q3 to Tee10
      TABLE_COLORS.women,
      'transparent',
      'transparent',
      currentTime,
      gap,
      'F',
      femaleMatchNumber
    );

    const femaleLateMatches = Math.max(femaleLateQ3.length, femaleLateQ4.length);
    for (let i = 0; i < femaleLateMatches; i++) {
      currentTime = addTime(currentTime, gap);
    }
    femaleMatchNumber += femaleLateQ3.length + femaleLateQ4.length;
  }

  lastEarlyTime = currentTime;

  // Add spacing and wait
  html += '<tr><td colspan="20" class="py-2">&nbsp;</td></tr>';

  if (compatto === COMPACT_TYPES.EARLY_LATE) {
    currentTime = addTime(currentTime, halfTime(roundTime));
  } else {
    currentTime = addTime(lastEarlyTime, halfTime(roundTime));
  }

  firstLateTime = currentTime;

  // 3. Female Early groups (Q1,Q2) go Late: Q2->Tee1, Q1->Tee10
  if (femaleEarlyGroups.length > 0) {
    let femaleEarlyQ1 = femaleEarlyGroups.filter(g => g.quadrant === 'Q1');
    let femaleEarlyQ2 = femaleEarlyGroups.filter(g => g.quadrant === 'Q2');

    html += this.buildGroupTableRows(
      femaleEarlyQ2,  // Q2 to Tee1
      femaleEarlyQ1,  // Q1 to Tee10
      TABLE_COLORS.women,
      'transparent',
      'transparent',
      currentTime,
      gap,
      'F',
      femaleMatchNumber
    );

    const femaleEarlyMatches = Math.max(femaleEarlyQ1.length, femaleEarlyQ2.length);
    for (let i = 0; i < femaleEarlyMatches; i++) {
      currentTime = addTime(currentTime, gap);
    }
    currentTime = addTime(currentTime, '00:10');
    femaleMatchNumber += femaleEarlyQ1.length + femaleEarlyQ2.length;
  }

  // 4. Male Early groups (Q1,Q2) go Late: Q2->Tee1, Q1->Tee10
  let maleEarlyQ1 = maleEarlyGroups.filter(g => g.quadrant === 'Q1');
  let maleEarlyQ2 = maleEarlyGroups.filter(g => g.quadrant === 'Q2');

  html += this.buildGroupTableRows(
    maleEarlyQ2,  // Q2 to Tee1
    maleEarlyQ1,  // Q1 to Tee10
    TABLE_COLORS.men,
    colors.lightGray,
    colors.yellow,
    currentTime,
    gap,
    'M',
    maleMatchNumber
  );
}
    // Store timing info for display
    this.lastEarlyTime = lastEarlyTime;
    this.firstLateTime = firstLateTime;

    return html + '</tbody>';
  }

  /**
   * NEW: Helper to build table rows from groups
   */
  buildGroupTableRows(leftGroups, rightGroups, color, leftBg, rightBg, startTime, gap, category, startNumber) {
    let html = '';
    let currentTime = startTime;
    let matchNumber = startNumber;
    const mod = parseInt(this.config.playersPerFlight);

    const maxGroups = Math.max(leftGroups.length, rightGroups.length);

    for (let i = 0; i < maxGroups; i++) {
      html += '<tr>';

      // Left side
      if (leftGroups[i]) {
        html += `<td class="text-center px-2 py-1 border border-gray-300 font-medium" style="background-color:${leftBg}">${matchNumber++}</td>`;
        html += '<td class="text-center px-2 py-1 border border-gray-300 font-medium">1</td>';

        for (let j = 0; j < mod; j++) {
          const player = leftGroups[i].players[j] || '';
          html += `<td class="text-center px-2 py-1 border border-gray-300" style="color: ${color}">${player}</td>`;
        }
      } else {
        html += `<td colspan="${mod + 2}" class="text-center px-2 py-1 border border-gray-300"></td>`;
      }

      // Time
      html += `<td class="text-center px-2 py-1 border border-gray-300 font-medium">${currentTime}</td>`;

      // Right side
      if (rightGroups[i]) {
        for (let j = 0; j < mod; j++) {
          const player = rightGroups[i].players[j] || '';
          html += `<td class="text-center px-2 py-1 border border-gray-300" style="color: ${color}">${player}</td>`;
        }

        html += '<td class="text-center px-2 py-1 border border-gray-300 font-medium">10</td>';
        html += `<td class="text-center px-2 py-1 border border-gray-300 font-medium" style="background-color:${rightBg}">${matchNumber++}</td>`;
      } else {
        html += `<td colspan="${mod + 2}" class="text-center px-2 py-1 border border-gray-300"></td>`;
      }

      html += '</tr>';
      currentTime = addTime(currentTime, gap);
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

  /**
   * Generates single tee configuration
   * @param {string} round - 'prima' or 'seconda'
   * @returns {string} HTML table content
   */
  generateSingleTee(round) {
    // Keep existing implementation for single tee
    // This is less affected by the new quadrant logic
    const { atleti, atlete } = this.getPlayerArrays();
    const mod = parseInt(this.config.playersPerFlight);
    const players = parseInt(this.config.players);
    const proette = parseInt(this.config.proette);

    // Use new logic for groups generation
    const maleGroups = this.generatePlayerGroups(players, mod, atleti, 'M');
    const femaleGroups = proette > 0 ? this.generatePlayerGroups(proette, mod, atlete, 'F') : [];

    // Build single tee table
    let tableHTML = this.generateTableHeader(false);
    let currentTime = this.config.startTime;
    const gap = this.config.gap;

    // Concatenate all groups in appropriate order
    const allGroups = round === ROUND_TYPES.FIRST
      ? [...femaleGroups, ...maleGroups]
      : [...maleGroups.reverse(), ...femaleGroups.reverse()];

    allGroups.forEach((group, index) => {
      tableHTML += '<tr>';
      tableHTML += `<td class="text-center px-2 py-1 border border-gray-300 font-medium">${index + 1}</td>`;
      tableHTML += '<td class="text-center px-2 py-1 border border-gray-300 font-medium">1</td>';

      for (let j = 0; j < mod; j++) {
        const player = group.players[j] || '';
        const style = group.category === 'F' ? 'style="font-style:italic; color:red"' : '';
        tableHTML += `<td class="text-center px-2 py-1 border border-gray-300" ${style}>${player}</td>`;
      }

      tableHTML += `<td class="text-center px-2 py-1 border border-gray-300 font-medium">${currentTime}</td>`;
      tableHTML += '</tr>';

      currentTime = addTime(currentTime, gap);
    });

    tableHTML += '</tbody>';
    return tableHTML;
  }
}
