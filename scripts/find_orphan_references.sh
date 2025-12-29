#!/bin/bash
# scripts/find_orphan_references.sh
# Script per ricerca manuale approfondita di riferimenti orfani

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔍 RICERCA AVANZATA RIFERIMENTI ORFANI${NC}"
echo "========================================"

# Target da cercare (modificabile)
TARGETS=("LetterTemplate" "Letterhead" "letter-templates" "letterheads")

# 1. RICERCA USE STATEMENTS
echo -e "\n${YELLOW}📁 USE STATEMENTS${NC}"
echo "═════════════════"
for target in "${TARGETS[@]}"; do
    echo -e "\n🎯 Cercando: ${target}"

    # Use statements diretti
    grep -rn "use.*${target}" app/ routes/ --include="*.php" | head -10

    # Import in stile moderno
    grep -rn "use.*\\${target}" app/ --include="*.php" | head -5
done

# 2. RICERCA CHIAMATE DIRETTE
echo -e "\n${YELLOW}🎯 CHIAMATE DIRETTE CLASSI${NC}"
echo "═══════════════════════════"
for target in "${TARGETS[@]}"; do
    echo -e "\n🎯 Cercando: ${target}"

    # new Class(), Class::method()
    grep -rn "${target}::" app/ routes/ --include="*.php" | head -10
    grep -rn "new ${target}" app/ --include="*.php" | head -5

    # String references
    grep -rn "'${target}'" app/ routes/ --include="*.php" | head -5
    grep -rn "\"${target}\"" app/ routes/ --include="*.php" | head -5
done

# 3. RICERCA ROUTE REFERENCES
echo -e "\n${YELLOW}🛣️ ROUTE REFERENCES${NC}"
echo "══════════════════"

# Blade templates
echo "🔍 Nei file Blade:"
grep -rn "route.*letter-template" resources/views/ --include="*.blade.php"
grep -rn "route.*letterhead" resources/views/ --include="*.blade.php"

# Route helpers
echo -e "\n🔍 Helper route():"
grep -rn "route('.*letter" app/ routes/ --include="*.php"

# URL helpers
echo -e "\n🔍 Helper url():"
grep -rn "url('.*letter" app/ routes/ --include="*.php"

# 4. RICERCA VIEW REFERENCES
echo -e "\n${YELLOW}👁️ VIEW REFERENCES${NC}"
echo "═════════════════"

# view() calls
echo "🔍 Chiamate view():"
grep -rn "view('.*letter" app/ --include="*.php"

# @include, @extends
echo -e "\n🔍 Blade directives:"
grep -rn "@include.*letter" resources/views/ --include="*.blade.php"
grep -rn "@extends.*letter" resources/views/ --include="*.blade.php"

# 5. RICERCA DATABASE
echo -e "\n${YELLOW}🗄️ DATABASE REFERENCES${NC}"
echo "═════════════════════"

# Migration files
echo "🔍 File migration:"
find database/migrations/ -name "*letter*" -o -name "*template*"

# Model relationships
echo -e "\n🔍 Relazioni nei modelli:"
grep -rn "belongsTo.*Letter" app/Models/ --include="*.php"
grep -rn "hasMany.*Letter" app/Models/ --include="*.php"

# Validation rules
echo -e "\n🔍 Regole validazione:"
grep -rn "exists:letter" app/ --include="*.php"
grep -rn "unique:letter" app/ --include="*.php"

# 6. RICERCA CONFIG/PROVIDER
echo -e "\n${YELLOW}⚙️ CONFIG & PROVIDERS${NC}"
echo "═══════════════════════"

# Config files
echo "🔍 File config:"
grep -rn "Letter" config/ --include="*.php" | head -10

# Service Providers
echo -e "\n🔍 Service Providers:"
grep -rn "Letter" app/Providers/ --include="*.php"

# 7. RICERCA AVANZATA PATTERN
echo -e "\n${YELLOW}🔬 PATTERN AVANZATI${NC}"
echo "═══════════════════"

# Facade calls
echo "🔍 Facade calls:"
for target in "${TARGETS[@]}"; do
    grep -rn "${target}::\w" app/ --include="*.php" | head -5
done

# Array keys
echo -e "\n🔍 Array keys:"
grep -rn "\['.*letter" app/ --include="*.php" | head -10

# Comments (potrebbero indicare TODO)
echo -e "\n🔍 Commenti:"
grep -rn "//.*[Ll]etter.*[Tt]emplate" app/ --include="*.php" | head -5

