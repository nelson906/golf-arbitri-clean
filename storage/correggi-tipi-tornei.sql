-- Correzione dei tipi dei tornei creati il 31/03/2026 tutti come "Gara 36 buche".
-- Ogni riga cambia UN torneo, cercato per nome e solo se ha ancora il tipo
-- GN36: rieseguirlo non fa danni. Da incollare nella scheda SQL di phpMyAdmin
-- (Aruba) o del MAMP. Dopo, eseguire anche database/sql/fig-segna-notificati.sql
-- perche' i tornei diventati nazionali abbiano la notifica CRC.

SET @gn36 = (SELECT id FROM tournament_types WHERE short_name = 'GN36');

-- Nazionali (li gestisce il CRC)
UPDATE tournaments SET tournament_type_id = (SELECT id FROM tournament_types WHERE short_name = 'CNZ'), updated_at = NOW()
 WHERE tournament_type_id = @gn36 AND TRIM(name) = 'CAMPIONATO NAZIONALE FEMMINILE MEDAL - TROFEO ISA GOLDSCHMID';
UPDATE tournaments SET tournament_type_id = (SELECT id FROM tournament_types WHERE short_name = 'CNZ'), updated_at = NOW()
 WHERE tournament_type_id = @gn36 AND TRIM(name) IN ('TORNEO NAZIONALE DI QUALIFICA MASCHILE A SQUADRE', 'TORNEO NAZIONALE DI QUALIFICA FEMMINILE A SQUADRE');

-- Zonali con il tipo giusto
UPDATE tournaments SET tournament_type_id = (SELECT id FROM tournament_types WHERE short_name = 'CR'), updated_at = NOW()
 WHERE tournament_type_id = @gn36 AND TRIM(name) = 'CAMPIONATO REGIONALE TOSCANO A SQUADRE';
UPDATE tournaments SET tournament_type_id = (SELECT id FROM tournament_types WHERE short_name = 'TGF'), updated_at = NOW()
 WHERE tournament_type_id = @gn36 AND (TRIM(name) LIKE 'TGF %' OR TRIM(name) LIKE 'TROFEO GIOVANILE FEDERALE%');
UPDATE tournaments SET tournament_type_id = (SELECT id FROM tournament_types WHERE short_name = 'GN54'), updated_at = NOW()
 WHERE tournament_type_id = @gn36 AND TRIM(name) = 'GARA NAZIONALE 54/54 A REGOLAMENTO SPECIALE';
UPDATE tournaments SET tournament_type_id = (SELECT id FROM tournament_types WHERE short_name = 'GRS'), updated_at = NOW()
 WHERE tournament_type_id = @gn36 AND TRIM(name) = 'ASOLO HILLS GARA A REGOLAMENTO SPECIALE';

-- Controllo: i tornei rimasti "Gara 36 buche"
SELECT id, name, start_date FROM tournaments WHERE tournament_type_id = @gn36 ORDER BY start_date;
