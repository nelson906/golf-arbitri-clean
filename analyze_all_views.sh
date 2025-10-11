#!/bin/bash
# analyze_all_views.sh - Analisi completa views utilizzate/inutilizzate
# Genera un report dettagliato senza eliminare nulla

echo "🔍 ANALISI COMPLETA VIEWS - REPORT DETTAGLIATO"
echo "=============================================="
echo ""

# Output files
REPORT_FILE="view_analysis_report_$(date +%Y%m%d_%H%M%S).txt"
UNUSED_VIEWS="unused_views.txt"
USED_VIEWS="used_views.txt"

# Inizializza report
cat > "$REPORT_FILE" <<EOF
ANALISI VIEWS LARAVEL - $(date)
=====================================

METODOLOGIA:
1. Cerca tutte le view in resources/views
2. Cerca referenze nei controller (view(), return view())
3. Cerca referenze nelle route (direttamente)
4. Cerca @include, @extends nei file blade
5. Identifica view mai referenziate

EOF

echo "📁 Fase 1: Raccolta tutte le view..."
echo "-----------------------------------"

# Trova tutte le view blade
find resources/views -name "*.blade.php" -type f > all_views_temp.txt
TOTAL_VIEWS=$(wc -l < all_views_temp.txt)

echo "   Trovate $TOTAL_VIEWS view totali"
echo "" >> "$REPORT_FILE"
echo "TOTALE VIEW TROVATE: $TOTAL_VIEWS" >> "$REPORT_FILE"
echo "==================================" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

echo ""
echo "🔎 Fase 2: Analisi utilizzo per ogni view..."
echo "---------------------------------------------"

> "$USED_VIEWS"
> "$UNUSED_VIEWS"

USED_COUNT=0
UNUSED_COUNT=0
CURRENT=0

while IFS= read -r view_file; do
    ((CURRENT++))

    # Progress indicator
    if [ $((CURRENT % 10)) -eq 0 ]; then
        echo "   Analizzate: $CURRENT/$TOTAL_VIEWS"
    fi

    # Converte path in view name (admin.users.index)
    view_path="${view_file#resources/views/}"
    view_name="${view_path%.blade.php}"
    view_name="${view_name//\//.}"

    IS_USED=0
    REFERENCES=()

    # 1. Cerca nei controller
    CONTROLLER_REFS=$(grep -r "view(['\"]${view_name}['\"]" app/Http/Controllers/ 2>/dev/null)
    if [ -n "$CONTROLLER_REFS" ]; then
        IS_USED=1
        REFERENCES+=("Controller: $(echo "$CONTROLLER_REFS" | head -1 | cut -d: -f1)")
    fi

    # 2. Cerca view('...')  senza quote specifiche
    VIEW_CALLS=$(grep -r "view\s*(\s*['\"]${view_name}" app/ 2>/dev/null)
    if [ -n "$VIEW_CALLS" ]; then
        IS_USED=1
        REFERENCES+=("View call: $(echo "$VIEW_CALLS" | head -1 | cut -d: -f1)")
    fi

    # 3. Cerca nelle routes
    ROUTE_REFS=$(grep -r "${view_name}" routes/ 2>/dev/null)
    if [ -n "$ROUTE_REFS" ]; then
        IS_USED=1
        REFERENCES+=("Route: $(echo "$ROUTE_REFS" | head -1 | cut -d: -f1)")
    fi

    # 4. Cerca @include/@extends in altri blade
    BLADE_REFS=$(grep -r "@\(include\|extends\)\s*(['\"]${view_name}" resources/views/ 2>/dev/null)
    if [ -n "$BLADE_REFS" ]; then
        IS_USED=1
        REFERENCES+=("Blade: $(echo "$BLADE_REFS" | head -1 | cut -d: -f1)")
    fi

    # 5. Cerca component usage <x-...>
    if [[ $view_file == *"/components/"* ]]; then
        component_name=$(basename "$view_file" .blade.php)
        COMPONENT_REFS=$(grep -r "<x-${component_name}" resources/views/ app/ 2>/dev/null)
        if [ -n "$COMPONENT_REFS" ]; then
            IS_USED=1
            REFERENCES+=("Component usage: $(echo "$COMPONENT_REFS" | head -1 | cut -d: -f1)")
        fi
    fi

    # 6. Cerca in Mailable (per email views)
    if [[ $view_file == *"/emails/"* ]]; then
        MAIL_REFS=$(grep -r "view:\s*['\"]${view_name}" app/Mail/ 2>/dev/null)
        if [ -n "$MAIL_REFS" ]; then
            IS_USED=1
            REFERENCES+=("Mailable: $(echo "$MAIL_REFS" | head -1 | cut -d: -f1)")
        fi
    fi

    # Registra risultato
    if [ $IS_USED -eq 1 ]; then
        ((USED_COUNT++))
        echo "$view_file" >> "$USED_VIEWS"

        {
            echo "✅ USATA: $view_file"
            echo "   View name: $view_name"
            for ref in "${REFERENCES[@]}"; do
                echo "   → $ref"
            done
            echo ""
        } >> "$REPORT_FILE"
    else
        ((UNUSED_COUNT++))
        echo "$view_file" >> "$UNUSED_VIEWS"

        {
            echo "❌ NON USATA: $view_file"
            echo "   View name: $view_name"
            echo "   Nessuna referenza trovata"
            echo ""
        } >> "$REPORT_FILE"
    fi

done < all_views_temp.txt

# Cleanup temp
rm all_views_temp.txt

echo ""
echo "✅ Analisi completata!"
echo ""
echo "📊 RISULTATI:"
echo "============="
echo "   Totale view:      $TOTAL_VIEWS"
echo "   View usate:       $USED_COUNT (${USED_COUNT}%)"
echo "   View NON usate:   $UNUSED_COUNT (${UNUSED_COUNT}%)"
echo ""

# Aggiungi summary al report
{
    echo ""
    echo "SUMMARY FINALE"
    echo "=============="
    echo "Totale view analizzate: $TOTAL_VIEWS"
    echo "View USATE: $USED_COUNT"
    echo "View NON USATE: $UNUSED_COUNT"
    echo ""
    echo "LISTA VIEW NON USATE:"
    echo "====================="
    cat "$UNUSED_VIEWS"
} >> "$REPORT_FILE"

echo "📄 Report salvato in: $REPORT_FILE"
echo "📋 View usate salvate in: $USED_VIEWS"
echo "🗑️  View NON usate salvate in: $UNUSED_VIEWS"
echo ""

# Mostra preview view non usate
if [ $UNUSED_COUNT -gt 0 ]; then
    echo "🔍 PREVIEW VIEW NON USATE (prime 20):"
    echo "======================================"
    head -20 "$UNUSED_VIEWS"

    if [ $UNUSED_COUNT -gt 20 ]; then
        echo ""
        echo "   ... e altre $((UNUSED_COUNT - 20)) view"
    fi
    echo ""
    echo "💡 Vedi lista completa in: $UNUSED_VIEWS"
fi

echo ""
echo "🎯 PROSSIMI PASSI:"
echo "=================="
echo "1. Rivedi il report: less $REPORT_FILE"
echo "2. Verifica manualmente le view non usate"
echo "3. Crea backup: tar -czf backup_views.tar.gz resources/views/"
echo "4. Elimina view confermate inutilizzate"
echo ""
echo "⚠️  ATTENZIONE: Verifica sempre manualmente prima di eliminare!"
