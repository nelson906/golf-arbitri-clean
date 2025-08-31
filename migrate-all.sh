#!/bin/bash

# Script per esecuzione automatica della migrazione completa
# Usage: ./migrate-all.sh [--dry-run] [--debug]

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funzione per stampare con colore
print_step() {
    echo -e "${BLUE}==>${NC} $1"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# Controlla se siamo nella directory corretta
if [ ! -f "artisan" ]; then
    print_error "Errore: artisan non trovato. Assicurati di essere nella root del progetto Laravel."
    exit 1
fi

# Parsing degli argomenti
DRY_RUN=""
DEBUG=""
SKIP_FRESH=false

for arg in "$@"; do
    case $arg in
        --dry-run)
            DRY_RUN="--dry-run"
            shift
            ;;
        --debug)
            DEBUG="--debug"
            shift
            ;;
        --skip-fresh)
            SKIP_FRESH=true
            shift
            ;;
        --help)
            echo "Usage: $0 [options]"
            echo "Options:"
            echo "  --dry-run     Esegue in modalità dry-run (non applica modifiche)"
            echo "  --debug       Mostra informazioni di debug"
            echo "  --skip-fresh  Salta migrate:fresh (utile se il DB è già pulito)"
            echo "  --help        Mostra questo messaggio"
            exit 0
            ;;
        *)
            print_error "Opzione sconosciuta: $arg"
            echo "Usa --help per vedere le opzioni disponibili"
            exit 1
            ;;
    esac
done

# Banner iniziale
echo ""
echo "╔════════════════════════════════════════════╗"
echo "║     MIGRAZIONE COMPLETA DATABASE GOLF      ║"
echo "╚════════════════════════════════════════════╝"
echo ""

if [ ! -z "$DRY_RUN" ]; then
    print_warning "MODALITÀ DRY-RUN ATTIVA - Nessuna modifica verrà applicata"
fi

if [ ! -z "$DEBUG" ]; then
    print_warning "MODALITÀ DEBUG ATTIVA"
fi

echo ""

# Funzione per eseguire comando e controllare risultato
run_command() {
    local cmd=$1
    local description=$2

    print_step "$description"

    if $cmd; then
        print_success "$description completato"
        return 0
    else
        print_error "$description fallito"
        return 1
    fi
}

# Step 1: Clear cache
print_step "Pulizia cache..."
php artisan cache:clear > /dev/null 2>&1
php artisan config:clear > /dev/null 2>&1
php artisan route:clear > /dev/null 2>&1
print_success "Cache pulita"

# Step 2: Migrate fresh (opzionale)
if [ "$SKIP_FRESH" = false ]; then
    if [ -z "$DRY_RUN" ]; then
        print_step "Esecuzione migrate:fresh (ATTENZIONE: cancellerà tutti i dati)..."

        # Chiedi conferma solo se non in dry-run
        echo -e "${YELLOW}Sei sicuro di voler cancellare e ricreare il database? (y/N)${NC}"
        read -r response

        if [[ "$response" =~ ^([yY][eE][sS]|[yY])$ ]]; then
            if run_command "php artisan migrate:fresh --force" "Database ricreato"; then
                echo ""
            else
                print_error "Errore durante migrate:fresh"
                exit 1
            fi
        else
            print_warning "Migrate:fresh saltato"
        fi
    else
        print_warning "Migrate:fresh saltato in modalità dry-run"
    fi
else
    print_warning "Migrate:fresh saltato (--skip-fresh)"
fi

# Step 3: Seed roles and permissions
# if run_command "php artisan db:seed --class=RolePermissionSeeder --force" "Creazione ruoli e permessi"; then
#     echo ""
# else
#     print_error "Errore durante il seeding dei ruoli"
#     exit 1
# fi

# Step 4: Migrate core data
print_step "Migrazione dati core (users, zones)..."
if php artisan migrate:core-data $DRY_RUN $DEBUG; then
    print_success "Dati core migrati"
    echo ""
else
    print_error "Errore durante la migrazione dei dati core"
    exit 1
fi

# Step 5: Migrate current data
print_step "Migrazione dati correnti (tournament types, clubs, tournaments, etc.)..."
if php artisan migrate:current-data $DRY_RUN $DEBUG; then
    print_success "Dati correnti migrati"
    echo ""
else
    print_error "Errore durante la migrazione dei dati correnti"
    exit 1
fi

# Step 6: Crea historical data (se necessario)
print_step "Migrazione dati storici (levels, clubs, tournaments, etc.)..."
if php artisan migrate:historical-json $DRY_RUN $DEBUG; then
    print_success "Dati storici migrati"
    echo ""
else
    print_error "Errore durante la migrazione dei dati storici"
    exit 1
fi

# Report finale
echo ""
echo "╔════════════════════════════════════════════╗"
echo "║              REPORT FINALE                 ║"
echo "╚════════════════════════════════════════════╝"
echo ""

# Mostra conteggi
print_step "Riepilogo dati migrati:"
echo ""

# Usa php artisan tinker per ottenere i conteggi
php artisan tinker --execute="
    echo 'Users: ' . \App\Models\User::count() . PHP_EOL;
    echo 'Zones: ' . \App\Models\Zone::count() . PHP_EOL;
    echo 'Tournament Types: ' . \App\Models\TournamentType::count() . PHP_EOL;
    echo 'Clubs: ' . \App\Models\Club::count() . PHP_EOL;
    echo 'Tournaments: ' . \App\Models\Tournament::count() . PHP_EOL;
    echo 'Assignments: ' . \App\Models\Assignment::count() . PHP_EOL;
    echo 'Availabilities: ' . \App\Models\Availability::count() . PHP_EOL;
    echo 'Availabilities: ' . \App\Models\RefereeCareerHistory::count() . PHP_EOL;
"

echo ""

# Cache optimization (solo se non dry-run)
if [ -z "$DRY_RUN" ]; then
    print_step "Ottimizzazione cache..."
    php artisan config:cache > /dev/null 2>&1
    php artisan route:cache > /dev/null 2>&1
    print_success "Cache ottimizzata"
fi

echo ""
print_success "MIGRAZIONE COMPLETATA CON SUCCESSO!"
echo ""

# Suggerimenti finali
echo "Prossimi passi consigliati:"
echo "  1. Verifica i dati migrati accedendo all'applicazione"
echo "  2. Controlla i log in storage/logs per eventuali warning"
echo "  3. Esegui test di base sulle funzionalità principali"
echo ""
