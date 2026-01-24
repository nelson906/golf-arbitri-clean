# Analisi Calendari Golf 2026

## Riepilogo PDF Analizzati

1. **calendario-attivita-dilettantistica-2026.pdf** - 6 pagine
2. **calendario-attivita-gare_patrocinate-2026.pdf** - 1 pagina
3. **calendario-attivita-giovanile-2026.pdf** - 3 pagine
4. **calendario-attivita-giovanile_zonale-2026.pdf** - 13 pagine

## Tipi di Torneo Identificati (da intestazioni PDF)

### Esistenti nel DB (da verificare mapping):
1. **Campionato Internazionale** - is_national: true, level: nazionale
2. **Campionato Nazionale** - is_national: true, level: nazionale

### Da Creare o Verificare:
3. **Gara Nazionale di Selezione**
4. **Gara Nazionale 54/54**
5. **Trofeo Giovanile Federale WAGR U18**
6. **Trofeo Giovanile Federale U18**
7. **Trofeo Giovanile Federale U16**
8. **Gara Patrocinata**
9. **Gara Nazionale 54/54 a Regolamento Speciale**
10. **Campionato Regionale/Interregionale a Squadre**
11. **Campionato Regionale/Interregionale Individuale**
12. **Gara Internazionale U.S. Kids**
13. **Circuito Teodoro Soldati** (zonale)

## Circoli nel Database (da ClubsTableSeeder)

### Zona 1 - SZR1 (Piemonte-Valle d'Aosta-Liguria):
- Golf Club Torino (TORINO)
- Royal Park I Roveri (ROYAL PARK ROVERI)
- Circolo Golf e Tennis Rapallo (RAPALLO)

### Zona 2 - SZR2 (Lombardia):
- Golf Milano (MILANO)
- L'Albenza Golf Club (BERGAMO ALBENZA)
- Franciacorta Golf Club (FRANCIACORTA)

### Zona 3 - SZR3 (Veneto-Trentino-Friuli):
- Golf Club Venezia
- Golf Club Asolo (ASOLO)

### Zona 4 - SZR4 (Emilia-Romagna):
- Bologna Golf Club (BOLOGNA)
- Modena Golf & Country Club (MODENA)

### Zona 5 - SZR5 (Toscana-Umbria):
- Circolo Golf Ugolino (FIRENZE UGOLINO)
- Golf Club Punta Ala (PUNTA ALA)

### Zona 6 - SZR6 (Lazio-Abruzzo-Molise-Sardegna):
- Olgiata Golf Club (OLGIATA)
- Marco Simone Golf & Country Club (MARCO SIMONE)
- Circolo Golf Mirasole (FIORANELLO o MIRASOLE?)

### Zona 7 - SZR7 (Sud Italia-Sicilia):
- Villa Airoldi Golf Club
- Is Molas Golf Club (IS MOLAS SSD)
- Pevero Golf Club
- Acaya Golf Club

## Circoli Menzionati nei PDF (da Mappare o Creare)

### PRESENTI (mapping nome PDF -> nome DB):
- IS MOLAS SSD -> Is Molas Golf Club (Z7)
- BERGAMO ALBENZA -> L'Albenza Golf Club (Z2)
- FRANCIACORTA -> Franciacorta Golf Club (Z2)
- ASOLO -> Golf Club Asolo (Z3)
- BOLOGNA -> Bologna Golf Club (Z4)
- MODENA -> Modena Golf & Country Club (Z4)
- PUNTA ALA -> Golf Club Punta Ala (Z5)
- OLGIATA -> Olgiata Golf Club (Z6)
- MARCO SIMONE -> Marco Simone Golf & Country Club (Z6)
- TORINO -> Golf Club Torino (Z1)
- ROYAL PARK ROVERI -> Royal Park I Roveri (Z1)
- RAPALLO -> Circolo Golf e Tennis Rapallo (Z1)
- MILANO -> Golf Milano (Z2)

### MANCANTI (da creare):

#### Zona 1:
- BIELLA BETULLE
- GARLENDA
- MARGHERITA
- MARGARA
- CASTELCONTURBIA
- CHERASCO
- CERVINO
- SESTRIERES
- BOVES
- CAVAGLIA'
- CONTINENTAL VERBANIA
- COLLINE GAVI
- ALBISOLA
- ARENZANO PINETA
- FRONDE
- GIRASOLI
- AOSTA BRISSOGNE
- SANREMO ULIVI
- CASTELLARO
- DES ILES BORROMEES

