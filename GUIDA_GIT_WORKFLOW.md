# 📘 Guida Workflow Git - Golf Arbitri

Guida semplice per gestire modifiche e branch con Claude Code.

---

## 🎯 Workflow Completo in 5 Passi

### 1️⃣ INIZIO - Nuovo Task/Feature

```bash
# Assicurati di essere su main aggiornato
git checkout main
git pull origin main

# Claude creerà automaticamente un nuovo branch tipo:
# claude/nome-feature-XXXXX
```

**✅ Fatto automaticamente da Claude!**

---

### 2️⃣ SVILUPPO - Claude lavora

Claude farà:
- ✅ Modifiche ai file
- ✅ Commit delle modifiche
- ✅ Push sul branch di feature

**Tu non devi fare nulla, solo rispondere alle domande di Claude!**

---

### 3️⃣ REVIEW - Verifica le modifiche

**Sul tuo Mac, in VSCode:**

```bash
# 1. Scarica il branch creato da Claude
git fetch origin

# 2. Passa al branch con le modifiche
git checkout claude/nome-feature-XXXXX

# 3. Testa l'applicazione
# Verifica che tutto funzioni come ti aspetti
```

**In basso a sinistra in VSCode** devi vedere il nome del branch con le modifiche!

---

### 4️⃣ MERGE - Integra nel main

**Opzione A: Via GitHub (CONSIGLIATA)**

1. Vai su: https://github.com/nelson906/golf-arbitri-clean/pulls
2. Clicca su **"Compare & pull request"** (banner giallo)
3. Clicca **"Create pull request"**
4. Clicca **"Merge pull request"**
5. Clicca **"Confirm merge"**
6. Clicca **"Delete branch"** (pulisce il branch remoto)

**Opzione B: Via Terminale (se hai fretta)**

```bash
# 1. Vai su main
git checkout main

# 2. Scarica ultimi aggiornamenti
git pull origin main

# 3. Fai il merge
git merge claude/nome-feature-XXXXX

# 4. Pusha su GitHub
git push origin main
```

⚠️ **ATTENZIONE**: L'Opzione B potrebbe fallire se `main` è protetto!

---

### 5️⃣ PULIZIA - Cancella branch vecchi

**Dopo il merge, pulisci:**

```bash
# 1. Torna su main
git checkout main

# 2. Aggiorna main locale
git pull origin main

# 3. Cancella branch locale
git branch -d claude/nome-feature-XXXXX

# 4. Pulisci riferimenti remoti obsoleti
git fetch --prune

# 5. Verifica che sia tutto pulito
git branch -a
# Dovresti vedere solo 'main' e 'remotes/origin/main'
```

---

## 🆘 Problemi Comuni e Soluzioni

### ❌ "VSCode non mostra le modifiche"

```bash
# Nel terminale integrato di VSCode:
git fetch origin
git checkout claude/nome-feature-XXXXX

# Poi in VSCode:
# 1. Chiudi il file aperto
# 2. Riapri il file dal file explorer
# O semplicemente: Ctrl+Shift+P → "Reload Window"
```

---

### ❌ "Non vedo il branch creato da Claude"

```bash
# Scarica tutti i branch da GitHub
git fetch origin

# Vedi tutti i branch disponibili
git branch -a

# Passa al branch che vuoi
git checkout nome-branch
```

---

### ❌ "Ho fatto commit su main per errore"

```bash
# NON PANICO! Annulla l'ultimo commit (senza perdere modifiche)
git reset --soft HEAD~1

# Crea il branch corretto
git checkout -b claude/fix-XXXXX

# Fai di nuovo il commit
git add .
git commit -m "messaggio"
git push -u origin claude/fix-XXXXX
```

---

### ❌ "Ho troppi branch vecchi"

```bash
# Cancella tutti i branch locali tranne main
git checkout main
git branch | grep -v "main" | xargs git branch -D

# Pulisci riferimenti remoti
git fetch --prune
```

---

## 📝 Comandi Essenziali (Cheat Sheet)

### Vedere dove sei
```bash
git status                    # Dove sono? Cosa ho modificato?
git branch                    # Quali branch ho localmente?
git branch -a                 # Tutti i branch (anche remoti)
git log --oneline -5          # Ultimi 5 commit
```

### Cambiare branch
```bash
git checkout main                      # Vai su main
git checkout claude/feature-XXXXX      # Vai su un branch di feature
```

### Aggiornare
```bash
git fetch origin              # Scarica info da GitHub (senza modificare file)
git pull origin main          # Scarica E applica modifiche da GitHub
```

### Verificare modifiche
```bash
git diff                      # Cosa ho modificato?
git log --oneline -3          # Ultimi 3 commit
```

---

## 🎓 Regole d'Oro

1. **MAI lavorare direttamente su `main`** → Sempre su branch di feature
2. **SEMPRE fare `git pull`** prima di iniziare un nuovo task
3. **SEMPRE verificare** su quale branch sei: `git status`
4. **PULIRE i branch** dopo il merge (non accumulare branch vecchi)
5. **In caso di dubbio** → chiedi a Claude prima di fare comandi pericolosi!

---

## 🔄 Workflow Visivo

```
main (protetto)
 │
 ├─── claude/feature-A ──→ sviluppo ──→ merge ──→ cancella branch
 │
 ├─── claude/feature-B ──→ sviluppo ──→ merge ──→ cancella branch
 │
 └─── claude/feature-C ──→ sviluppo ──→ (in corso...)
```

Ogni feature = 1 branch separato → Merge → Pulizia → Ricomincia

---

## 💡 Tips Pro

### VSCode - Indicatore Branch
In basso a sinistra vedi sempre su quale branch sei.
Clicca per cambiare branch velocemente!

### GitHub Desktop (Alternativa)
Se preferisci un'interfaccia grafica:
1. Scarica GitHub Desktop
2. Clone del repository
3. Gestisci branch visualmente

### Alias Utili (Opzionale)
Aggiungi al tuo `~/.zshrc` o `~/.bashrc`:

```bash
alias gs='git status'
alias gc='git checkout'
alias gp='git pull origin main'
alias gb='git branch -a'
alias gclean='git fetch --prune'
```

---

## 📞 Quando Chiedere Aiuto a Claude

- ❓ "Non sono sicuro su quale branch sono"
- ❓ "Ho fatto un pasticcio, come torno indietro?"
- ❓ "Come cancello tutti i branch vecchi?"
- ❓ "Voglio annullare l'ultimo commit"

**Claude può guidarti passo passo!**

---

**📅 Ultimo aggiornamento:** Gennaio 2026
**📧 Domande?** Chiedi a Claude durante la sessione!
