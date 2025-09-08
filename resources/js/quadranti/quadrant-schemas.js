/**
 * Quadrant Schemas Configuration
 * Definisce gli schemi di distribuzione dei giocatori nei quadranti
 * per diversi tipi di gara e giorni
 */

/**
 * Schema definition structure:
 * - arrayIndex: quale array utilizzare (1-4)
 * - inverted: se l'array deve essere invertito (dal basso verso l'alto)
 * - invertFlights: se le triplette devono essere invertite (3-2-1 invece di 1-2-3)
 * - gender: 'MEN' o 'WOMEN'
 */

export const QUADRANT_SCHEMAS = {
  // 72 buche - 1° giorno
  '72_day1': {
    Q1: [
      { arrayIndex: 2, gender: 'MEN', inverted: false, invertFlights: false },
      { arrayIndex: 2, gender: 'WOMEN', inverted: false, invertFlights: false }
    ],
    Q2: [
      { arrayIndex: 1, gender: 'MEN', inverted: true, invertFlights: false },
      { arrayIndex: 1, gender: 'WOMEN', inverted: true, invertFlights: false }
    ],
    Q3: [
      { arrayIndex: 3, gender: 'WOMEN', inverted: false, invertFlights: false },
      { arrayIndex: 4, gender: 'MEN', inverted: false, invertFlights: false }
    ],
    Q4: [
      { arrayIndex: 4, gender: 'WOMEN', inverted: false, invertFlights: false },
      { arrayIndex: 3, gender: 'MEN', inverted: false, invertFlights: false }
    ]
  },

  // 72 buche - 2° giorno
  '72_day2': {
    Q1: [
      { arrayIndex: 3, gender: 'MEN', inverted: false, invertFlights: false },
      { arrayIndex: 3, gender: 'WOMEN', inverted: false, invertFlights: false }
    ],
    Q2: [
      { arrayIndex: 4, gender: 'MEN', inverted: false, invertFlights: false },
      { arrayIndex: 4, gender: 'WOMEN', inverted: false, invertFlights: false }
    ],
    Q3: [
      { arrayIndex: 1, gender: 'WOMEN', inverted: true, invertFlights: false },
      { arrayIndex: 1, gender: 'MEN', inverted: true, invertFlights: false }
    ],
    Q4: [
      { arrayIndex: 2, gender: 'WOMEN', inverted: false, invertFlights: false },
      { arrayIndex: 2, gender: 'MEN', inverted: false, invertFlights: false }
    ]
  },

  // 54 buche - 1° giorno
  '54_day1': {
    Q1: [
      { arrayIndex: 2, gender: 'MEN', inverted: false, invertFlights: false },
      { arrayIndex: 2, gender: 'WOMEN', inverted: false, invertFlights: false }
    ],
    Q2: [
      { arrayIndex: 1, gender: 'MEN', inverted: true, invertFlights: false },
      { arrayIndex: 1, gender: 'WOMEN', inverted: true, invertFlights: false }
    ],
    Q3: [
      { arrayIndex: 4, gender: 'WOMEN', inverted: true, invertFlights: false },
      { arrayIndex: 4, gender: 'MEN', inverted: true, invertFlights: false }
    ],
    Q4: [
      { arrayIndex: 3, gender: 'WOMEN', inverted: false, invertFlights: false },
      { arrayIndex: 3, gender: 'MEN', inverted: false, invertFlights: false }
    ]
  },

  // 54 buche - 2° giorno
  '54_day2': {
    Q1: [
      { arrayIndex: 3, gender: 'MEN', inverted: false, invertFlights: false },
      { arrayIndex: 3, gender: 'WOMEN', inverted: false, invertFlights: false }
    ],
    Q2: [
      { arrayIndex: 4, gender: 'MEN', inverted: false, invertFlights: false },
      { arrayIndex: 4, gender: 'WOMEN', inverted: false, invertFlights: false }
    ],
    Q3: [
      { arrayIndex: 1, gender: 'WOMEN', inverted: true, invertFlights: false },
      { arrayIndex: 1, gender: 'MEN', inverted: true, invertFlights: false }
    ],
    Q4: [
      { arrayIndex: 2, gender: 'WOMEN', inverted: false, invertFlights: false },
      { arrayIndex: 2, gender: 'MEN', inverted: false, invertFlights: false }
    ]
  },

  // 36 buche - 1° giorno
  '36_day1': {
    Q1: [
      { arrayIndex: 2, gender: 'MEN', inverted: false, invertFlights: false },
      { arrayIndex: 2, gender: 'WOMEN', inverted: false, invertFlights: false }
    ],
    Q2: [
      { arrayIndex: 1, gender: 'MEN', inverted: true, invertFlights: false },
      { arrayIndex: 1, gender: 'WOMEN', inverted: true, invertFlights: false }
    ],
    Q3: [
      { arrayIndex: 4, gender: 'WOMEN', inverted: true, invertFlights: false },
      { arrayIndex: 4, gender: 'MEN', inverted: true, invertFlights: false }
    ],
    Q4: [
      { arrayIndex: 3, gender: 'WOMEN', inverted: false, invertFlights: false },
      { arrayIndex: 3, gender: 'MEN', inverted: false, invertFlights: false }
    ]
  },

  // 36 buche - 2° giorno (SPECIALE: triplette invertite)
  '36_day2': {
    Q1: [
      { arrayIndex: 3, gender: 'MEN', inverted: true, invertFlights: true },
      { arrayIndex: 3, gender: 'WOMEN', inverted: true, invertFlights: true }
    ],
    Q2: [
      { arrayIndex: 4, gender: 'MEN', inverted: true, invertFlights: true },
      { arrayIndex: 4, gender: 'WOMEN', inverted: true, invertFlights: true }
    ],
    Q3: [
      { arrayIndex: 1, gender: 'WOMEN', inverted: true, invertFlights: true },
      { arrayIndex: 1, gender: 'MEN', inverted: true, invertFlights: true }
    ],
    Q4: [
      { arrayIndex: 2, gender: 'WOMEN', inverted: false, invertFlights: true },
      { arrayIndex: 2, gender: 'MEN', inverted: false, invertFlights: true }
    ]
  }
};

