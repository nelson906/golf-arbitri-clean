import { describe, it, expect, beforeEach } from 'vitest';
import { QuadrantiLogic } from './quadranti-logic.js';
import { DEFAULT_CONFIG } from './config.js';

// QuadrantiLogic richiede jQuery solo per metodi DOM (initializeDatepicker, fetchEphemerisData).
// I metodi di pura logica (bilanciaQuadranti, limitiQuadranti, remapQuadrant,
// generatePlayerGroups, getPlayerArrays) non usano jQuery e sono testabili direttamente.

function makeLogic(overrides = {}) {
  return new QuadrantiLogic({ ...DEFAULT_CONFIG, ...overrides });
}

// ─── bilanciaQuadranti ───────────────────────────────────────────────────────
describe('bilanciaQuadranti', () => {
  let logic;
  beforeEach(() => { logic = makeLogic(); });

  it('144 giocatori a 3 → 48 voli totali distribuiti in 4 quadranti', () => {
    const { Q1, Q2, Q3, Q4 } = logic.bilanciaQuadranti(144, 3);
    expect(Q1 + Q2 + Q3 + Q4).toBe(48);
    expect(Q1).toBe(Q2);
    expect(Q3).toBe(Q4);
  });

  it('distribuzione bilanciata: nessun quadrante negativo', () => {
    [72, 90, 108, 120, 132, 144].forEach(n => {
      const q = logic.bilanciaQuadranti(n, 3);
      expect(q.Q1).toBeGreaterThan(0);
      expect(q.Q2).toBeGreaterThan(0);
      expect(q.Q3).toBeGreaterThan(0);
      expect(q.Q4).toBeGreaterThan(0);
    });
  });

  it('total voli = ceil(players / mod)', () => {
    [[100, 3], [80, 4], [144, 3]].forEach(([n, mod]) => {
      const { Q1, Q2, Q3, Q4 } = logic.bilanciaQuadranti(n, mod);
      expect(Q1 + Q2 + Q3 + Q4).toBe(Math.ceil(n / mod));
    });
  });

  it('mod=4 funziona senza errori', () => {
    expect(() => logic.bilanciaQuadranti(80, 4)).not.toThrow();
  });
});

// ─── limitiQuadranti ─────────────────────────────────────────────────────────
describe('limitiQuadranti', () => {
  let logic;
  beforeEach(() => { logic = makeLogic(); });

  it('restituisce struttura corretta', () => {
    const result = logic.limitiQuadranti(144, 3);
    expect(result).toHaveProperty('limit1');
    expect(result).toHaveProperty('limit2');
    expect(result).toHaveProperty('limit3');
    expect(result).toHaveProperty('difference');
    expect(result).toHaveProperty('playersPerQuadrant');
  });

  it('somma players per quadrante uguale al totale (quando divisibile)', () => {
    const result = logic.limitiQuadranti(144, 3);
    const { Q1, Q2, Q3, Q4 } = result.playersPerQuadrant;
    // Il difference viene sottratto da Q1 — verifichiamo con tolleranza
    expect(Q1 + Q2 + Q3 + Q4 + result.difference).toBe(144);
  });

  it('limit3 >= limit2 (Q3 parte dopo Q1)', () => {
    const result = logic.limitiQuadranti(144, 3);
    expect(result.limit3).toBeGreaterThanOrEqual(result.limit2);
  });

  it('difference non negativo', () => {
    [72, 100, 120, 144].forEach(n => {
      const result = logic.limitiQuadranti(n, 3);
      expect(result.difference).toBeGreaterThanOrEqual(0);
    });
  });
});

// ─── remapQuadrant ───────────────────────────────────────────────────────────
describe('remapQuadrant', () => {
  let logic;
  beforeEach(() => { logic = makeLogic(); });

  it('giorno 1: quadrante invariato', () => {
    ['Q1', 'Q2', 'Q3', 'Q4'].forEach(q => {
      expect(logic.remapQuadrant(q, 1)).toBe(q);
    });
  });

  it('giorno 2: rotazione Q1→Q4, Q2→Q3, Q3→Q1, Q4→Q2', () => {
    expect(logic.remapQuadrant('Q1', 2)).toBe('Q4');
    expect(logic.remapQuadrant('Q2', 2)).toBe('Q3');
    expect(logic.remapQuadrant('Q3', 2)).toBe('Q1');
    expect(logic.remapQuadrant('Q4', 2)).toBe('Q2');
  });

  it('4 rotazioni successive giorno 2 → ritorna al quadrante originale (ciclo a 4)', () => {
    // La mappatura è un ciclo: Q1→Q4→Q2→Q3→Q1, non una doppia inversione
    ['Q1', 'Q2', 'Q3', 'Q4'].forEach(q => {
      let current = q;
      for (let i = 0; i < 4; i++) {
        current = logic.remapQuadrant(current, 2);
      }
      expect(current).toBe(q);
    });
  });

  it('quadrante sconosciuto viene restituito invariato', () => {
    expect(logic.remapQuadrant('QX', 2)).toBe('QX');
  });
});

// ─── generatePlayerGroups ────────────────────────────────────────────────────
describe('generatePlayerGroups', () => {
  let logic;
  beforeEach(() => { logic = makeLogic(); });

  it('genera gruppi per 144 giocatori a 3', () => {
    const players = Array.from({ length: 144 }, (_, i) => i + 1);
    const groups = logic.generatePlayerGroups(144, 3, players, 'M');

    // Ogni gruppo ha 1-3 giocatori
    groups.forEach(g => {
      expect(g.players.length).toBeGreaterThan(0);
      expect(g.players.length).toBeLessThanOrEqual(3);
    });
  });

  it('i gruppi coprono tutti i giocatori senza duplicati', () => {
    const players = Array.from({ length: 144 }, (_, i) => i + 1);
    const groups = logic.generatePlayerGroups(144, 3, players, 'M');
    const allPlayers = groups.flatMap(g => g.players);

    expect(allPlayers.length).toBe(144);
    expect(new Set(allPlayers).size).toBe(144);
  });

  it('ogni gruppo ha quadrante valido (Q1-Q4)', () => {
    const players = Array.from({ length: 72 }, (_, i) => i + 1);
    const groups = logic.generatePlayerGroups(72, 3, players, 'F');
    const validQuadrants = new Set(['Q1', 'Q2', 'Q3', 'Q4']);
    groups.forEach(g => {
      expect(validQuadrants.has(g.quadrant)).toBe(true);
    });
  });

  it('funziona con numero non multiplo di 3 (es. 100)', () => {
    const players = Array.from({ length: 100 }, (_, i) => i + 1);
    expect(() => logic.generatePlayerGroups(100, 3, players, 'M')).not.toThrow();
    const groups = logic.generatePlayerGroups(100, 3, players, 'M');
    const count = groups.flatMap(g => g.players).length;
    expect(count).toBe(100);
  });
});

// ─── updateConfig ────────────────────────────────────────────────────────────
describe('updateConfig', () => {
  it('aggiorna solo le chiavi specificate', () => {
    const logic = makeLogic({ players: 100 });
    logic.updateConfig({ players: 120 });
    expect(logic.config.players).toBe(120);
    expect(logic.config.startTime).toBe(DEFAULT_CONFIG.startTime);
  });
});
