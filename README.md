# golf-arbitri-clean

Gestione delle designazioni degli arbitri di golf per le Sezioni Zonali Regole (SZR) e il Comitato Regole e Campionati (CRC) della Federazione Italiana Golf.

L'applicazione regge un ciclo: l'arbitro dichiara le disponibilità, l'amministratore designa il comitato di gara, l'amministratore comunica la designazione al circolo (tornei zonali) o al Comitato Campionati (tornei nazionali).

## Ruoli

| Ruolo | Cosa fa |
| --- | --- |
| Arbitro | Dichiara le disponibilità, vede le proprie designazioni e il curriculum |
| Admin di zona (SZR) | Tornei zonali dall'inizio alla lettera al circolo; osservatori dei nazionali della zona |
| Admin nazionale (CRC) | Arbitri e Direttore di Torneo dei tornei nazionali |
| Super admin | Configurazione (zone, tipi torneo, email istituzionali, clausole), archiviazione annuale |

Che un torneo sia nazionale lo decide solo il tipo torneo (`is_national`).

## Requisiti

- PHP 8.5 (`composer.lock` risolto su 8.5), Composer 2
- MySQL 8 (il nome del database di test deve contenere `test`)
- Node 20 per la compilazione degli asset (Vite)

## Installazione locale

```bash
git clone https://github.com/nelson906/golf-arbitri-clean.git
cd golf-arbitri-clean
composer install
npm ci && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate
```

## Controlli di qualità

```bash
php artisan test                                                        # PHPUnit
vendor/bin/phpstan analyse -c phpstan-strict.neon --level=9 --memory-limit=1G
npm run qa                                                              # ESLint, type-check, Vitest
```

La CI (`.github/workflows/ci.yml`) esegue gli stessi controlli a ogni push su `main` e su ogni pull request.

## Documentazione

- `docs/guides/archiviazione-anno.md` — chiusura della stagione
- `docs/deploy/aruba-deploy-checklist.md` — caricamento su Aruba (hosting condiviso, senza SSH)
- `docs/STORICO.md` — storia del progetto

## Licenza

[MIT](LICENSE)