# 8. RICERCA ASSET FILES
echo -e "\n${YELLOW}🎨 ASSET FILES${NC}"
echo "═════════════════"

# CSS
echo "🔍 File CSS:"
find public/ -name "*.css" -exec grep -l "letter\|template" {} \;

# JavaScript
echo -e "\n🔍 File JavaScript:"
find public/ -name "*.js" -exec grep -l "letter\|template" {} \;
find resources/js/ -name "*.js" -exec grep -l "letter\|template" {} \; 2>/dev/null

# 9. RICERCA LOGS E CACHE
echo -e "\n${YELLOW}📜 LOGS & CACHE${NC}"
echo "═══════════════════"

# Storage logs
echo "🔍 Log files (ultimi 5):"
find storage/logs/ -name "*.log" -exec grep -l "Letter" {} \; 2>/dev/null | head -5

# Cache files
echo -e "\n🔍 Cache keys:"
if command -v redis-cli &> /dev/null; then
    echo "Redis cache keys con 'letter':"
    redis-cli keys "*letter*" 2>/dev/null | head -10
else
    echo "Redis non disponibile"
fi

# 10. CONTROLLO COMPOSER
echo -e "\n${YELLOW}📦 COMPOSER & AUTOLOAD${NC}"
echo "═══════════════════════════"

# composer.json
echo "🔍 composer.json:"
grep -n "letter\|template" composer.json 2>/dev/null || echo "Nessun riferimento in composer.json"

# PSR-4 autoload
echo -e "\n🔍 Autoload PSR-4:"
if [ -f vendor/composer/autoload_psr4.php ]; then
    grep -n "Letter" vendor/composer/autoload_psr4.php | head -5
fi

# 11. RICERCA QUEUE/JOBS
echo -e "\n${YELLOW}⚡ QUEUE & JOBS${NC}"
echo "══════════════════"

# Job files
echo "🔍 Job files:"
find app/Jobs/ -name "*.php" -exec grep -l "Letter" {} \; 2>/dev/null

# Queue tables (if exists)
echo -e "\n🔍 Jobs in database:"
if command -v mysql &> /dev/null; then
    mysql -e "SELECT COUNT(*) as jobs_with_letter FROM jobs WHERE payload LIKE '%Letter%'" 2>/dev/null || echo "Tabella jobs non accessibile"
fi

# 12. RICERCA TESTS
echo -e "\n${YELLOW}🧪 TEST FILES${NC}"
echo "═════════════════"

echo "🔍 File di test:"
find tests/ -name "*.php" -exec grep -l "Letter" {} \; 2>/dev/null

# Feature tests
echo -e "\n🔍 Feature tests:"
grep -rn "letter-template\|letterhead" tests/ --include="*.php" | head -5

# SUMMARY FINALE
echo -e "\n${GREEN}✅ RICERCA COMPLETATA${NC}"
echo "═══════════════════════"
echo -e "\n💡 ${YELLOW}COMANDI UTILI AGGIUNTIVI:${NC}"
echo "   find . -type f -name '*.php' -not -path './vendor/*' -exec grep -l 'LetterTemplate' {} \;"
echo "   ag 'LetterTemplate|Letterhead' --php"
echo "   ripgrep 'Letter(Template|head)' --type php"
echo "   grep -r 'letter.*template' . --include='*.blade.php'"
echo ""
echo -e "🔧 ${YELLOW}PER VERIFICHE SPECIFICHE:${NC}"
echo "   php artisan route:list | grep letter"
echo "   php artisan tinker"
echo "   >>> DB::select('SHOW TABLES LIKE \"%letter%\"')"
echo ""
echo -e "⚠️  ${RED}NOTA:${NC} Controlla manualmente ogni risultato prima di rimuovere!"

# Cerca file che potrebbero essere stati dimenticati
echo -e "\n${YELLOW}📋 FILE PROBABILMENTE DIMENTICATI${NC}"
echo "═══════════════════════════════════"

# Cerca file il cui nome contiene template/letterhead
find . -type f \( -name "*letter*template*" -o -name "*letterhead*" \) -not -path "./vendor/*" -not -path "./node_modules/*"

# Cerca directory
find . -type d \( -name "*letter*template*" -o -name "*letterhead*" \) -not -path "./vendor/*" -not -path "./node_modules/*"
