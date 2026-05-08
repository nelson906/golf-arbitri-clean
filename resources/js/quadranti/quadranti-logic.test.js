import { describe, it, expect, beforeEach } from 'vitest';
import { QuadrantiLogic, mergeFedergolfResponses, normalizeGaraTitle } from './quadranti-logic.js';
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

  it('144 giocatori a 3 → 48 voli totali distribuiti in 4 quadranti uguali', () => {
    const { Q1, Q2, Q3, Q4 } = logic.bilanciaQuadranti(144, 3);
    expect(Q1 + Q2 + Q3 + Q4).toBe(48);
    expect(Q1).toBe(Q2);
    expect(Q3).toBe(Q4);
  });

  it('102 giocatori a 3 senza constraint → remainder naturale a Early (Q1/Q2)', () => {
    const { Q1, Q2, Q3, Q4 } = logic.bilanciaQuadranti(102, 3);
    // distribuzione naturale: il resto va prima a Q1, poi Q2
    expect(Q1).toBeGreaterThanOrEqual(Q3);
    expect(Q1 + Q2 + Q3 + Q4).toBe(34);
  });

  it('constraint maxEarlySlots=8 → sposta overflow da Early a Late', () => {
    // Senza constraint: Q1=9, Q2=9. Con maxEarlySlots=8 → Q1=Q2=8, Q3=Q4=9
    const { Q1, Q2, Q3, Q4 } = logic.bilanciaQuadranti(102, 3, 8);
    expect(Q1).toBe(8);
    expect(Q2).toBe(8);
    expect(Q3).toBe(9);
    expect(Q4).toBe(9);
    expect(Q1 + Q2 + Q3 + Q4).toBe(34);
  });

  it('constraint non attivato se già rispettato', () => {
    // 42 donne, 14 voli, Q1=Q2=4 → con maxEarlySlots=8 nessun aggiustamento
    const { Q1, Q2, Q3, Q4 } = logic.bilanciaQuadranti(42, 3, 8);
    expect(Q1).toBe(4); // invariato, già ≤ 8
    expect(Q1 + Q2 + Q3 + Q4).toBe(14);
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

  it('playerIndices tracciano la posizione originale in sourceArray', () => {
    // Usiamo nomi distinti così il match indice→valore è univoco e verificabile.
    const sourceArray = Array.from({ length: 60 }, (_, i) => `P${i}`);
    const groups = logic.generatePlayerGroups(60, 3, sourceArray, 'M');

    groups.forEach(g => {
      expect(g.playerIndices).toBeDefined();
      expect(g.playerIndices.length).toBe(g.players.length);
      g.playerIndices.forEach((idx, j) => {
        expect(idx).toBeGreaterThanOrEqual(0);
        expect(idx).toBeLessThan(sourceArray.length);
        expect(sourceArray[idx]).toBe(g.players[j]);
      });
    });

    // Tutti gli indici 0..59 devono comparire esattamente una volta in totale.
    const allIdx = groups.flatMap(g => g.playerIndices);
    expect(allIdx.length).toBe(60);
    expect(new Set(allIdx).size).toBe(60);
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

// ─── REGRESSIONE: bilanciamento Early ≈ Late (differenza max 1) ──────────────
// Vincolo corretto: Early e Late devono essere bilanciati (|Early-Late| ≤ 1).
// Il vincolo fisso di 12 non bastava: per configurazioni piccole (es. 64M+17F)
// non scattava mai. Il fix usa targetEarly = ceil(totalOrari/2) come limite dinamico.
// Il giorno 1 riceve l'orario "in più" in Early; il giorno 2 (remap) lo avrà in Late.

/**
 * Simula la logica di generateDoubleTee con il nuovo constraint dinamico.
 * Restituisce { mEarly, mLate, fEarly, fLate, totalEarly, totalLate }
 */
function simulaCombinato(logic, nUomini, nDonne, mod = 3) {
  const wQ = logic.bilanciaQuadranti(nDonne, mod);
  const fEarly = wQ.Q1;
  const fLate  = wQ.Q3;

  const totalFlights    = Math.ceil(nUomini / mod) + Math.ceil(nDonne / mod);
  const totalOrari      = totalFlights / 2;
  const targetEarly     = Math.ceil(totalOrari / 2);
  const menMax          = Math.max(0, targetEarly - fEarly);

  const mQ     = logic.bilanciaQuadranti(nUomini, mod, menMax);
  const mEarly = mQ.Q1;
  const mLate  = mQ.Q3;

  return {
    mEarly, mLate, fEarly, fLate,
    totalEarly: mEarly + fEarly,
    totalLate:  mLate  + fLate,
    totalOrari,
    targetEarly,
  };
}

describe('REGRESSIONE — bilanciamento Early ≈ Late (|diff| ≤ 1)', () => {
  let logic;
  beforeEach(() => { logic = makeLogic(); });

  it('[BUG ORIGINALE] 102U+42D → Early=12, Late=12 (era 13+11 senza fix)', () => {
    const r = simulaCombinato(logic, 102, 42);
    expect(r.mEarly).toBe(8);
    expect(r.mLate).toBe(9);
    expect(r.fEarly).toBe(4);
    expect(r.fLate).toBe(3);
    expect(r.totalEarly).toBe(12);
    expect(r.totalLate).toBe(12);
  });

  it('[BUG 2] 64U+17D → Early=7, Late=7 (era 8+6 con vincolo fisso 12)', () => {
    // Con MAX_ORARI=12: maleMax=12-2=10, men naturale=6 → nessun constraint → 8E+6L ❌
    // Con targetEarly=7: maleMax=7-2=5, men→5E+6L → total 7E+7L ✓
    const r = simulaCombinato(logic, 64, 17);
    expect(r.mEarly).toBe(5);
    expect(r.mLate).toBe(6);
    expect(r.fEarly).toBe(2);
    expect(r.totalEarly).toBe(7);
    expect(r.totalLate).toBe(7);  // non 6 come prima
  });

  it('donne non vengono alterate dal constraint maschile (42 donne)', () => {
    const wQ = logic.bilanciaQuadranti(42, 3);
    expect(wQ.Q1).toBe(4);
    expect(wQ.Q2).toBe(4);
    expect(wQ.Q3).toBe(3);
    expect(wQ.Q4).toBe(3);
  });

  // Tutti gli scenari: |totalEarly - totalLate| ≤ 1
  const scenari = [
    { m: 144, f: 0,  desc: '144U + 0D'  },
    { m: 102, f: 42, desc: '102U + 42D' },
    { m: 96,  f: 48, desc: '96U + 48D'  },
    { m: 108, f: 36, desc: '108U + 36D' },
    { m: 120, f: 24, desc: '120U + 24D' },
    { m: 90,  f: 42, desc: '90U + 42D'  },
    { m: 72,  f: 30, desc: '72U + 30D'  },
    { m: 64,  f: 17, desc: '64U + 17D'  },
    { m: 48,  f: 12, desc: '48U + 12D'  },
  ];

  scenari.forEach(({ m, f, desc }) => {
    it(`${desc} → |Early-Late| ≤ 1`, () => {
      const r = simulaCombinato(logic, m, f);
      expect(Math.abs(r.totalEarly - r.totalLate)).toBeLessThanOrEqual(1);
    });
  });

  it('i gruppi uomini con constraint coprono tutti i giocatori senza duplicati', () => {
    const atleti = Array.from({ length: 102 }, (_, i) => i + 1);
    const wQ = logic.bilanciaQuadranti(42, 3);
    const totalFlights = Math.ceil(102/3) + Math.ceil(42/3);
    const targetEarly  = Math.ceil((totalFlights/2) / 2);
    const menMax = Math.max(0, targetEarly - wQ.Q1);
    const groups = logic.generatePlayerGroups(102, 3, atleti, 'M', menMax);
    const all = groups.flatMap(g => g.players);
    expect(all.length).toBe(102);
    expect(new Set(all).size).toBe(102);
  });
});

// ─── REGRESSIONE: caricamento Federgolf MISTA (M+F insieme) ──────────────────
// Bug introdotto in fbddad9 ("Migliorie quadranti"): selezionando una gara MISTA
// dal dropdown Federgolf, se anche UNA SOLA delle due gare (M o F) aveva
// iscrizioni ancora aperte (totale>0 e ammessi=0), l'intero caricamento veniva
// abortito con `if (aperte1 || aperte2) return`. Il comportamento atteso (e
// presente fino a 8c0e632) è: in MISTA carica entrambi i generi quando entrambi
// sono chiusi; se una sola è aperta, carica l'altra; abortisci solo se entrambe
// sono aperte.
describe('REGRESSIONE — mergeFedergolfResponses (caricamento MISTA M+F)', () => {
  // Helper: risposta "iscrizioni chiuse, gara con iscritti ammessi"
  const ok = (iscritti) => ({
    success: true,
    iscritti,
    totale_iscritti: iscritti.length,
    ammessi: iscritti.length,
    iscrizioni_aperte: false,
    message: null,
  });

  // Helper: risposta "iscrizioni ancora aperte" (ci sono righe ma nessuno ammesso)
  const aperta = () => ({
    success: true,
    iscritti: [],
    totale_iscritti: 5,
    ammessi: 0,
    iscrizioni_aperte: true,
    message: 'Iscrizioni non ancora chiuse: nessun iscritto ammesso. Riprovare dopo la chiusura.',
  });

  describe('MISTA: response2 presente (M+F insieme)', () => {
    it('[BUG ORIGINALE] entrambe chiuse → carica M e F insieme senza abortire', () => {
      const r1 = ok(['Tom', 'Bob']);          // gara maschile
      const r2 = ok(['Alice', 'Beth']);       // gara femminile
      const result = mergeFedergolfResponses(r1, r2, 'MISTA');

      expect(result.abort).toBe(false);
      expect(result.atleti).toEqual(['Tom', 'Bob']);
      expect(result.atlete).toEqual(['Alice', 'Beth']);
      expect(result.warning).toBeNull();
    });

    it('[REGRESSIONE] solo F aperta → NON abortire, carica M con warning', () => {
      // Era IL BUG: aperte2=true ⇒ if(aperte1||aperte2) return → l'utente non
      // poteva caricare gli uomini neppure quando le loro iscrizioni erano già
      // chiuse, perché il check OR azzerava tutto il flow.
      const r1 = ok(['Tom', 'Bob']);
      const r2 = aperta();
      const result = mergeFedergolfResponses(r1, r2, 'MISTA');

      expect(result.abort).toBe(false);
      expect(result.atleti).toEqual(['Tom', 'Bob']);
      expect(result.atlete).toEqual([]);
      expect(result.warning).toContain('femminile');
    });

    it('[REGRESSIONE] solo M aperta → NON abortire, carica F con warning', () => {
      const r1 = aperta();
      const r2 = ok(['Alice', 'Beth']);
      const result = mergeFedergolfResponses(r1, r2, 'MISTA');

      expect(result.abort).toBe(false);
      expect(result.atleti).toEqual([]);
      expect(result.atlete).toEqual(['Alice', 'Beth']);
      expect(result.warning).toContain('maschile');
    });

    it('entrambe aperte → abort con messaggio', () => {
      const r1 = aperta();
      const r2 = aperta();
      const result = mergeFedergolfResponses(r1, r2, 'MISTA');

      expect(result.abort).toBe(true);
      expect(result.atleti).toEqual([]);
      expect(result.atlete).toEqual([]);
      expect(result.warning).toBeTruthy();
    });

    it('iscritti vuoti su entrambe (ma chiuse) → no abort, atleti/atlete vuoti', () => {
      // Es. gara passata senza partecipanti: lascia che il chiamante gestisca
      // l'avviso "nessun nominativo trovato" successivamente.
      const r1 = ok([]);
      const r2 = ok([]);
      const result = mergeFedergolfResponses(r1, r2, 'MISTA');

      expect(result.abort).toBe(false);
      expect(result.atleti).toEqual([]);
      expect(result.atlete).toEqual([]);
      expect(result.warning).toBeNull();
    });

    it('arrays sono indipendenti: 50M+15F caricati in atleti/atlete distinti', () => {
      const men = Array.from({ length: 50 }, (_, i) => `Uomo${i + 1}`);
      const women = Array.from({ length: 15 }, (_, i) => `Donna${i + 1}`);
      const result = mergeFedergolfResponses(ok(men), ok(women), 'MISTA');

      expect(result.atleti).toHaveLength(50);
      expect(result.atlete).toHaveLength(15);
      expect(result.atleti[0]).toBe('Uomo1');
      expect(result.atlete[0]).toBe('Donna1');
    });
  });

  describe('Singolo MASCHILE: response2 == null', () => {
    it('chiusa con iscritti → carica atleti, atlete vuote', () => {
      const result = mergeFedergolfResponses(ok(['Tom']), null, 'MASCHILE');
      expect(result.abort).toBe(false);
      expect(result.atleti).toEqual(['Tom']);
      expect(result.atlete).toEqual([]);
    });

    it('aperta → abort (singolo non ha fallback su altro genere)', () => {
      const result = mergeFedergolfResponses(aperta(), null, 'MASCHILE');
      expect(result.abort).toBe(true);
      expect(result.warning).toBeTruthy();
    });
  });

  describe('Singolo FEMMINILE: response2 == null', () => {
    it('chiusa con iscritte → carica atlete, atleti vuoti', () => {
      const result = mergeFedergolfResponses(ok(['Alice']), null, 'FEMMINILE');
      expect(result.abort).toBe(false);
      expect(result.atleti).toEqual([]);
      expect(result.atlete).toEqual(['Alice']);
    });

    it('aperta → abort', () => {
      const result = mergeFedergolfResponses(aperta(), null, 'FEMMINILE');
      expect(result.abort).toBe(true);
    });
  });

  describe('robustezza: input mancanti', () => {
    it('response1 senza iscritti → trattato come array vuoto', () => {
      const r1 = { success: true, iscrizioni_aperte: false };
      const result = mergeFedergolfResponses(r1, null, 'MASCHILE');
      expect(result.abort).toBe(false);
      expect(result.atleti).toEqual([]);
    });

    it('response2 senza iscritti → atlete vuote ma no abort', () => {
      const r1 = ok(['Tom']);
      const r2 = { success: true, iscrizioni_aperte: false };
      const result = mergeFedergolfResponses(r1, r2, 'MISTA');
      expect(result.abort).toBe(false);
      expect(result.atleti).toEqual(['Tom']);
      expect(result.atlete).toEqual([]);
    });
  });

  describe('REGRESSIONE — gestione success:false (timeout federgolf.it)', () => {
    // Quando il controller backend restituisce {success:false, message:...}
    // (es. cURL timeout 28, federgolf.it non risponde per gare grosse come
    // "Quercia d'Oro"), il flow handleFedergolfGaraSelected deve mostrare il
    // messaggio diagnostico SENZA toccare storage o azzerare i campi.
    // mergeFedergolfResponses non gestisce questo caso direttamente — il check
    // failed1/failed2 avviene a livello di handler — ma ci assicuriamo che la
    // risposta di errore non causi crash o stati incoerenti se passata comunque.
    const failed = (msg) => ({
      success: false,
      iscritti: [],
      totale_iscritti: 0,
      ammessi: 0,
      iscrizioni_aperte: false,
      message: msg,
    });

    it('response1 con success=false → atleti/atlete vuoti, no crash', () => {
      const result = mergeFedergolfResponses(
        failed('Federgolf.it non risponde (timeout)'),
        null,
        'MASCHILE'
      );
      // mergeFedergolfResponses non vede `success`, ma non deve esplodere
      // e deve restituire array vuoti.
      expect(result.atleti).toEqual([]);
      expect(result.atlete).toEqual([]);
    });

    it('MISTA con response2 fallita → atleti caricati, atlete vuote', () => {
      const result = mergeFedergolfResponses(
        ok(['Tom']),
        failed('timeout'),
        'MISTA'
      );
      expect(result.atleti).toEqual(['Tom']);
      expect(result.atlete).toEqual([]);
    });
  });
});

// ─── REGRESSIONE: normalizeGaraTitle (raggruppamento dropdown M+F) ───────────
// Bug: la regex /\s*(MASCHILE|FEMMINILE)\s*/gi non gestiva la punteggiatura
// attorno alla parola-chiave. Conseguenza: gare M ed F dello stesso evento con
// punteggiatura asimmetrica (es. trattino prima di MASCHILE ma assente prima di
// FEMMINILE) producevano chiavi diverse e nel dropdown apparivano come voci
// [M] e [F] separate, senza l'opzione combinata [M+F]. L'utente non poteva
// quindi caricare i due generi insieme. Questi test garantiscono che le forme
// più comuni di titolo federgolf.it producano la stessa chiave normalizzata.
describe('REGRESSIONE — normalizeGaraTitle (raggruppamento dropdown M+F)', () => {
  it('coppie MASCHILE/FEMMINILE simmetriche → stessa chiave', () => {
    expect(normalizeGaraTitle('TROFEO XYZ MASCHILE 2026'))
      .toBe(normalizeGaraTitle('TROFEO XYZ FEMMINILE 2026'));
  });

  it('[BUG] trattino prima di MASCHILE ma non FEMMINILE → ora stessa chiave', () => {
    // Vecchia regex: "TROFEO X -2026" vs "TROFEO X 2026" (diverse)
    // Nuova: entrambe → "trofeo x 2026"
    expect(normalizeGaraTitle('TROFEO X - MASCHILE 2026'))
      .toBe(normalizeGaraTitle('TROFEO X FEMMINILE 2026'));
  });

  it('[BUG] separatori asimmetrici (slash, due punti, virgola) → stessa chiave', () => {
    expect(normalizeGaraTitle('GARA: MASCHILE'))
      .toBe(normalizeGaraTitle('GARA, FEMMINILE'));
  });

  it('[BUG] trattino lungo (em-dash) attorno a MASCHILE → stessa chiave di FEMMINILE plain', () => {
    expect(normalizeGaraTitle('CAMPIONATO – MASCHILE – 2026'))
      .toBe(normalizeGaraTitle('CAMPIONATO FEMMINILE 2026'));
  });

  it('case-insensitive: maschile/MASCHILE/Maschile → stessa chiave', () => {
    expect(normalizeGaraTitle('Trofeo Maschile 2026'))
      .toBe(normalizeGaraTitle('TROFEO FEMMINILE 2026'));
  });

  it('whitespace multipli/diversi → collassati in spazi singoli', () => {
    expect(normalizeGaraTitle('GARA  MASCHILE   2026'))
      .toBe(normalizeGaraTitle('GARA\tFEMMINILE\n2026'));
  });

  it('input vuoto/null/undefined → stringa vuota, non crash', () => {
    expect(normalizeGaraTitle('')).toBe('');
    expect(normalizeGaraTitle(null)).toBe('');
    expect(normalizeGaraTitle(undefined)).toBe('');
  });

  it('non confonde gare diverse: titoli con prefissi differenti restano distinti', () => {
    expect(normalizeGaraTitle('GARA REGIONALE U18 MASCHILE'))
      .not.toBe(normalizeGaraTitle('GARA REGIONALE U16 FEMMINILE'));
  });

  it('non confonde gare con stesso prefisso ma evento diverso', () => {
    expect(normalizeGaraTitle('TROFEO PRIMAVERA MASCHILE'))
      .not.toBe(normalizeGaraTitle('TROFEO ESTATE FEMMINILE'));
  });

  it('simula raggruppamento dropdown: chiave = nome + data', () => {
    const garaM = { title: 'CAMPIONATO ITALIANO - MASCHILE', date: '15/06/2026' };
    const garaF = { title: 'CAMPIONATO ITALIANO FEMMINILE',   date: '15/06/2026' };

    const chiaveM = `${normalizeGaraTitle(garaM.title)}_${garaM.date}`;
    const chiaveF = `${normalizeGaraTitle(garaF.title)}_${garaF.date}`;

    expect(chiaveM).toBe(chiaveF); // ⇒ il dropdown produrrà l'opzione [M+F]
  });

  it('date diverse → chiavi diverse anche se titoli identici (eventi distinti)', () => {
    const garaA = { title: 'TROFEO XYZ MASCHILE', date: '15/06/2026' };
    const garaB = { title: 'TROFEO XYZ FEMMINILE', date: '22/06/2026' };

    const chiaveA = `${normalizeGaraTitle(garaA.title)}_${garaA.date}`;
    const chiaveB = `${normalizeGaraTitle(garaB.title)}_${garaB.date}`;

    expect(chiaveA).not.toBe(chiaveB);
  });
});