/**
 * Helper function to get schema key based on competition type, day and special 36 hole format
 * @param {string} competitionType - 'Gara 54 buche' or 'Gara 36 buche'
 * @param {string} day - 'prima' or 'seconda'
 * @param {boolean} isNormale36 - true if 36 holes is 'Normale' format (uses 72 schema)
 * @returns {string} Schema key
 */
export function getSchemaKey(competitionType, day, isNormale36 = false) {
  let holes;
  
  if (competitionType === 'Gara 54 buche') {
    // Per gare 54 buche:
    // - Se prima giornata: usa schema 72 
    // - Se seconda giornata: usa schema 54
    holes = day === 'prima' ? '72' : '54';
  } else if (competitionType === 'Gara 36 buche') {
    // Per gare 36 buche:
    // - Se formato 'Normale': usa schema 72
    // - Altrimenti: usa schema 36
    holes = isNormale36 ? '72' : '36';
  } else {
    // Default to 72 for any other competition type
    holes = '72';
  }
  
  const dayNum = day === 'prima' ? '1' : '2';
  return `${holes}_day${dayNum}`;
}

/**
 * Calcola i range degli array in base al numero totale di giocatori
 * @param {number} totalPlayers - Numero totale di giocatori
 * @returns {Object} Oggetto con i range per ogni array
 */
export function calculateArrayRanges(totalPlayers) {
  const quarterSize = Math.floor(totalPlayers / 4);
  const remainder = totalPlayers % 4;
  
  // Distribuisci il resto equamente tra i primi array
  const sizes = [quarterSize, quarterSize, quarterSize, quarterSize];
  for (let i = 0; i < remainder; i++) {
    sizes[i]++;
  }
  
  // Calcola i range
  let start = 1;
  const ranges = {};
  
  for (let i = 1; i <= 4; i++) {
    const end = start + sizes[i - 1] - 1;
    ranges[`array${i}`] = { start, end };
    start = end + 1;
  }
  
  return ranges;
}

/**
 * Inverte l'ordine dei giocatori all'interno di ogni flight
 * @param {Array} flights - Array di flights (array di array)
 * @returns {Array} Flights con ordine interno invertito
 */
export function invertFlightOrder(flights) {
  return flights.map(flight => [...flight].reverse());
}
