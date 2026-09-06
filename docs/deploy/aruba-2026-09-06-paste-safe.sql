/* golf-arbitri-clean - allineamento DB Aruba - 2026-09-06 */
/* Niente information_schema: su Aruba e' negato all'utente del database. */
/* Le verifiche usano SHOW, che funziona con i privilegi normali. */
/* Niente commenti con -- : sopravvive anche a un paste che perde gli a capo. */

/* ================= PASSO 1 - fotografia. Non modifica nulla. ================= */

SHOW COLUMNS FROM tournaments LIKE 'club_id';

SELECT migration, batch FROM migrations ORDER BY batch, migration;

/* Nella riga di SHOW COLUMNS guarda la colonna Null:                    */
/*   NO  -> il PASSO 2 va eseguito                                       */
/*   YES -> il PASSO 2 e' gia' stato applicato, saltalo                  */

/* ================= PASSO 2 - club_id diventa nullable ================= */

ALTER TABLE tournaments MODIFY club_id BIGINT UNSIGNED NULL;

/* ================= PASSO 3 - registra le migration gia' applicate ================= */
/* Non le esegue: le tabelle esistono gia'. Rilanciabile senza duplicare. */

SET @batch := (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations);

INSERT INTO migrations (migration, batch) SELECT * FROM (SELECT '2025_08_29_000001_create_tournament_types_table' AS m, @batch AS b UNION ALL SELECT '2025_08_29_000002_create_referee_career_history_table', @batch UNION ALL SELECT '2025_08_29_000003_create_tournaments_table', @batch UNION ALL SELECT '2025_08_29_000004_create_notifications_table', @batch UNION ALL SELECT '2025_08_29_000005_create_notification_clauses_table', @batch UNION ALL SELECT '2025_08_29_000006_create_documents_table', @batch UNION ALL SELECT '2025_08_29_000007_create_letter_templates_table', @batch UNION ALL SELECT '2026_09_06_000001_make_tournaments_club_id_nullable', @batch) AS nuove WHERE NOT EXISTS (SELECT 1 FROM migrations AS esistenti WHERE esistenti.migration = nuove.m);

/* ================= PASSO 4 - verifica finale ================= */
/* Atteso: club_id con Null = YES, e le 8 migration presenti nell'elenco. */

SHOW COLUMNS FROM tournaments LIKE 'club_id';

SELECT migration, batch FROM migrations ORDER BY batch, migration;

/* ================= Se il PASSO 2 fallisce per la foreign key ================= */
/* Prima trova il nome del vincolo: */
/*   SHOW CREATE TABLE tournaments;  */
/* cerca la riga CONSTRAINT `...` FOREIGN KEY (`club_id`) e usa quel nome qui: */
/*   ALTER TABLE tournaments DROP FOREIGN KEY tournaments_club_id_foreign; */
/*   ALTER TABLE tournaments MODIFY club_id BIGINT UNSIGNED NULL; */
/*   ALTER TABLE tournaments ADD CONSTRAINT tournaments_club_id_foreign FOREIGN KEY (club_id) REFERENCES clubs(id); */
