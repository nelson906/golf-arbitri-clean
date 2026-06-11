# Soft-launch presidiato — checklist operativa

*Creata il 2026-06-11. Prima campagna di notifiche reali dopo il refactor "mail unica" del 2026-06-10. Da eseguire UNA volta; superata, il sistema si usa in autonomia.*

Tempo stimato: mezza giornata, spalmata su 2 momenti (prep + verifica ricezione).

---

## Fase 0 — Prerequisiti (in locale, MAMP)

- [ ] `php artisan test tests/Feature/Notifications/` → tutto verde (ricorda: restart Apache completo dopo modifiche a service/controller, OPcache MAMP)
- [ ] `php artisan test tests/Feature/Notifications/PreflightRecipientsTest.php` → verde (nuovo, 2026-06-11)
- [ ] Eseguite le query di diagnosi di `database/bonifica/2026-06-11_bonifica_dati.sql` su **produzione** → query 1 e 2 a zero righe (o UPDATE applicati)
- [ ] Deploy su Aruba dell'ultima versione (pre-flight incluso)

## Fase 1 — Prova generale in locale con Mailpit

- [ ] Mailpit attivo (`MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`)
- [ ] Scegliere in locale un torneo **zonale** con 2+ arbitri assegnati
- [ ] Aprire il form di invio → controllare il pannello **Pre-flight destinatari** (deve essere ✅ verde)
- [ ] Inviare → in Mailpit verificare: **UNA** mail, TO = circolo, CC = arbitri + sezione + istituzionali selezionati, **2 allegati** (Convocazione + Lettera circolo)
- [ ] Ripetere con un torneo **nazionale** → destinatari CRC + osservatori di zona corretti
- [ ] Ripetere un **reinvio** dello stesso record → passa dal form, destinatari = scelte del form (non la colonna stantia — fix D1)

## Fase 2 — Campagna campione in produzione

Scegliere 2 tornei reali a basso rischio (date lontane, circoli collaborativi): 1 zonale + 1 nazionale.

- [ ] **Zonale**: form → pre-flight verde → aggiungere la PROPRIA email in "Email Aggiuntive" (così ricevi la copia esatta) → inviare
- [ ] Verificare la propria copia: oggetto, corpo, allegati apribili, destinatari visibili in CC corretti
- [ ] Telefonata/messaggio al circolo: "vi è arrivata la convocazione con 2 allegati?"
- [ ] Conferma da almeno 1 arbitro in CC
- [ ] **Nazionale**: stesso giro con torneo CRC
- [ ] Controllare lo **status** dei 2 record in `/admin/tournament-notifications`: deve essere `sent` (se `partial` → leggere il warning con la causa, fix D3)
- [ ] Controllare `storage/logs/laravel.log` su Aruba: nessun `Log::warning` di destinatari skippati inattesi

## Fase 3 — Esito

- [ ] Tutto ok → sistema in autonomia; annotare la data in `docs/STORICO.md`
- [ ] Problemi → aprire i log, confrontare con la riga `Normalized recipients for sending`, query diagnostica §3 del file bonifica per i record FIG

## Cosa NON fare

- Non usare per il campione tornei con record FIG importati incompleti (prima reinviarli via form: ERR_MISSING_RECIPIENTS è previsto)
- Non inviare campagne massive prima che entrambi i campioni risultino confermati dai riceventi
- Non bypassare il pre-flight rosso "tanto poi correggo": il destinatario scartato non riceve nulla e nessuno se ne accorge
