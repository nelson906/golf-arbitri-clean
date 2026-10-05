@extends('layouts.admin')

@section('page-title', 'Gestione Zone')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold">🌍 Gestione Zone</h2>
            <p class="text-sm text-gray-600 mt-1">
                L'email della zona è usata per la copia alla sezione nelle notifiche e come indirizzo di risposta delle mail zonali.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Codice</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Telefono</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Circoli</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Arbitri</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach ($zones as $zone)
                        @php $emailOk = $zone->email && filter_var($zone->email, FILTER_VALIDATE_EMAIL); @endphp
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $zone->code }}
                                @if ($zone->is_national)
                                    <span class="ml-1 px-2 py-0.5 text-xs bg-purple-100 text-purple-800 rounded">Nazionale</span>
                                @endif
                                @unless ($zone->is_active)
                                    <span class="ml-1 px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">Non attiva</span>
                                @endunless
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $zone->name }}</td>
                            <td class="px-6 py-4 text-sm">
                                @if ($emailOk)
                                    <span class="text-gray-900">{{ $zone->email }}</span>
                                @else
                                    <span class="text-red-600 font-medium" title="Il sistema usa al suo posto l'indirizzo standard della sezione">
                                        ⚠️ {{ $zone->email ?: 'mancante' }} — non è un'email valida
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $zone->phone }}</td>
                            <td class="px-6 py-4 text-center text-sm text-gray-900">{{ $zone->clubs_count }}</td>
                            <td class="px-6 py-4 text-center text-sm text-gray-900">{{ $zone->referees_count }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <a href="{{ route('super-admin.zones.edit', $zone) }}" class="text-indigo-600 hover:text-indigo-900">Modifica</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
