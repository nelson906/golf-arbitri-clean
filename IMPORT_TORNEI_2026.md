# Guida Importazione Tornei 2026

## 📋 Panoramica

Questo documento spiega come importare i **189 tornei 2026** estratti dai 4 calendari PDF ufficiali FIG.

## 🎯 Cosa Include

### File Creati

1. **`calendari_2026_consolidato.csv`** - CSV consolidato con tutti i tornei
2. **`database/seeders/Tournaments2026Seeder.php`** - Seeder per import automatico
3. **`ANALISI_CALENDARI_2026.md`** - Analisi dettagliata dei PDF

### Tornei Importati

| Tipo Torneo | Codice | Quantità |
|-------------|--------|----------|
| Campionato Internazionale | CI | 4 |
| Campionato Nazionale | CNZ | 27 |
| Torneo Nazionale / Selezione | TNZ | 5 |
| Gara Nazionale 54/54 | GN54 | 36 |
| Trofeo Giovanile Federale | TGF | 20 |
| Gara Patrocinata | PATR | 14 |
| U.S. Kids | USK | 3 |
| Torneo 18 buche (Teodoro Soldati) | T18 | 73 |
| Campionato Regionale | CR | 7 |
| **TOTALE** | | **189** |

## ⚠️ IMPORTANTE: Preservazione Dati 2025

Il seeder è progettato per **NON toccare i dati 2025**:

- ✅ **Preserva** tutti i tornei 2025 esistenti
- ✅ **Preserva** storico assegnazioni e disponibilità 2025
- ✅ **Preserva** dati per `referee_career_history`
- ❌ **Cancella SOLO** tornei con `start_date >= 2026-01-01`

### Perché è Importante?

I dati 2025 potrebbero essere necessari per:
- Alimentare `referee_career_history` secondo procedure esistenti
- Mantenere storico assegnazioni anche se fittizie
- Permettere popolamento dati 2025 in un secondo momento

## 🚀 Come Importare

### Prerequisiti

1. **Database migrato** con tutte le tabelle
2. **Almeno un utente super_admin** nel database
3. **File CSV** nella root del progetto

### Passo 1: Verifica File CSV

```bash
# Controlla che il file esista
ls -lh calendari_2026_consolidato.csv

# Dovrebbe mostrare ~190 righe
wc -l calendari_2026_consolidato.csv
```

### Passo 2: Esegui il Seeder

```bash
php artisan db:seed --class=Tournaments2026Seeder
```

### Output Atteso

```
🏌️ Importazione Tornei 2026 dai Calendari PDF
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🗑️  Cancellazione tornei 2026 esistenti...
   Nessun torneo 2026 esistente
   ✓ Tornei 2025 preservati: XX

📋 Verifica Tournament Types...
   ✓ 9 esistenti, 0 creati

🏌️ Verifica Circoli...
   + Creato: Villa D'este (Zona 2)
   + Creato: Villa Condulmer (Zona 3)
   ... (altri circoli mancanti)
   ✓ XX esistenti, YY creati

📅 Importazione Tornei 2026...
   ... 50 tornei importati
   ... 100 tornei importati
   ... 150 tornei importati

   ✓ Importati: 186
   ⊘ Saltati: 3

✅ Importazione completata!
```

### Passo 3: Verifica Importazione

```bash
# Conta tornei 2026
php artisan tinker --execute="echo 'Tornei 2026: ' . \App\Models\Tournament::whereYear('start_date', 2026)->count();"

# Conta tornei 2025 (dovrebbero essere invariati)
php artisan tinker --execute="echo 'Tornei 2025: ' . \App\Models\Tournament::whereYear('start_date', 2025)->count();"

# Mostra distribuzione per zona
php artisan tinker --execute="
\$data = \App\Models\Tournament::whereYear('start_date', 2026)
    ->selectRaw('zone_id, COUNT(*) as count')
    ->groupBy('zone_id')
    ->orderBy('zone_id')
    ->get();
echo json_encode(\$data, JSON_PRETTY_PRINT);
"
```

## 📊 Cosa Fa il Seeder

### Step 1: Cancellazione Selettiva
```sql
DELETE FROM tournaments
WHERE start_date >= '2026-01-01'
AND start_date < '2027-01-01';
```

### Step 2: Creazione Tournament Types

Crea/verifica 9 tipi di torneo con configurazioni:
- `name`, `short_name`, `is_national`, `level`
- `required_level`, `min_referees`, `max_referees`

### Step 3: Creazione Circoli Mancanti

Per ogni circolo nel CSV non presente nel DB:
- Normalizza nome e crea codice
- Assegna zona dal CSV
- Crea email placeholder
- Imposta `is_active = true`

