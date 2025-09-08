@extends('layouts.admin')

@section('page-title', 'Modifica Tipo di Torneo')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white shadow rounded-lg">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold">Modifica Tipo di Torneo: {{ $tournamentType->name }}</h2>
        </div>

        <form action="{{ route('super-admin.tournament-types.update', $tournamentType) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="p-6 space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Nome *</label>
                    <input type="text" 
                           name="name" 
                           id="name" 
                           value="{{ old('name', $tournamentType->name) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                           required>
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">Codice</label>
                    <input type="text" 
                           name="code" 
                           id="code" 
                           value="{{ old('code', $tournamentType->code) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Descrizione</label>
                    <textarea name="description" 
                              id="description" 
                              rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $tournamentType->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="calendar_color" class="block text-sm font-medium text-gray-700">Colore Calendario</label>
                    <input type="color" 
                           name="calendar_color" 
                           id="calendar_color" 
                           value="{{ old('calendar_color', $tournamentType->calendar_color ?? '#3B82F6') }}"
                           class="mt-1 block h-10 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('calendar_color')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="sort_order" class="block text-sm font-medium text-gray-700">Ordine</label>
                    <input type="number" 
                           name="sort_order" 
                           id="sort_order" 
                           value="{{ old('sort_order', $tournamentType->sort_order) }}"
                           min="0"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('sort_order')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="flex items-center">
                        <input type="checkbox" 
                               name="is_active" 
                               value="1" 
                               {{ old('is_active', $tournamentType->is_active) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-700">Attivo</span>
                    </label>
                </div>

                @if($tournamentType->tournaments_count > 0)
                    <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-md">
                        <p class="text-sm text-yellow-800">
                            Questo tipo è utilizzato da {{ $tournamentType->tournaments_count }} torneo/i.
                        </p>
                    </div>
                @endif
            </div>

            <div class="px-6 py-4 bg-gray-50 text-right space-x-3">
                <a href="{{ route('super-admin.tournament-types.index') }}" 
                   class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Annulla
                </a>
                <button type="submit" 
                        class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Salva Modifiche
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
