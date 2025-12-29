@extends('layouts.admin')

@section('title', 'Modifica Notifica')

@section('content')
    <div class="container mx-auto px-4 py-6">
        <h1 class="text-2xl font-bold mb-6">Modifica Notifica</h1>

        <form action="{{ route('admin.tournament-notifications.update', $tournamentNotification->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="bg-white shadow rounded-lg p-6">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Lista Arbitri</label>
<textarea name="referee_list" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('referee_list', $tournamentNotification->referee_list) }}</textarea>
                </div>

                <div class="flex justify-end space-x-4">
                    <a href="{{ route('admin.tournament-notifications.index') }}"
                        class="px-4 py-2 border rounded">Annulla</a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Salva</button>
                </div>
            </div>
        </form>
    </div>
@endsection
