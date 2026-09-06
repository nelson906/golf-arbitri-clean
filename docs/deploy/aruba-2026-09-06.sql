-- =====================================================================
-- golf-arbitri-clean — allineamento DB Aruba
-- Data: 2026-09-06
--
-- Da incollare nel phpMyAdmin di Aruba, UN BLOCCO ALLA VOLTA, leggendo
-- il risultato di ciascuno prima di passare al successivo.
--
-- Contesto: lo schema remoto viene da un dump, quindi la tabella
-- `migrations` non registra le migration del 2025-08-29 anche se le
-- tabelle esistono. Finche' resta cosi', qualunque `migrate` riparte da
-- capo e sbatte sulla prima tabella gia' presente.
-- =====================================================================


-- ---------------------------------------------------------------------
-- BLOCCO 0 — FOTOGRAFIA (non modifica nulla)
-- Serve a sapere da dove si parte. Conserva l'output.
-- ---------------------------------------------------------------------

SELECT migration, batch FROM migrations ORDER BY batch, migration;

SELECT
    COLUMN_NAME,
    IS_NULLABLE,
    COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'tournaments'
  AND COLUMN_NAME  = 'club_id';

-- Atteso PRIMA dell'intervento: IS_NULLABLE = 'NO'.
-- Se e' gia' 'YES', il BLOCCO 1 e' gia' stato applicato: saltalo.


-- ---------------------------------------------------------------------
-- BLOCCO 1 — tournaments.club_id diventa nullable
--
-- Perche': nel calendario federale una gara puo' avere data e zona gia'
-- fissate mentre il circolo e' ancora "T.B.A.". Con club_id NOT NULL quei
-- tornei non erano rappresentabili e Tournaments2026Seeder li scartava.
-- ---------------------------------------------------------------------

ALTER TABLE tournaments
    MODIFY club_id BIGINT UNSIGNED NULL;

-- Se MySQL rifiuta per via della foreign key, usa questa variante
-- (togli i commenti, esegui le tre righe in sequenza):
--
-- ALTER TABLE tournaments DROP FOREIGN KEY tournaments_club_id_foreign;
-- ALTER TABLE tournaments MODIFY club_id BIGINT UNSIGNED NULL;
-- ALTER TABLE tournaments
--     ADD CONSTRAINT tournaments_club_id_foreign
--     FOREIGN KEY (club_id) REFERENCES clubs(id);
--
-- Il nome del vincolo lo verifichi con:
-- SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tournaments'
--   AND COLUMN_NAME = 'club_id' AND REFERENCED_TABLE_NAME IS NOT NULL;


-- ---------------------------------------------------------------------
-- BLOCCO 2 — allineamento della tabella `migrations`
--
-- Registra come gia' applicate le migration le cui tabelle esistono gia'.
-- NON le esegue: e' corretto proprio perche' lo schema c'e' gia'.
-- Ogni INSERT e' condizionato, quindi rilanciare il blocco non duplica
-- nulla ed e' sicuro se qualcuna risultava gia' registrata.
--
-- L'ultima riga registra la migration di questo deploy, che il BLOCCO 1
-- ha appena applicato a mano.
-- ---------------------------------------------------------------------

SET @batch := (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations);

INSERT INTO migrations (migration, batch)
SELECT * FROM (
    SELECT '2025_08_29_000001_create_tournament_types_table'        AS m, @batch AS b
    UNION ALL SELECT '2025_08_29_000002_create_referee_career_history_table', @batch
    UNION ALL SELECT '2025_08_29_000003_create_tournaments_table',            @batch
    UNION ALL SELECT '2025_08_29_000004_create_notifications_table',          @batch
    UNION ALL SELECT '2025_08_29_000005_create_notification_clauses_table',   @batch
    UNION ALL SELECT '2025_08_29_000006_create_documents_table',              @batch
    UNION ALL SELECT '2025_08_29_000007_create_letter_templates_table',       @batch
    UNION ALL SELECT '2026_09_06_000001_make_tournaments_club_id_nullable',   @batch
) AS nuove
WHERE NOT EXISTS (
    SELECT 1 FROM migrations AS esistenti WHERE esistenti.migration = nuove.m
);


-- ---------------------------------------------------------------------
-- BLOCCO 3 — VERIFICA FINALE
-- ---------------------------------------------------------------------

SELECT migration, batch FROM migrations ORDER BY batch, migration;

SELECT
    COLUMN_NAME,
    IS_NULLABLE,
    COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'tournaments'
  AND COLUMN_NAME  = 'club_id';

-- Atteso DOPO l'intervento:
--   * club_id -> IS_NULLABLE = 'YES'
--   * l'elenco migrations contiene tutte le righe del BLOCCO 2
--
-- Da qui in avanti ogni nuova migration si applica normalmente: basta
-- caricarne il file e registrarla, oppure — se un giorno avrai modo di
-- lanciare artisan sul server — `php artisan migrate` partira' dalla
-- prima davvero mancante invece che da capo.
