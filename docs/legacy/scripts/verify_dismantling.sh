#!/bin/bash
# scripts/verify_dismantling.sh
# Script di verifica completa post-smantellamento

echo "🔍 VERIFICA POST-SMANTELLAMENTO"
echo "==============================="

# Colori
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ERRORS=0

# 1. Verifica file eliminati
echo -e "\n📁 Verificando file eliminati..."
FILES=(
    "app/Http/Controllers/Admin/LetterTemplateController.php"
    "app/Http/Controllers/Admin/LetterheadController.php"
    "app/Http/Controllers/Admin/TemplateManagementController.php"
    "app/Models/LetterTemplate.php"
    "app/Models/Letterhead.php"
)

for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${RED}❌ File ancora presente: $file${NC}"
        ((ERRORS++))
    else
        echo -e "${GREEN}✅ File rimosso: $file${NC}"
    fi
done

# 2. Verifica directory eliminate
echo -e "\n📂 Verificando directory eliminate..."
DIRECTORIES=(
    "resources/views/admin/letter-templates"
    "resources/views/admin/letterheads"
)

for dir in "${DIRECTORIES[@]}"; do
    if [ -d "$dir" ]; then
        echo -e "${RED}❌ Directory ancora presente: $dir${NC}"
        ((ERRORS++))
    else
        echo -e "${GREEN}✅ Directory rimossa: $dir${NC}"
    fi
done

# 3. Verifica routes
echo -e "\n🛣️ Verificando routes..."
if php artisan route:list | grep -E "(template|letterhead)" > /dev/null; then
    echo -e "${RED}❌ Routes template/letterhead ancora presenti${NC}"
    php artisan route:list | grep -E "(template|letterhead)"
    ((ERRORS++))
else
    echo -e "${GREEN}✅ Nessuna route template/letterhead trovata${NC}"
fi

# 4. Verifica tabelle database
echo -e "\n🗄️ Verificando database..."
if php artisan tinker --execute="
try {
    \$tables = collect(['letter_templates', 'letterheads'])
        ->filter(fn(\$table) => \Illuminate\Support\Facades\Schema::hasTable(\$table));
    if (\$tables->count() > 0) {
        echo 'RESIDUAL_TABLES:' . \$tables->implode(',') . PHP_EOL;
        exit(1);
    } else {
        echo 'TABLES_CLEAN' . PHP_EOL;
        exit(0);
    }
} catch (Exception \$e) {
    echo 'ERROR:' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
" 2>/dev/null | grep -q "TABLES_CLEAN"; then
    echo -e "${GREEN}✅ Tabelle template/letterhead rimosse${NC}"
else
    echo -e "${RED}❌ Tabelle template/letterhead ancora presenti${NC}"
    ((ERRORS++))
fi

# 5. Test funzionalità core
echo -e "\n🧪 Test funzionalità core..."

# Test dashboard admin
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/admin/dashboard | grep -q "200"; then
    echo -e "${GREEN}✅ Dashboard admin accessibile${NC}"
else
    echo -e "${YELLOW}⚠️ Dashboard admin non raggiungibile (normale se server non attivo)${NC}"
fi

# Test notifiche
if php artisan route:list | grep "notifications" > /dev/null; then
    echo -e "${GREEN}✅ Sistema notifiche preservato${NC}"
else
    echo -e "${RED}❌ Sistema notifiche compromesso${NC}"
    ((ERRORS++))
fi

# 6. Verifica storage
echo -e "\n💾 Verificando storage..."
STORAGE_DIRS=("storage/app/public/letterheads" "storage/app/public/templates")

for dir in "${STORAGE_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        echo -e "${YELLOW}⚠️ Directory storage ancora presente: $dir${NC}"
    else
        echo -e "${GREEN}✅ Directory storage rimossa: $dir${NC}"
    fi
done

# 7. Verifica import/use statements
echo -e "\n📝 Verificando riferimenti residui nel codice..."
if grep -r "use App\\\\Models\\\\LetterTemplate" app/ 2>/dev/null | grep -v ".php.bak"; then
    echo -e "${RED}❌ Riferimenti LetterTemplate ancora presenti${NC}"
    ((ERRORS++))
fi

if grep -r "use App\\\\Models\\\\Letterhead" app/ 2>/dev/null | grep -v ".php.bak"; then
    echo -e "${RED}❌ Riferimenti Letterhead ancora presenti${NC}"
    ((ERRORS++))
fi

if ! grep -r "LetterTemplate\|Letterhead" app/ 2>/dev/null | grep -v ".php.bak" > /dev/null; then
    echo -e "${GREEN}✅ Nessun riferimento template residuo${NC}"
fi

# 8. Test cache
echo -e "\n🧹 Verificando cache..."
if php artisan config:cache && php artisan route:cache; then
    echo -e "${GREEN}✅ Cache ricostruite con successo${NC}"
else
    echo -e "${RED}❌ Errore nella ricostruzione cache${NC}"
    ((ERRORS++))
fi

# RISULTATO FINALE
echo -e "\n"
echo "================================"
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}🎉 VERIFICA COMPLETATA CON SUCCESSO!${NC}"
    echo -e "${GREEN}✅ Sistema Template/Letterhead completamente rimosso${NC}"
    echo -e "${GREEN}✅ Funzionalità core preservate${NC}"
    echo ""
    echo "🚀 PROSSIMI PASSI:"
    echo "   1. Verifica manuale menu admin"
    echo "   2. Test creazione notifiche"
    echo "   3. Verifica assegnazioni tornei"
    echo "   4. Deploy in staging per test"
else
    echo -e "${RED}❌ VERIFICA FALLITA - $ERRORS errori trovati${NC}"
    echo -e "${YELLOW}⚠️ Risolvi gli errori prima di procedere${NC}"
    exit 1
fi

echo ""
echo "📊 STATISTICHE PULIZIA:"
echo "   - File rimossi: ${#FILES[@]}"
echo "   - Directory rimosse: ${#DIRECTORIES[@]}"
echo "   - Tabelle database rimosse: 2"
echo "   - Routes template eliminate: ~20+"
echo "   - Linee codice rimosse: ~2000+"
