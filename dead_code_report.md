# Report Dead Code — Progetto
_Analisi eseguita il 2026-03-25 02:17_

---

## Sommario Esecutivo

- **Linguaggi analizzati**: php
- **Finding totali**: 824
- **Finding reali** (esclusi falsi positivi noti): 823
- **Probabili falsi positivi**: 1

## 🎯 Top Priorità (Alta Confidenza)

Questi sono i candidati più sicuri per la rimozione:

| File | Riga | Tipo | Nome | Confidenza |
|------|------|------|------|-----------|
| `app/Console/Commands/ArchiveCareerYear.php` | 7 | class | `ArchiveCareerYear` | 85% |
| `app/Console/Commands/ManageCareerEntry.php` | 10 | class | `ManageCareerEntry` | 85% |
| `app/Http/Middleware/DocumentAccess.php` | 8 | class | `DocumentAccess` | 85% |
| `app/Http/Middleware/RefereeOnly.php` | 8 | class | `RefereeOnly` | 85% |
| `app/Http/Requests/AssignmentRequest.php` | 14 | class | `AssignmentRequest` | 85% |
| `app/Http/Requests/AvailabilityRequest.php` | 6 | class | `AvailabilityRequest` | 85% |
| `app/Http/Requests/DocumentUploadRequest.php` | 6 | class | `DocumentUploadRequest` | 85% |
| `app/Http/Requests/RefereeRequest.php` | 7 | class | `RefereeRequest` | 85% |
| `app/Http/Requests/TournamentTypeRequest.php` | 7 | class | `TournamentTypeRequest` | 85% |
| `app/View/Components/AppLayout.php` | 7 | class | `AppLayout` | 85% |

---

## PHP (tool: custom (regex))

**Finding totali**: 824 | **Reali**: 823 | **Falsi positivi noti**: 1

**Per tipo:**

- function: 741
- class: 66
- const: 16
- trait: 1

### Classs non usati (66)

| File | Riga | Nome | Confidenza |
|------|------|------|-----------|
| `app/Console/Commands/ArchiveCareerYear.php` | 7 | `ArchiveCareerYear` | 85% |
| `app/Console/Commands/ManageCareerEntry.php` | 10 | `ManageCareerEntry` | 85% |
| `app/Http/Middleware/DocumentAccess.php` | 8 | `DocumentAccess` | 85% |
| `app/Http/Middleware/RefereeOnly.php` | 8 | `RefereeOnly` | 85% |
| `app/Http/Requests/AssignmentRequest.php` | 14 | `AssignmentRequest` | 85% |
| `app/Http/Requests/AvailabilityRequest.php` | 6 | `AvailabilityRequest` | 85% |
| `app/Http/Requests/DocumentUploadRequest.php` | 6 | `DocumentUploadRequest` | 85% |
| `app/Http/Requests/RefereeRequest.php` | 7 | `RefereeRequest` | 85% |
| `app/Http/Requests/TournamentTypeRequest.php` | 7 | `TournamentTypeRequest` | 85% |
| `app/View/Components/AppLayout.php` | 7 | `AppLayout` | 85% |
| `app/View/Components/GuestLayout.php` | 7 | `GuestLayout` | 85% |
| `database/factories/AssignmentFactory.php` | 13 | `AssignmentFactory` | 85% |
| `database/factories/TournamentTypeFactory.php` | 11 | `TournamentTypeFactory` | 85% |
| `database/factories/ZoneFactory.php` | 11 | `ZoneFactory` | 85% |
| `database/seeders/DatabaseSeeder.php` | 7 | `DatabaseSeeder` | 85% |
| `database/seeders/Tournaments2026Seeder.php` | 13 | `Tournaments2026Seeder` | 85% |
| `docs/legacy/ScrapeFedergolfIscritti.php` | 8 | `ScrapeFedergolfIscritti` | 85% |
| `...Feature/Admin/AssignmentManagementTest.php` | 8 | `AssignmentManagementTest` | 85% |
| `...ure/Admin/EnumMiddlewareRegressionTest.php` | 21 | `EnumMiddlewareRegressionTest` | 85% |
| `...Feature/Admin/TournamentManagementTest.php` | 9 | `TournamentManagementTest` | 85% |
| `tests/Feature/Auth/AuthenticationTest.php` | 8 | `AuthenticationTest` | 85% |
| `tests/Feature/Auth/EmailVerificationTest.php` | 11 | `EmailVerificationTest` | 85% |
| `.../Feature/Auth/PasswordConfirmationTest.php` | 8 | `PasswordConfirmationTest` | 85% |
| `tests/Feature/Auth/PasswordResetTest.php` | 10 | `PasswordResetTest` | 85% |
| `tests/Feature/Auth/PasswordUpdateTest.php` | 9 | `PasswordUpdateTest` | 85% |
| `tests/Feature/Auth/RegistrationTest.php` | 7 | `RegistrationTest` | 85% |
| `...ature/CareerHistoryGiorniEffettiviTest.php` | 13 | `CareerHistoryGiorniEffettiviTest` | 85% |
| `tests/Feature/DatabaseConnectionTest.php` | 7 | `DatabaseConnectionTest` | 85% |
| `tests/Feature/ProfileTest.php` | 8 | `ProfileTest` | 85% |
| `...ure/Referee/AvailabilityManagementTest.php` | 8 | `AvailabilityManagementTest` | 85% |
_...e altri 36 finding. Analizza il JSON raw per la lista completa._

### Consts non usati (16)

