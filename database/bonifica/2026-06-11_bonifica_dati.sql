-- =====================================================================
-- BONIFICA DATI PRODUZIONE — 2026-06-11
-- Origine: risk assessment 2026-05-30 (R2) + analisi 2026-06-10 (D2)
-- Eseguire su: arbitrigolf.golfrating.it (e in locale MAMP per allineare)
-- ORDINE: prima tutte le SELECT (diagnosi), POI gli UPDATE con i valori veri.
-- Fare backup prima: php artisan db:backup  (o export da phpMyAdmin Aruba)
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. DIAGNOSI: zone con email non valida (nome al posto dell'email)
-- ---------------------------------------------------------------------
SELECT id, code, name, email
FROM zones
WHERE email IS NULL
   OR email = ''
   OR email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$';

-- FIX (compilare un UPDATE per ogni riga emersa sopra, con l'email reale
-- della sezione zonale. NON inventare: chiedere alla federazione se manca):
-- UPDATE zones SET email = 'szrX@federgolf.it' WHERE id = <ID>;

-- ---------------------------------------------------------------------
-- 2. DIAGNOSI: circoli con email vuota/invalida
--    (colonna NOT NULL → lo sporco è stringa vuota, non NULL.
--     Un circolo senza email valida = TO mancante nella notifica zonale)
-- ---------------------------------------------------------------------
SELECT id, name, email
FROM clubs
WHERE email = ''
   OR email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$';

-- FIX:
-- UPDATE clubs SET email = '<email reale circolo>' WHERE id = <ID>;

-- ---------------------------------------------------------------------
-- 3. DIAGNOSI: record FIG importati incompleti
--    (metadata.source presente ma senza recipients → il send ora li
--     rifiuta con ERR_MISSING_RECIPIENTS e reindirizza al form: OK by design.
--     Questa query serve solo a sapere QUANTI sono e quali tornei toccano)
-- ---------------------------------------------------------------------
SELECT tn.id,
       tn.tournament_id,
       t.name AS torneo,
       tn.status,
       tn.documents,
       JSON_EXTRACT(tn.metadata, '$.source')     AS fig_source,
       JSON_EXTRACT(tn.metadata, '$.recipients') AS meta_recipients,
       tn.recipients                              AS colonna_recipients
FROM tournament_notifications tn
JOIN tournaments t ON t.id = tn.tournament_id
WHERE JSON_EXTRACT(tn.metadata, '$.source') LIKE '%FIG%'
  AND (JSON_EXTRACT(tn.metadata, '$.recipients') IS NULL OR tn.documents IS NULL);

-- Nessun UPDATE qui: i record FIG si "riparano" da soli al primo reinvio
-- via form prepare_notification (l'admin compila destinatari e contenuti).

-- ---------------------------------------------------------------------
-- 4. DIAGNOSI: colonne recipients "avvelenate" da invii pre-refactor
--    (difetto D1, ora innocuo: la colonna non è più riletta come input.
--     Solo censimento, nessuna azione necessaria)
-- ---------------------------------------------------------------------
SELECT id, tournament_id, status,
       JSON_EXTRACT(recipients, '$.club')          AS club_flag,
       JSON_EXTRACT(recipients, '$.institutional') AS inst
FROM tournament_notifications
WHERE recipients IS NOT NULL
  AND (JSON_EXTRACT(recipients, '$.club') = false
       OR JSON_LENGTH(JSON_EXTRACT(recipients, '$.institutional')) = 0);

-- ---------------------------------------------------------------------
-- 5. VERIFICA FINALE (dopo gli UPDATE): le query 1 e 2 devono tornare 0 righe
-- ---------------------------------------------------------------------
