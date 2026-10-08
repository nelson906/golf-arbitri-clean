-- ─────────────────────────────────────────────────────────────────────────
-- Tornei caricati da FIG = gia' notificati (decisione 2026-10-08)
--
-- Stesso effetto di `php artisan federgolf:mark-notified`, per Aruba dove
-- artisan non c'e': incollare TUTTO nella scheda SQL di phpMyAdmin ed
-- eseguire una volta. Si puo' rieseguire: i tornei gia' segnati si saltano.
--
-- Per ogni torneo con designazioni caricate da FIG (note «Import batch FIG…»
-- o «Importato da federgolf.it»):
--   nazionale -> notifica CRC (crc_referees), zonale -> notifica zonale;
--   gia' inviata (anche in parte): non si tocca;
--   bozza o non inviata dello stesso tipo: diventa inviata;
--   nessuna: se ne crea una inviata.
-- ─────────────────────────────────────────────────────────────────────────

DROP TEMPORARY TABLE IF EXISTS fig_t;
DROP TEMPORARY TABLE IF EXISTS fig_r;

-- 1. Tornei caricati da FIG e tipo di notifica
CREATE TEMPORARY TABLE fig_t AS
SELECT t.id AS tournament_id,
       CASE WHEN tt.is_national = 1 THEN 'crc_referees' ELSE NULL END AS ntype
FROM tournaments t
JOIN tournament_types tt ON tt.id = t.tournament_type_id
WHERE EXISTS (
    SELECT 1 FROM assignments a
    WHERE a.tournament_id = t.id
      AND (a.notes LIKE 'Import batch FIG%' OR a.notes = 'Importato da federgolf.it')
);

-- 2. Via quelli gia' inviati (anche in parte)
DELETE f FROM fig_t f
JOIN tournament_notifications n
  ON n.tournament_id = f.tournament_id
 AND n.notification_type <=> f.ntype
 AND n.status IN ('sent', 'partial');

-- 3. Arbitri di ogni torneo (sui nazionali la notifica CRC esclude gli osservatori)
CREATE TEMPORARY TABLE fig_r AS
SELECT a.tournament_id,
       GROUP_CONCAT(u.name ORDER BY a.id SEPARATOR ', ') AS lista,
       COUNT(*) AS quanti,
       MAX(a.assigned_at) AS quando
FROM assignments a
JOIN users u ON u.id = a.user_id
JOIN tournaments t ON t.id = a.tournament_id
JOIN tournament_types tt ON tt.id = t.tournament_type_id
WHERE tt.is_national = 0 OR a.role <> 'Osservatore'
GROUP BY a.tournament_id;

-- 4. Bozze e non inviate dello stesso tipo: diventano inviate
UPDATE tournament_notifications n
JOIN fig_t f ON f.tournament_id = n.tournament_id AND n.notification_type <=> f.ntype
JOIN fig_r r ON r.tournament_id = f.tournament_id
SET n.status = 'sent',
    n.sent_at = COALESCE(r.quando, NOW()),
    n.referee_list = r.lista,
    n.metadata = JSON_SET(COALESCE(n.metadata, JSON_OBJECT()), '$.source', 'Import FIG (SQL)', '$.fig', JSON_EXTRACT('true', '$')),
    n.updated_at = NOW()
WHERE n.status IN ('pending', 'failed');

DELETE f FROM fig_t f
JOIN tournament_notifications n
  ON n.tournament_id = f.tournament_id
 AND n.notification_type <=> f.ntype
 AND n.status = 'sent';

-- 5. Nessuna notifica: se ne crea una inviata
INSERT INTO tournament_notifications
    (tournament_id, notification_type, referee_list, status, sent_at, details, metadata, created_at, updated_at)
SELECT f.tournament_id, f.ntype, r.lista, 'sent', COALESCE(r.quando, NOW()),
       JSON_OBJECT('sent', r.quanti, 'arbitri', r.quanti, 'total_recipients', r.quanti,
                   'note', 'Comitato pubblicato da FIG: convocazioni gia'' fatte'),
       JSON_OBJECT('source', 'Import FIG (SQL)', 'fig', JSON_EXTRACT('true', '$')),
       NOW(), NOW()
FROM fig_t f
JOIN fig_r r ON r.tournament_id = f.tournament_id;

DROP TEMPORARY TABLE IF EXISTS fig_t;
DROP TEMPORARY TABLE IF EXISTS fig_r;
