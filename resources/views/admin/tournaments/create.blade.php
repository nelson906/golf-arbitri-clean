@extends('layouts.admin')

@section('title', 'Nuovo Torneo')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Nuovo Torneo</h1>
                <p class="mt-2 text-gray-600">Crea un nuovo torneo per la tua zona</p>
            </div>
            <a href="{{ route('admin.tournaments.index') }}"
               class="text-gray-600 hover:text-gray-900 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Torna all'elenco
            </a>
        </div>
    </div>

    {{-- Form Errors --}}
    @if ($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold mb-2">Errori nel form:</p>
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('admin.tournaments.store') }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white shadow-sm rounded-lg p-6">
            {{-- Basic Information --}}
            <h3 class="text-lg font-medium text-gray-900 mb-4">Informazioni Base</h3>

            <div class="grid grid-cols-1 gap-6">
                {{-- Tournament Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">
                        Nome Torneo <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           value="{{ old('name') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                           required>
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Category --}}
                <div>
                    <label for="tournament_type_id" class="block text-sm font-medium text-gray-700">
                        Categoria <span class="text-red-500">*</span>
                    </label>
                    <select name="tournament_type_id"
                            id="tournament_type_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('tournament_type_id') border-red-500 @enderror"
                            required>
                        <option value="">Seleziona categoria...</option>
                        @foreach($tournamentTypes as $type)
                            <option value="{{ $type->id }}"
                                {{ old('tournament_type_id') == $type->id ? 'selected' : '' }}
                                data-min-referees="{{ $type->min_referees }}"
                                data-max-referees="{{ $type->max_referees }}"
                                data-national="{{ $type->is_national ? '1' : '0' }}">
                                {{ $type->name }}
                                @if($type->is_national)
                                    (Nazionale)
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('tournament_type_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500" id="category-info" style="display: none;">
                        Arbitri richiesti: <span id="referees-range"></span>
                    </p>
                </div>

                {{-- Zone (only for national admin) --}}
                @if(auth()->user()->user_type === 'national_admin')
                <div>
                    <label for="zone_id" class="block text-sm font-medium text-gray-700">
                        Zona <span class="text-red-500">*</span>
                    </label>
                    <select name="zone_id"
                            id="zone_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('zone_id') border-red-500 @enderror"
                            required>
                        <option value="">Seleziona zona...</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}" {{ old('zone_id') == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('zone_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                @endif

                {{-- club --}}
                <div>
                    <label for="club_id" class="block text-sm font-medium text-gray-700">
                        Circolo <span class="text-red-500">*</span>
                    </label>
                    <select name="club_id"
                            id="club_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('club_id') border-red-500 @enderror"
                            required>
                        <option value="">Seleziona circolo...</option>
                        @foreach($clubs as $club)
                            <option value="{{ $club->id }}"
                                {{ old('club_id') == $club->id ? 'selected' : '' }}
                                data-zone-id="{{ $club->zone_id }}">
                                {{ $club->name }} ({{ $club->city }})
                            </option>
                        @endforeach
                    </select>
                    @error('club_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- Start Date --}}
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700">
                            Data Inizio <span class="text-red-500">*</span>
                        </label>
                        <input type="date"
                               name="start_date"
                               id="start_date"
                               value="{{ old('start_date') }}"
                               min="{{ date('Y-m-d') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('start_date') border-red-500 @enderror"
                               required>
                        @error('start_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- End Date --}}
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700">
                            Data Fine <span class="text-red-500">*</span>
                        </label>
                        <input type="date"
                               name="end_date"
                               id="end_date"
                               value="{{ old('end_date') }}"
                               min="{{ date('Y-m-d') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('end_date') border-red-500 @enderror"
                               required>
                        @error('end_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Availability Deadline --}}
                    <div>
                        <label for="availability_deadline" class="block text-sm font-medium text-gray-700">
                            Scadenza Disponibilità <span class="text-red-500">*</span>
                        </label>
                        <input type="date"
                               name="availability_deadline"
                               id="availability_deadline"
                               value="{{ old('availability_deadline') }}"
                               min="{{ date('Y-m-d') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('availability_deadline') border-red-500 @enderror"
                               required>
                        @error('availability_deadline')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">Data entro cui gli arbitri devono dichiarare la disponibilità</p>
                    </div>
                </div>

                {{-- Notes --}}
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">
                        Note
                    </label>
                    <textarea name="notes"
                              id="notes"
                              rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('notes') border-red-500 @enderror">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Note aggiuntive per gli arbitri</p>
                </div>

                {{-- Status --}}
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">
                        Stato Iniziale <span class="text-red-500">*</span>
                    </label>
                    <select name="status"
                            id="status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('status') border-red-500 @enderror"
                            required>
                        <option value="draft" {{ old('status', 'draft') == 'draft' ? 'selected' : '' }}>
                            Bozza (non visibile agli arbitri)
                        </option>
                        <option value="open" {{ old('status') == 'open' ? 'selected' : '' }}>
                            Aperto (visibile e aperto per disponibilità)
                        </option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end space-x-4">
            <a href="{{ route('admin.tournaments.index') }}"
               class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Annulla
            </a>
            <button type="submit"
                    name="action"
                    value="save"
                    class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Crea Torneo
            </button>
            <button type="submit"
                    name="action"
                    value="save_and_new"
                    class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                Crea e Nuovo
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Category change handler
    const categorySelect = document.getElementById('tournament_type_id');
    const categoryInfo = document.getElementById('category-info');
    const refereesRange = document.getElementById('referees-range');

    categorySelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            const minReferees = selectedOption.dataset.minReferees;
            const maxReferees = selectedOption.dataset.maxReferees;

            if (minReferees === maxReferees) {
                refereesRange.textContent = minReferees;
            } else {
                refereesRange.textContent = `${minReferees} - ${maxReferees}`;
            }

            categoryInfo.style.display = 'block';
        } else {
            categoryInfo.style.display = 'none';
        }
    });

    // Date validation
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const deadline = document.getElementById('availability_deadline');

    startDate.addEventListener('change', function() {
        // End date must be >= start date
        endDate.min = this.value;
        if (endDate.value && endDate.value < this.value) {
            endDate.value = this.value;
        }

        // Deadline must be before start date
        if (this.value) {
            const maxDeadline = new Date(this.value);
            maxDeadline.setDate(maxDeadline.getDate() - 1);
            deadline.max = maxDeadline.toISOString().split('T')[0];
        }
    });

    endDate.addEventListener('change', function() {
        // Start date must be <= end date
        if (startDate.value && this.value < startDate.value) {
            startDate.value = this.value;
        }
    });

    @if(auth()->user()->user_type === 'national_admin')
    // Zone change handler (for national admin)
    const zoneSelect = document.getElementById('zone_id');
    const clubSelect = document.getElementById('club_id');
    const allclubOptions = Array.from(clubSelect.options);

    zoneSelect.addEventListener('change', function() {
        const selectedZoneId = this.value;

        // Reset club select
        clubSelect.innerHTML = '<option value="">Seleziona circolo...</option>';

        // Filter clubs by zone
        if (selectedZoneId) {
            allclubOptions.forEach(option => {
                if (option.value && option.dataset.zoneId === selectedZoneId) {
                    clubSelect.appendChild(option.cloneNode(true));
                }
            });
        } else {
            // Show all clubs if no zone selected
            allclubOptions.forEach(option => {
                if (option.value) {
                    clubSelect.appendChild(option.cloneNode(true));
                }
            });
        }
    });
    @endif
});
</script>
@endpush
@endsection