#### Zona 2:
- VILLA D'ESTE
- TOLCINASCO
- MONTICELLO
- VARESE
- MOLINETTO
- PINETINA
- ARZAGA
- ZOATE
- LECCO
- AMBROSIANO
- VILLA PARADISO SSD
- GARDAGOLF
- LAGHI
- MENAGGIO
- ROSSERA
- CAMPODOGLIO
- LANZO
- BARLASSINA
- COLLI BERGAMO
- COLOMBERA ASD
- BORMIO SSD
- VALTELLINA
- ROVEDINE
- CARIMATE

#### Zona 3:
- VILLA CONDULMER
- LIGNANO SSD
- ASIAGO
- FOLGARIA
- DOLOMITI
- MADONNA CAMPIGLIO
- MONTECCHIA GOLF

#### Zona 4:
- SALSOMAGGIORE TERME
- FONTI
- RIVIERA GOLF
- CROARA SSD
- CUS FERRARA
- RIMINI VERUCCHIO
- MONTEVEGLIO ASD
- FAENZA CICOGNE
- ARGENTA
- CASALUNGA
- SANTO STEFANO GOLF
- CONERO
- OASI DI MAGLIANO-FIORDALISI

#### Zona 5:
- PAVONIERE
- TOSCANA (generico - probabilmente più circoli)
- ARGENTARIO
- BELLOSGUARDO
- RIVA TOSCANA
- MONTELUPO
- VALDICHIANA
- CASENTINO
- TIRRENIA
- MONTECATINI TERME

#### Zona 6:
- NAZIONALE (location da definire)
- ROMA ACQUASANTA
- PARCO ROMA
- FIORANELLO
- CASTELGANDOLFO
- PARCO MEDICI
- FIUGGI 1928
- PERUGIA
- TERRE CONSOLI
- MARINA VELKA
- ARCHI CLAUDIO
- LAMBORGHINI

#### Zona 7:
- SAN DOMENICO - EGNAZIA
- VERDURA
- CERRETO MIGLIANICO
- SICILIA'S PICCIOLO

### Circoli con Nome Generico o Da Definire:
- T.B.A. (To Be Announced - 4 occorrenze)
- NAZIONALE (circolo specifico o location generica?)

## Casi Dubbi da Risolvere

### 1. Circoli con Nomi Simili
- FIORANELLO vs MIRASOLE (stessa location?)
- FIRENZE UGOLINO vs UGOLINO (stesso circolo)

### 2. Circoli con Location Generica
- **NAZIONALE** - usato per "NAZIONALE FEMMINILE MATCH PLAY" in Zona 6. Quale circolo specifico?
- **TOSCANA** - usato per varie gare in Zona 5. Quale circolo specifico?

### 3. Circoli T.B.A. (To Be Announced)
- Aprile 11-12: "CAMPIONATO TOSCANO A SQUADRE" - T.B.A.
- Giugno 20-21: "TROFEO GIOVANILE FEDERALE UNDER 16" - T.B.A.
- Ottobre 17-18: "MEMORIAL STEFANO ESENTE" - T.B.A.

### 4. Nomi con Suffissi Diversi
- "IS MOLAS SSD" - il SSD sta per "Società Sportiva Dilettantistica"?
- "LIGNANO SSD"
- "CROARA SSD"
- "VILLA PARADISO SSD"
- "VILLA D'ESTE" vs "VILLA PARADISO SSD" - circoli diversi

### 5. Duplicazioni tra Calendari
Molte gare appaiono in più calendari (dilettantistica e giovanile). Verificare se creare un solo record o duplicati.

## Mapping Zone Difficili

Alcuni circoli menzionati potrebbero non essere nella zona indicata nel PDF. Da verificare:
- Zona indicata nei PDF è corretta?
- Alcuni circoli potrebbero essere stati riassegnati a zone diverse

## Prossimi Passi

1. ✅ Completare mapping circoli esistenti
2. ⏳ Creare lista completa circoli mancanti con zone stimate
3. ⏳ Definire tournament_types mancanti
4. ⏳ Creare seeder con tornei non duplicati
5. ⏳ Gestire casi T.B.A.
6. ⏳ Presentare casi dubbi all'utente

## Note Tecniche

- Deadline disponibilità: da calcolare (solitamente 2-3 settimane prima start_date)
- Status default: 'open' (da Tournament model)
- created_by: dovrà essere un utente super_admin esistente
- Alcuni tornei hanno date che attraversano mesi (es. 30/04 - 02/05)
