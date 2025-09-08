# Correzioni Sistema Notifiche - RISOLTE

## Problema Identificato
Gli arbitri ricevevano la lettera al circolo (DOCX) invece del PDF della convocazione.

## Cause del Problema
1. **Formato nomi file inconsistente**: Il PDF veniva generato con formato `convocazione_nome-torneo.pdf` mentre il DOCX era `convocazione_ID_Nome_Torneo.docx`
2. **Confusione negli allegati**: Il sistema confondeva quale file allegare a chi
3. **Metodi duplicati** nel NotificationController causavano comportamenti incoerenti

## Soluzioni Implementate

### 1. Uniformato il formato dei nomi file
- Modificato `generateConvocationPDF()` in DocumentGenerationService per usare lo stesso formato del DOCX:
  ```php
  $filename = "convocazione_{$tournament->id}_{$tournamentName}.pdf";
  ```

### 2. Corretto il metodo send() nel NotificationController
- **Arbitri ricevono SOLO il PDF** della convocazione
- **Circolo riceve ENTRAMBI**: PDF convocazione + DOCX lettera circolo
- Aggiunti log dettagliati per tracciare gli invii

### 3. Modificato generateDocument()
- Quando si genera la convocazione, vengono creati sia DOCX che PDF
- Entrambi i file vengono salvati con lo stesso formato nome

### 4. Rimossi i metodi duplicati
- Eliminati tutti i metodi duplicati dal NotificationController
- Eliminato TournamentNotificationController non più utilizzato

## Flusso Corretto Attuale

1. **Generazione Documenti**:
   - Convocazione DOCX: `convocazione_ID_Nome_Torneo.docx`
   - Convocazione PDF: `convocazione_ID_Nome_Torneo.pdf` 
   - Lettera circolo DOCX: `lettera_circolo_ID_Nome_Torneo.docx`

2. **Invio Email**:
   - **Arbitri**: Ricevono SOLO il PDF della convocazione
   - **Circolo**: Riceve sia PDF convocazione che DOCX lettera circolo
   - **Istituzionali**: Ricevono solo email senza allegati

## File Modificati
1. `/app/Services/DocumentGenerationService.php` - Metodo generateConvocationPDF()
2. `/app/Http/Controllers/Admin/NotificationController.php` - Metodi send() e generateDocument()
3. Rimossi vecchi file PDF con formato nome errato

## Test Consigliati
1. Generare nuovi documenti per un torneo
2. Inviare le notifiche
3. Verificare nei log che:
   - Agli arbitri venga allegato SOLO il PDF
   - Al circolo vengano allegati ENTRAMBI i documenti
4. Controllare le email ricevute per confermare gli allegati corretti
