@props([
    'route_name',
    'filters' => [],
    'clear_route' => null,
    'title' => 'Filtri',
    'storage_key' => 'filters_expanded',
    'show_toggle' => true,
    'auto_submit_selects' => true,
    'auto_submit_text_delay' => 500,
    'show_filter_count' => true,
    'exclude_from_count' => ['order_by', 'order_dir']
])

@php
    $clearRoute = $clear_route ?? str_replace('.index', '.clearFilters', $route_name);

    // Calcola filtri attivi
    $activeFilters = array_filter(
        $filters,
        function ($value, $key) use ($exclude_from_count) {
            return $value !== null &&
                   $value !== '' &&
                   !in_array($key, $exclude_from_count);
        },
        ARRAY_FILTER_USE_BOTH
    );

    $activeFiltersCount = count($activeFilters);
@endphp

<div id="filter-section" class="mb-6">
    @if($show_toggle)
        <div class="flex justify-between mb-2">
            <h3 class="text-lg font-medium">{{ $title }}</h3>
            <button type="button" id="toggle-filters"
                class="text-sm text-indigo-600 hover:text-indigo-900">
                Nascondi filtri
            </button>
        </div>
    @else
        <h3 class="text-lg font-medium mb-4">{{ $title }}</h3>
    @endif

    <form action="{{ route($route_name) }}" method="GET" id="filter-form"
        class="bg-gray-50 p-4 rounded-lg">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{ $slot }}
        </div>

        <!-- Pulsanti azione -->
        <div class="flex justify-between mt-4">
            <div class="space-x-2">
                <x-button type="primary" action="submit" icon="filter">
                    Applica filtri
                </x-button>

                <x-button type="outline-secondary" href="{{ route($clearRoute) }}" icon="clear">
                    Cancella filtri
                </x-button>
            </div>

            <!-- Indicatore filtri attivi -->
            @if($show_filter_count)
                <div>
                    @if($activeFiltersCount > 0)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                            {{ $activeFiltersCount }} filtri attivi
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            Nessun filtro attivo
                        </span>
                    @endif
                </div>
            @endif
        </div>
    </form>
</div>

@if($show_toggle || $auto_submit_selects)
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterForm = document.getElementById('filter-form');

        @if($show_toggle)
        // Gestione toggle filtri
        const toggleButton = document.getElementById('toggle-filters');
        const filterSection = document.getElementById('filter-section');

        // Verifica localStorage per lo stato dei filtri
        const isExpanded = localStorage.getItem('{{ $storage_key }}') !== 'false';

        if (!isExpanded) {
            filterForm.classList.add('hidden');
            toggleButton.textContent = 'Mostra filtri';
        }

        toggleButton.addEventListener('click', function() {
            const isHidden = filterForm.classList.contains('hidden');

            if (isHidden) {
                filterForm.classList.remove('hidden');
                toggleButton.textContent = 'Nascondi filtri';
                localStorage.setItem('{{ $storage_key }}', 'true');
            } else {
                filterForm.classList.add('hidden');
                toggleButton.textContent = 'Mostra filtri';
                localStorage.setItem('{{ $storage_key }}', 'false');
            }
        });
        @endif

        @if($auto_submit_selects)
        // Auto-submit per i campi select
        const selectFields = filterForm.querySelectorAll('select');
        selectFields.forEach(select => {
            select.addEventListener('change', function() {
                filterForm.submit();
            });
        });
        @endif

        @if($auto_submit_text_delay > 0)
        // Debounce per i campi di testo
        const textInputs = filterForm.querySelectorAll('input[type="text"], input[type="search"]');
        let timeout = null;

        textInputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    filterForm.submit();
                }, {{ $auto_submit_text_delay }});
            });
        });

        // Auto-submit per campi data con timeout
        const dateFields = filterForm.querySelectorAll('input[type="date"]');
        dateFields.forEach(dateField => {
            dateField.addEventListener('change', function() {
                setTimeout(() => {
                    filterForm.submit();
                }, 500);
            });
        });
        @endif
    });
</script>
@endif
