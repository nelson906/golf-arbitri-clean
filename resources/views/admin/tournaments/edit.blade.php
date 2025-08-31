@extends('layouts.admin')

@section('title', 'Modifica Torneo: ' . $tournament->name)

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Modifica Torneo</h1>
                <p class="mt-2 text-gray-600">{{ $tournament->name }} - {{ $tournament->club->name }}</p>
            </div>
            <div class="flex space-x-4">
                <a href="{{ route('admin.tournaments.show', $tournament) }}"
                   class="text-gray-600 hover:text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    Visualizza
                </a>
                <a href="{{ route('admin.tournaments.index') }}"
                   class="text-gray-600 hover:text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Torna all'elenco
                </a>
            </div>
        </div>
    </div>

    {{-- Alert Messages --}}
    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Errori di validazione!</p>
            <ul class="mt-2 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('admin.tournaments.update', $tournament) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="bg-white shadow-sm rounded-lg p-6">
            {{-- Basic Information --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Nome Torneo *
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           value="{{ old('name', $tournament->name) }}"
                           required
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="tournament_type_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Categoria *
                    </label>
                    <select name="tournament_type_id" id="tournament_type_id" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Seleziona una categoria</option>
                        @foreach($tournamentTypes as $type)
                            <option value="{{ $type->id }}" {{ old('tournament_type_id', $tournament->tournament_type_id) == $type->id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('tournament_type_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Location --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @if(count($zones) > 1)
                <div>
                    <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Zona *
                    </label>
                    <select name="zone_id" id="zone_id" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Seleziona una zona</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}" {{ old('zone_id', $tournament->zone_id) == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('zone_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                @else
                    <input type="hidden" name="zone_id" value="{{ $zones->first()->id }}">
                @endif

                <div>
                    <label for="club_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Club *
                    </label>
                    <select name="club_id" id="club_id" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Seleziona un club</option>
                        @foreach($clubs as $club)
                            <option value="{{ $club->id }}" {{ old('club_id', $tournament->club_id) == $club->id ? 'selected' : '' }}>
                                {{ $club->name }} ({{ $club->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('club_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Dates --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">
                        Data Inizio *
                    </label>
                    <input type="date"
                           name="start_date"
                           id="start_date"
                           value="{{ old('start_date', $tournament->start_date->format('Y-m-d')) }}"
                           required
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('start_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">
                        Data Fine *
                    </label>
                    <input type="date"
                           name="end_date"
                           id="end_date"
                           value="{{ old('end_date', $tournament->end_date->format('Y-m-d')) }}"
                           required
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('end_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="availability_deadline" class="block text-sm font-medium text-gray-700 mb-2">
                        Scadenza Disponibilità *
                    </label>
                    <input type="date"
                           name="availability_deadline"
                           id="availability_deadline"
                           value="{{ old('availability_deadline', Carbon\Carbon::parse($tournament->availability_deadline)->format('Y-m-d')) }}"
                           required
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('availability_deadline')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Notes --}}
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                    Note
                </label>
                <textarea name="notes"
                          id="notes"
                          rows="3"
                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $tournament->notes) }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end space-x-4">
            <a href="{{ route('admin.tournaments.show', $tournament) }}"
               class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Annulla
            </a>
            <button type="submit"
                    class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Aggiorna Torneo
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const deadlineInput = document.getElementById('availability_deadline');

    // Auto-update end date when start date changes
    startDateInput.addEventListener('change', function() {
        if (this.value && !endDateInput.value) {
            endDateInput.value = this.value;
        }

        // Set availability deadline to 7 days before start date
        if (this.value && !deadlineInput.value) {
            const startDate = new Date(this.value);
            const deadline = new Date(startDate);
            deadline.setDate(deadline.getDate() - 7);
            deadlineInput.value = deadline.toISOString().split('T')[0];
        }
    });

    // Validate end date is not before start date
    endDateInput.addEventListener('change', function() {
        if (startDateInput.value && this.value) {
            if (new Date(this.value) < new Date(startDateInput.value)) {
                alert('La data di fine non può essere precedente alla data di inizio');
                this.value = startDateInput.value;
            }
        }
    });

    // Validate availability deadline is before start date
    deadlineInput.addEventListener('change', function() {
        if (startDateInput.value && this.value) {
            if (new Date(this.value) >= new Date(startDateInput.value)) {
                alert('La scadenza disponibilità deve essere precedente alla data di inizio');
                const startDate = new Date(startDateInput.value);
                const deadline = new Date(startDate);
                deadline.setDate(deadline.getDate() - 1);
                this.value = deadline.toISOString().split('T')[0];
            }
        }
    });
});
</script>
@endpush
@endsection
