{{-- File: resources/views/admin/users/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Gestione Utenti')

@section('content')
    <div class="py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">👥 Gestione Utenti</h1>
                <p class="text-gray-600 mt-1">
                    @if (isset($isNationalAdmin) && $isNationalAdmin)
                        Gestione completa utenti sistema
                    @else
                        Gestione utenti della tua zona
                    @endif
                </p>
            </div>
            <div>
                <a href="{{ route('admin.users.create') }}"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg transition-colors">
                    ➕ Nuovo Utente
                </a>
            </div>
        </div>

        {{-- Messaggi Flash --}}
        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        {{-- Filtri --}}
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-4">
                <form method="GET" action="{{ route('admin.users.index') }}"
                    class="space-y-4 md:space-y-0 md:flex md:gap-4">
                    {{-- Ricerca --}}
                    <div class="flex-1">
                        <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Cerca per nome, email o codice arbitro..."
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    {{-- Ordina per cognome --}}
                    <div>
                        <select name="sort" id="sort"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Seleziona ordinamento</option>
                            <option value="surname_asc" {{ request('sort') == 'surname_asc' ? 'selected' : '' }}>
                                Cognome A-Z
                            </option>
                            <option value="surname_desc" {{ request('sort') == 'surname_desc' ? 'selected' : '' }}>
                                Cognome Z-A
                            </option>
                            <option value="name_asc" {{ request('sort') == 'name_asc' ? 'selected' : '' }}>
                                Nome A-Z
                            </option>
                        </select>
                    </div>

                    {{-- Tipo Utente --}}
                    <div>
                        <select name="user_type"
                            class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Tutti i tipi</option>
                            <option value="referee" {{ request('user_type') == 'referee' ? 'selected' : '' }}>Arbitro
                            </option>
                            <option value="admin" {{ request('user_type') == 'admin' ? 'selected' : '' }}>Admin Zona
                            </option>
                            @if (isset($isNationalAdmin) && $isNationalAdmin)
                                <option value="national_admin"
                                    {{ request('user_type') == 'national_admin' ? 'selected' : '' }}>Admin Nazionale
                                </option>
                                <option value="super_admin" {{ request('user_type') == 'super_admin' ? 'selected' : '' }}>
                                    Super Admin</option>
                            @endif
                        </select>
                    </div>

                    {{-- Livello (solo per arbitri) --}}
                    <div>
                        <select name="level"
                            class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Tutti i livelli</option>
                            @foreach (referee_levels() as $key => $label)
                                <option value="{{ $key }}" {{ request('level') == $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Stato --}}
                    <div>
                        <select name="status"
                            class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="active"
                                {{ !request()->has('status') || request('status') == 'active' ? 'selected' : '' }}>Solo
                                attivi</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Solo inattivi
                            </option>
                            <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>Tutti</option>
                        </select>
                    </div>

                    {{-- Zona (solo per admin nazionali) --}}
                    @if (isset($isNationalAdmin) && $isNationalAdmin && isset($zones))
                        <div>
                            <select name="zone_id"
                                class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Tutte le zone</option>
                                @foreach ($zones as $zone)
                                    <option value="{{ $zone->id }}"
                                        {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                                        {{ $zone->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Bottoni --}}
                    <div class="flex gap-2">
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors">
                            🔍 Cerca
                        </button>
                        <a href="{{ route('admin.users.index') }}"
                            class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md transition-colors">
                            ↻ Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Tabella Utenti --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Utente
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Email
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Tipo
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Livello
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Zona
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Stato
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Azioni
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div
                                            class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $user->name }}
                                        </div>
                                        @if (isset($user->referee_code) && $user->referee_code)
                                            <div class="text-xs text-gray-500">
                                                Codice: {{ $user->referee_code }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $user->email }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $typeColors = [
                                        'referee' => 'bg-green-100 text-green-800',
                                        'admin' => 'bg-blue-100 text-blue-800',
                                        'national_admin' => 'bg-purple-100 text-purple-800',
                                        'super_admin' => 'bg-red-100 text-red-800',
                                    ];
                                    $typeLabels = [
                                        'referee' => 'Arbitro',
                                        'admin' => 'Admin Zona',
                                        'national_admin' => 'Admin Nazionale',
                                        'super_admin' => 'Super Admin',
                                    ];
                                @endphp
                                <span
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $typeColors[$user->user_type] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $typeLabels[$user->user_type] ?? $user->user_type }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if ($user->user_type === 'referee' && isset($user->level))
                                    @php
                                        $levelColors = [
                                            'Aspirante' => 'bg-yellow-100 text-yellow-800',
                                            '1_livello' => 'bg-blue-100 text-blue-800',
                                            'Regionale' => 'bg-green-100 text-green-800',
                                            'Nazionale' => 'bg-purple-100 text-purple-800',
                                            'Internazionale' => 'bg-red-100 text-red-800',
                                            'Archivio' => 'bg-gray-100 text-gray-800',
                                        ];
                                    @endphp
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $levelColors[$user->level] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ referee_level_label($user->level) }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $user->zone->name ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($user->is_active)
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Attivo
                                    </span>
                                @else
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Inattivo
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end gap-2">
                                    {{-- Visualizza --}}
                                    <a href="{{ route('admin.users.show', $user) }}"
                                        class="text-blue-600 hover:text-blue-900" title="Visualizza">
                                        👁️
                                    </a>

                                    {{-- Modifica --}}
                                    @if ((isset($isNationalAdmin) && $isNationalAdmin) || auth()->user()->zone_id == $user->zone_id)
                                        <a href="{{ route('admin.users.edit', $user) }}"
                                            class="text-yellow-600 hover:text-yellow-900" title="Modifica">
                                            ✏️
                                        </a>
                                    @endif

                                    {{-- Toggle Attivo (solo admin nazionali) --}}
                                    @if (isset($isNationalAdmin) && $isNationalAdmin)
                                        <form action="{{ route('admin.users.toggle-active', $user) }}" method="POST"
                                            class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                class="{{ $user->is_active ? 'text-orange-600 hover:text-orange-900' : 'text-green-600 hover:text-green-900' }}"
                                                title="{{ $user->is_active ? 'Disattiva' : 'Attiva' }}">
                                                {{ $user->is_active ? '🔒' : '🔓' }}
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Elimina (solo super admin) --}}
                                    @if (isset($isSuperAdmin) && $isSuperAdmin && $user->id !== auth()->id())
                                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                            class="inline"
                                            onsubmit="return confirm('Sei sicuro di voler eliminare questo utente?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900"
                                                title="Elimina">
                                                🗑️
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                <div class="py-8">
                                    <span class="text-4xl">👥</span>
                                    <p class="mt-2">Nessun utente trovato</p>
                                    @if (request()->has('search') || request()->has('user_type') || request()->has('zone_id'))
                                        <a href="{{ route('admin.users.index') }}"
                                            class="mt-4 inline-block text-blue-600 hover:text-blue-800">
                                            Rimuovi filtri
                                        </a>
                                    @else
                                        <a href="{{ route('admin.users.create') }}"
                                            class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors">
                                            Crea il primo utente
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Paginazione --}}
            @if ($users->hasPages())
                <div class="bg-gray-50 px-6 py-3 border-t border-gray-200">
                    {{ $users->withQueryString()->links() }}
                </div>
            @endif
        </div>

        {{-- Statistiche --}}
        <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <span class="text-2xl mr-3">📊</span>
                    <div>
                        <p class="text-sm text-gray-500">Totale Utenti</p>
                        <p class="text-xl font-semibold text-gray-900">{{ $users->total() }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <span class="text-2xl mr-3">👨‍⚖️</span>
                    <div>
                        <p class="text-sm text-gray-500">Arbitri</p>
                        <p class="text-xl font-semibold text-gray-900">
                            {{ \App\Models\User::where('user_type', 'referee')->count() }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <span class="text-2xl mr-3">👔</span>
                    <div>
                        <p class="text-sm text-gray-500">Amministratori</p>
                        <p class="text-xl font-semibold text-gray-900">
                            {{ \App\Models\User::whereIn('user_type', ['admin', 'national_admin', 'super_admin'])->count() }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <span class="text-2xl mr-3">🌍</span>
                    <div>
                        <p class="text-sm text-gray-500">Zone</p>
                        <p class="text-xl font-semibold text-gray-900">
                            {{ \App\Models\Zone::count() }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
let submitTimeout;

document.addEventListener('DOMContentLoaded', function() {
    const selects = document.querySelectorAll('select');

    selects.forEach(select => {
        select.addEventListener('change', function() {
            clearTimeout(submitTimeout);
            submitTimeout = setTimeout(() => {
                this.closest('form').submit();
            }, 300); // Attendi 300ms
        });
    });
});
</script>
@endsection
