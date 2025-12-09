@extends('layouts.admin')

@section('title', 'Archivia Anno')

@section('content')
    <div class="container mx-auto px-4 py-8 max-w-3xl">
        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Archivia Anno</h1>
            <p class="mt-1 text-sm text-gray-600">
                Trasferisci assegnazioni e disponibilita dell'anno nello storico carriera
            </p>
        </div>

        {{-- Alert --}}
        @if (session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                {{ session('error') }}
            </div>
        @endif

        {{-- Preview Stats --}}
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h3 class="text-sm font-medium text-blue-800 mb-2">
                Preview Anno {{ $currentYear }}
                @if (isset($stats['zone_id']) && $stats['zone_id'])
                    <span class="text-blue-600">(solo tua zona)</span>
                @endif
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-blue-600 font-semibold">{{ $stats['referees_with_assignments'] }}</span>
                    <span class="text-blue-700"> arbitri con assegnazioni</span>
                </div>
                <div>
                    <span class="text-blue-600 font-semibold">{{ $stats['total_assignments'] }}</span>
                    <span class="text-blue-700"> assegnazioni totali</span>
                </div>
                <div>
                    <span class="text-blue-600 font-semibold">{{ $stats['referees_with_availabilities'] }}</span>
                    <span class="text-blue-700"> arbitri con disponibilita</span>
                </div>
                <div>
                    <span class="text-blue-600 font-semibold">{{ $stats['tournaments_count'] }}</span>
                    <span class="text-blue-700"> tornei</span>
                </div>
            </div>
        </div>

        {{-- Form --}}
        <div class="bg-white rounded-lg shadow p-6">
            <form action="{{ route('admin.career-history.process-archive') }}" method="POST">
                @csrf

                <div class="space-y-6">
                    {{-- Anno --}}
                    <div>
                        <label for="year" class="block text-sm font-medium text-gray-700 mb-2">
                            Anno da Archiviare
                        </label>
                        <select name="year" id="year"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            onchange="updatePreview(this.value)">
                            @for ($y = now()->year; $y >= 2020; $y--)
                                <option value="{{ $y }}" {{ $y == $currentYear ? 'selected' : '' }}>
                                    {{ $y }}
                                </option>
                            @endfor
                        </select>
                        @error('year')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Utente specifico --}}
                    <div>
                        <label for="user_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Arbitro {{ $canArchiveAll ?? false ? '(opzionale)' : '' }}
                        </label>
                        <select name="user_id" id="user_id" {{ $canArchiveAll ?? false ? '' : 'required' }}
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @if ($canArchiveAll ?? false)
                                <option value="">Tutti gli arbitri</option>
                            @else
                                <option value="">Seleziona un arbitro...</option>
                            @endif
                            @foreach ($referees as $referee)
                                <option value="{{ $referee->id }}"
                                    {{ request('user_id') == $referee->id ? 'selected' : '' }}>
                                    {{ $referee->name }} ({{ $referee->email }})
                                </option>
                            @endforeach
                        </select>
                        @if ($canArchiveAll ?? false)
                            <p class="mt-1 text-xs text-gray-500">
                                Lascia vuoto per archiviare tutti gli arbitri con attivita nell'anno
                            </p>
                        @else
                            <p class="mt-1 text-xs text-gray-500">
                                Seleziona l'arbitro della tua zona per cui archiviare l'anno
                            </p>
                        @endif
                    </div>

                    {{-- Warning --}}
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <div class="flex">
                            <svg class="h-5 w-5 text-amber-400 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                </path>
                            </svg>
                            <div>
                                <h4 class="text-sm font-medium text-amber-800">Nota</h4>
                                <p class="text-sm text-amber-700 mt-1">
                                    L'archiviazione aggiungerà i dati allo storico senza eliminare i dati originali.
                                    I dati esistenti nello storico per lo stesso anno verranno sovrascritti.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="mt-6 flex justify-end space-x-3">
                    <a href="{{ route('admin.career-history.index') }}"
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Annulla
                    </a>
                    <button type="submit"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        Archivia Anno
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function updatePreview(year) {
                // Potrebbe chiamare un endpoint AJAX per aggiornare le stats
                // Per ora mostra solo l'anno selezionato
                // Could call AJAX endpoint to update stats
                console.log('Anno selezionato:', year);
            }
        </script>
    @endpush
@endsection
