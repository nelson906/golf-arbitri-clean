-- ==========================================
-- SQL PER AGGIUNGERE CAMPI NOTIFICATION
-- DA ESEGUIRE SU ARUBA VIA PHPMYADMIN
-- ==========================================
--
-- Questo script aggiunge i campi necessari per la gestione
-- delle notifiche alle email istituzionali.
--
-- ISTRUZIONI:
-- 1. Accedi a phpMyAdmin su Aruba
-- 2. Seleziona il database
-- 3. Vai su "SQL"
-- 4. Copia e incolla questo codice
-- 5. Clicca "Esegui"
--

ALTER TABLE `institutional_emails`
ADD COLUMN `receive_all_notifications` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`,
ADD COLUMN `notification_types` JSON NULL AFTER `receive_all_notifications`;

-- Verifica che le colonne siano state create
-- DESCRIBE `institutional_emails`;