### Step 4: Import Tornei

Per ogni riga del CSV:
1. ✅ Trova/salta circolo (se T.B.A.)
2. ✅ Trova tournament_type per `short_name`
3. ✅ Calcola `availability_deadline` (start_date - 3 settimane)
4. ✅ Verifica duplicati (`name`, `club_id`, `start_date`)
5. ✅ Crea torneo con `status = 'open'`
6. ✅ Deriva `zone_id` sempre da `club->zone_id`

## 🎲 Tornei Saltati (T.B.A.)

3 tornei hanno circolo "To Be Assigned" e vengono saltati:

1. **2026-04-11/12**: CAMPIONATO TOSCANO A SQUADRE
2. **2026-06-20/21**: TROFEO GIOVANILE FEDERALE UNDER 16
3. **2026-10-17/18**: MEMORIAL STEFANO ESENTE

### Come Gestirli

Quando il circolo sarà definito:
1. Aggiorna il CSV con il circolo corretto
2. Ri-esegui il seeder (cancellerà e ricrea tutti i 2026)

## 🔧 Risoluzione Problemi

### Errore: "Nessun super_admin trovato"

```bash
# Crea un super_admin
php artisan tinker --execute="
\App\Models\User::create([
    'name' => 'Admin Sistema',
    'email' => 'admin@federgolf.it',
    'password' => bcrypt('password'),
    'user_type' => 'super_admin',
    'level' => 'Nazionale',
    'zone_id' => 1,
]);
"
```

### Errore: "File CSV non trovato"

```bash
# Verifica path
ls -la calendari_2026_consolidato.csv

# Il file deve essere nella root del progetto
# Se è altrove, sposta:
mv path/to/calendari_2026_consolidato.csv .
```

### Circoli Duplicati

Se il seeder crea circoli duplicati:
1. Verifica in `clubs` table con: `SELECT * FROM clubs WHERE name LIKE '%NOME%'`
2. Se esiste con nome simile, aggiorna il CSV con il nome esatto
3. Ri-esegui il seeder

### Rollback Completo

Per annullare completamente l'import 2026:

```bash
php artisan tinker --execute="
\App\Models\Tournament::where('start_date', '>=', '2026-01-01')
    ->where('start_date', '<', '2027-01-01')
    ->delete();
echo 'Tornei 2026 cancellati';
"
```

## 📈 Dopo l'Importazione

### Verifica Dati

```bash
# Tornei per mese
php artisan tinker --execute="
\$data = \App\Models\Tournament::whereYear('start_date', 2026)
    ->selectRaw('MONTH(start_date) as mese, COUNT(*) as count')
    ->groupBy('mese')
    ->orderBy('mese')
    ->get();
echo json_encode(\$data, JSON_PRETTY_PRINT);
"

# Tornei nazionali
php artisan tinker --execute="
echo 'Nazionali: ' . \App\Models\Tournament::whereYear('start_date', 2026)
    ->whereHas('tournamentType', fn(\$q) => \$q->where('is_national', true))
    ->count();
"
```

### Prossimi Passi

1. ✅ Verificare zone assegnate ai circoli creati
2. ✅ Completare dati circoli (indirizzo, telefono, email reale)
3. ✅ Gestire i 3 tornei T.B.A. quando saranno definiti
4. ✅ Iniziare assegnazione arbitri ai tornei aperti

## 📚 File di Riferimento

- **CSV**: `calendari_2026_consolidato.csv` - Dati sorgente
- **Seeder**: `database/seeders/Tournaments2026Seeder.php` - Logica import
- **Analisi**: `ANALISI_CALENDARI_2026.md` - Dettagli estrazione PDF
- **Migration**: `database/migrations/2025_08_29_055240_create_clean_golf_schema.php` - Schema DB

## 🆘 Supporto

Per problemi o domande:
1. Controlla i log: `storage/logs/laravel.log`
2. Verifica output del seeder per messaggi di warning/errore
3. Consulta il CSV per dati originali

## 📝 Note Tecniche

### Campi Calcolati

- **zone_id**: sempre `club->zone_id` (non dalla colonna `zona` del CSV)
- **availability_deadline**: `start_date - 3 settimane` alle 23:59:59
- **status**: sempre `'open'` (Tournament::STATUS_OPEN)
- **created_by**: primo super_admin trovato

### Unicità

Un torneo è unico per: `(name, club_id, start_date)`

Se esiste già un torneo con stessa combinazione, viene saltato.

### Relazioni

- `tournament -> club -> zone`: zona sempre derivata dal club
- `tournament -> tournament_type`: via `short_name`
- `tournament -> user (created_by)`: primo super_admin