| File | Riga | Nome | Confidenza |
|------|------|------|-----------|
| `app/Services/TournamentColorService.php` | 90 | `TYPE_COLORS_MAP` | 85% |
| `app/Models/Communication.php` | 76 | `STATUS_EXPIRED` | 55% |
| `app/Models/Document.php` | 88 | `CATEGORY_GENERAL` | 55% |
| `app/Models/Document.php` | 89 | `CATEGORY_TOURNAMENT` | 55% |
| `app/Models/Document.php` | 91 | `CATEGORY_REGULATION` | 55% |
| `app/Models/Document.php` | 93 | `CATEGORY_FORM` | 55% |
| `app/Models/Document.php` | 95 | `CATEGORY_TEMPLATE` | 55% |
| `app/Models/Document.php` | 116 | `TYPE_OTHER` | 55% |
| `app/Models/Notification.php` | 125 | `TYPE_REFEREE` | 55% |
| `app/Models/Notification.php` | 126 | `TYPE_CLUB` | 55% |
| `app/Models/Notification.php` | 128 | `TYPE_INSTITUTIONAL` | 55% |
| `app/Models/Notification.php` | 130 | `TYPE_CUSTOM` | 55% |
| `app/Models/User.php` | 95 | `CATEGORY_MASCHILE` | 55% |
| `app/Models/User.php` | 96 | `CATEGORY_FEMMINILE` | 55% |
| `app/Models/User.php` | 98 | `CATEGORY_MISTO` | 55% |
| `app/Services/TournamentColorService.php` | 86 | `DEFAULT_BORDER` | 55% |

### Functions non usati (741)

| File | Riga | Nome | Confidenza |
|------|------|------|-----------|
| `app/Helpers/RefereeLevelsHelper.php` | 23 | `getDbEnumValues` | 85% |
| `app/Helpers/SystemOperations.php` | 147 | `composerInstall` | 85% |
| `app/Helpers/SystemOperations.php` | 172 | `composerUpdate` | 85% |
| `app/Helpers/SystemOperations.php` | 245 | `getLatestCommit` | 85% |
| `app/Helpers/SystemOperations.php` | 272 | `gitPull` | 85% |
| `app/Helpers/SystemOperations.php` | 462 | `cleanOldFiles` | 85% |
| `...Controllers/Admin/TournamentController.php` | 301 | `updateStatus` | 85% |
| `...Controllers/Admin/TournamentController.php` | 445 | `getclubsByZone` | 85% |
| `...Controllers/Admin/TournamentController.php` | 462 | `getEntityName` | 85% |
| `...Controllers/Admin/TournamentController.php` | 466 | `getIndexRoute` | 85% |
| `...Controllers/Admin/TournamentController.php` | 471 | `getDeleteErrorMessage` | 85% |
| `...Controllers/Admin/TournamentController.php` | 476 | `canBeDeleted` | 85% |
| `...Controllers/Admin/TournamentController.php` | 481 | `checkAccess` | 85% |
| `...rollers/Admin/TournamentTypeController.php` | 150 | `updateOrder` | 85% |
| `...ollers/SuperAdmin/MonitoringController.php` | 47 | `healthCheck` | 85% |
| `...p/Controllers/User/FedergolfController.php` | 12 | `searchCompetitions` | 85% |
| `app/Http/Requests/AssignmentRequest.php` | 135 | `resolvedRole` | 85% |
| `app/Http/Requests/TournamentTypeRequest.php` | 222 | `withValidator` | 85% |
| `app/Http/Requests/TournamentTypeRequest.php` | 298 | `getValidatedSettings` | 85% |
| `app/Mail/TournamentNotificationMail.php` | 81 | `fromMetadata` | 85% |
| `app/Models/Communication.php` | 120 | `scopePublished` | 85% |
| `app/Models/Communication.php` | 154 | `getPriorityBadgeAttribute` | 85% |
| `app/Models/Communication.php` | 168 | `getTypeBadgeAttribute` | 85% |
| `app/Models/Document.php` | 155 | `getFileSizeHumanAttribute` | 85% |
| `app/Models/Document.php` | 170 | `getFileUrlAttribute` | 85% |
| `app/Models/Document.php` | 178 | `getDownloadUrlAttribute` | 85% |
| `app/Models/Document.php` | 186 | `getTypeIconAttribute` | 85% |
| `app/Models/Document.php` | 201 | `scopePublic` | 85% |
| `app/Models/Document.php` | 209 | `scopeCategory` | 85% |
| `app/Models/InstitutionalEmail.php` | 108 | `scopeOfCategory` | 85% |
_...e altri 711 finding. Analizza il JSON raw per la lista completa._

**⚠️ Avvertenze:**

> L'analisi PHP è basata su regex e ha limitazioni con namespace, DI container e hook dinamici.
> Verifica sempre manualmente prima di rimuovere qualsiasi simbolo.
> Interfacce e trait possono risultare inutilizzati anche se sono contratti di tipo.

---

## ⚠️ Probabili Falsi Positivi (esclusi dal conteggio)

Questi elementi sono stati rilevati ma probabilmente **non** sono da rimuovere:

| File | Tipo | Nome | Motivo |
|------|------|------|--------|
| `tests/CreatesApplication.php` | trait | `CreatesApplication` | interface/trait usato indirettamente |

---

## Guida alla Rimozione Sicura

**Prima di rimuovere qualsiasi elemento:**
1. Cerca il nome con `grep -r 'nomeElemento' .` per confermare che non sia usato
2. Controlla se è esportato e potrebbe essere usato da pacchetti esterni
3. Verifica se è usato via reflection, `getattr()`, `eval()`, o import dinamici
4. Esegui i test dopo ogni rimozione

**Pattern sicuri per la rimozione batch:**
```bash
# Cerca tutti gli usi di una funzione prima di rimuoverla
grep -rn 'nome_funzione' . --include='*.py'
grep -rn 'nomeMetodo' . --include='*.ts'
grep -rn 'nomeMetodo' . --include='*.php'
```
