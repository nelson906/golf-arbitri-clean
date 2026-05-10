# Archivio audit — golf-arbitri

Cartella di archivio per documenti di audit superati. Mantenuti solo per
storicità (riferimenti incrociati, contesto delle decisioni passate).

## Contenuto

| File | Data | Stato |
|---|---|---|
| `AUDIT_report.md` | apr 2026 | Audit iniziale — superato dall'architect review del 7 aprile |
| `AUDIT_report_v2.md` | apr 2026 | 2ª iterazione — superato |
| `AUDIT_report_v3.md` | apr 2026 | 3ª iterazione — superato dall'architect review del 7 aprile |
| `SPEC_ricostruzione.md` | mar 2026 | Specifiche per ricostruzione da zero su Aruba — non più in piano (oggi si patcha l'esistente) |

## Documenti vivi (in root)

- `AUDIT_architect_review.md` — fonte di verità (audit 7 aprile + delta 9 maggio in coda)
- `AUDIT_notifications_v1.md` — audit specifico flusso notifiche
- `DeepTest_Report.md` — output strumento di test approfondito
- `PIANO_INTERVENTO.md` — stato di esecuzione interventi
- `dead_code_report.md` — analisi codice morto
- `README.md` — README principale del progetto

## Quando archiviare nuovi documenti qui

Quando un audit/report:
- è stato sostituito da una versione più recente che ne incorpora i finding
- riferisce uno stato del codice non più rappresentativo
- non guida più decisioni attive

Non archiviare automaticamente per data: alcuni audit "vecchi" sono ancora la
fonte di verità (vedi `AUDIT_architect_review.md`, aggiornato il 9 maggio 2026
in coda al documento del 7 aprile).
