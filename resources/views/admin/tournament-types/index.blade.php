@extends('layouts.admin')

@section('page-title', 'Tipi di Torneo')

@section('content')
<div class="bg-white shadow rounded-lg">
    <div class="p-6 border-b border-gray-200">
        <div class="flex justify-between items-center">
            <h2 class="text-xl font-semibold">Gestione Tipi di Torneo</h2>
            <a href="{{ route('super-admin.tournament-types.create') }}" 
               class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                + Nuovo Tipo
            </a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Codice</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Colore</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tornei</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($types as $type)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            {{ $type->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $type->code }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-block w-6 h-6 rounded" 
                                  style="background-color: {{ $type->calendar_color ?? '#3B82F6' }}"></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $type->tournaments_count ?? 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($type->is_active)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Attivo
                                </span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    Inattivo
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="{{ route('super-admin.tournament-types.edit', $type) }}" 
                               class="text-indigo-600 hover:text-indigo-900 mr-3">Modifica</a>
                            
                            <form action="{{ route('super-admin.tournament-types.toggle-active', $type) }}" 
                                  method="POST" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" 
                                        class="text-yellow-600 hover:text-yellow-900 mr-3">
                                    {{ $type->is_active ? 'Disattiva' : 'Attiva' }}
                                </button>
                            </form>
                            
                            @if(!($type->tournaments_count ?? 0))
                                <form action="{{ route('super-admin.tournament-types.destroy', $type) }}" 
                                      method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="text-red-600 hover:text-red-900"
                                            onclick="return confirm('Sei sicuro di voler eliminare questo tipo?')">
                                        Elimina
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                            Nessun tipo di torneo trovato.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4">
        {{ $types->links() }}
    </div>
</div>
@endsection
