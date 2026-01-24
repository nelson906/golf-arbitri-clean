# Guida ai Test - Golf Arbitri

## ⚠️ IMPORTANTE: Protezione Database

I test sono ora configurati per **NON distruggere** il database di sviluppo.

### Configurazione Sicura

**1. Database di Test Separato (SQLite in memoria)**

Il file `phpunit.xml` è configurato per usare SQLite in memoria:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

**Vantaggi:**
- ✅ Veloce (tutto in RAM)
- ✅ Isolato (non tocca il DB di sviluppo)
- ✅ Pulito (ricreato per ogni test)
- ✅ Sicuro (impossibile distruggere dati reali)

**2. Protezione Automatica**

Il `TestCase` base include un controllo di sicurezza:

```php
protected function assertDatabaseIsTest(): void
{
    // Verifica che il database usato sia di test
    // Blocca l'esecuzione se rileva il database di sviluppo
}
```

---

## 🧪 Eseguire i Test

### Test Completi (Raccomandato)
```bash
php artisan test
```

Usa automaticamente SQLite in memoria (configurato in `phpunit.xml`).

### Test Specifici
```bash
# Singolo file
php artisan test tests/Feature/NotificationCycleTest.php

# Singolo metodo
php artisan test --filter=test_notification_cycle

# Con output verboso
php artisan test --verbose
```

### Test con Coverage
```bash
php artisan test --coverage
```

---

## 📝 Scrivere Test Sicuri

### 1. Usa RefreshDatabase per Test Isolati

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class MyTest extends TestCase
{
    use RefreshDatabase;

    public function test_example()
    {
        // Il database viene ricreato per questo test
        // Usa SQLite in memoria (veloce e sicuro)
    }
}
```

### 2. Usa DatabaseTransactions per Test su DB Reale

```php
use Illuminate\Foundation\Testing\DatabaseTransactions;

class MyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_example()
    {
        // Tutte le modifiche vengono rollback automaticamente
        // Utile se devi testare su MySQL/MariaDB
    }
}
```

### 3. NON Usare DatabaseMigrations in Produzione

```php
// ❌ PERICOLOSO - Non usare in produzione
use Illuminate\Foundation\Testing\DatabaseMigrations;

// ✅ SICURO - Usa invece RefreshDatabase
use Illuminate\Foundation\Testing\RefreshDatabase;
```

---

## 🔧 Configurazione Avanzata

### Test su MySQL/MariaDB (Opzionale)

Se hai bisogno di testare su un database MySQL separato:

**1. Crea database di test:**
```sql
CREATE DATABASE golf_arbitri_test;
```

**2. Configura `.env.testing`:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=golf_arbitri_test
DB_USERNAME=root
DB_PASSWORD=your_password
```

**3. Esegui test:**
```bash
php artisan test
```

**⚠️ IMPORTANTE:** Il database deve contenere "test" nel nome per passare il controllo di sicurezza.

---

## 🛡️ Protezioni Implementate

### 1. Controllo Nome Database
```php
// In TestCase::assertDatabaseIsTest()
$this->assertTrue(
    str_contains(strtolower($dbName), 'test') || $dbName === ':memory:',
    "⚠️ ATTENZIONE: Database non di test rilevato!"
);
```

### 2. Configurazione phpunit.xml
- Forza `DB_CONNECTION=sqlite`
- Forza `DB_DATABASE=:memory:`
- Impossibile sovrascrivere senza modificare il file

### 3. File .env.testing Separato
- Configurazione dedicata ai test
- Non influenza `.env` di sviluppo
- Può essere versionato (senza credenziali sensibili)

---

## 📊 Struttura Test

```
tests/
├── CreatesApplication.php    # Trait per bootstrap app
├── TestCase.php              # Base class con protezioni
├── Feature/                  # Test end-to-end
│   ├── Auth/                # Test autenticazione
│   ├── NotificationCycleTest.php
│   └── ProfileTest.php
└── Unit/                     # Test unitari
    └── ExampleTest.php
```

---

## 🚀 Best Practices

### 1. Test Veloci
```php
// ✅ Usa SQLite in memoria (default)
use RefreshDatabase;

// ❌ Evita MySQL per test semplici
// Più lento e richiede configurazione
```

### 2. Test Isolati
```php
public function test_something()
{
    // ✅ Crea i dati necessari nel test
    $user = User::factory()->create();
    
    // ❌ Non fare affidamento su dati esistenti
    // $user = User::find(1); // Potrebbe non esistere
}
```

### 3. Cleanup Automatico
```php
// ✅ RefreshDatabase pulisce tutto automaticamente
use RefreshDatabase;

// ❌ Non serve cleanup manuale
// User::truncate(); // Non necessario
```

### 4. Mock Servizi Esterni
```php
public function test_email_sending()
{
    // ✅ Mock mail per non inviare email reali
    Mail::fake();
    
    // Esegui test
    $this->post('/send-email');
    
    // Verifica
    Mail::assertSent(WelcomeEmail::class);
}
```

---

## 🔍 Troubleshooting

### Errore: "Database non di test rilevato"

**Causa:** Stai usando il database di sviluppo per i test.

**Soluzione:**
1. Verifica `phpunit.xml` (deve avere `DB_CONNECTION=sqlite`)
2. Verifica `.env.testing` (se esiste)
3. Non usare `--env=local` quando esegui i test

### Errore: "SQLSTATE[HY000]: General error: 1 no such table"

**Causa:** SQLite non ha le tabelle create.

**Soluzione:**
```php
// Aggiungi al test
use RefreshDatabase;

// Questo esegue le migrations automaticamente
```

### Test Lenti

**Causa:** Stai usando MySQL invece di SQLite.

**Soluzione:**
1. Verifica `phpunit.xml`
2. Rimuovi override in `.env.testing`
3. Usa SQLite in memoria (default)

---

## 📚 Risorse

- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Database Testing](https://laravel.com/docs/database-testing)

---

## ✅ Checklist Pre-Test

Prima di eseguire `php artisan test`:

- [ ] `phpunit.xml` ha `DB_CONNECTION=sqlite` e `DB_DATABASE=:memory:`
- [ ] `.env.testing` (se esiste) usa database di test
- [ ] I test usano `RefreshDatabase` o `DatabaseTransactions`
- [ ] Nessun test usa `DatabaseMigrations` in produzione
- [ ] Il database di sviluppo ha un backup recente

---

**Data Aggiornamento:** 18 Dicembre 2025  
**Versione:** 1.0  
**Status:** ✅ Configurazione Sicura Attiva
