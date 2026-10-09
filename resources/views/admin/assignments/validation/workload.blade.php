@extends('layouts.admin')

@section('title', 'Carico arbitri')

@section('content')
@php $scale = max(1, $stats['top'], $max); @endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Carico arbitri</h1>
            <p class="mt-2 text-sm text-gray-600">
                Tutti gli arbitri attivi, dal più carico al meno carico.
            </p>
            <div class="mt-1">@include('admin.assignments.validation.partials.counts-legend')</div>
        </div>
        <a href="{{ route('admin.assignment-validation.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            ← Torna
        </a>
    </div>

    {{-- Soglie --}}
    <form method="GET" class="bg-white shadow rounded-lg mb-6 p-4 flex flex-wrap items-end gap-4">
        <div>
            <label for="min" class="block text-sm font-medium text-gray-700 mb-1">Minimo</label>
            <input type="number" id="min" name="min" value="{{ $min }}" min="0" max="50" class="w-24 border-gray-300 rounded-md shadow-sm">
        </div>
        <div>
            <label for="max" class="block text-sm font-medium text-gray-700 mb-1">Massimo</label>
            <input type="number" id="max" name="max" value="{{ $max }}" min="0" max="50" class="w-24 border-gray-300 rounded-md shadow-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Applica</button>
        <p class="text-xs text-gray-500">
            <span class="inline-block w-3 h-3 rounded bg-amber-200 align-middle"></span> sotto il minimo
            <span class="inline-block w-3 h-3 rounded bg-red-200 align-middle ml-3"></span> sopra il massimo
        </p>
    </form>

    {{-- Riepilogo --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white p-4 shadow rounded-lg"><p class="text-sm text-gray-500">Arbitri</p><p class="text-2xl font-bold">{{ $stats['referees'] }}</p></div>
        <div class="bg-white p-4 shadow rounded-lg"><p class="text-sm text-gray-500">Designazioni</p><p class="text-2xl font-bold">{{ $stats['designations'] }}</p></div>
        <div class="bg-white p-4 shadow rounded-lg"><p class="text-sm text-gray-500">Media per arbitro</p><p class="text-2xl font-bold">{{ $stats['avg'] }}</p></div>
        <div class="bg-white p-4 shadow rounded-lg"><p class="text-sm text-gray-500">Sotto il minimo</p><p class="text-2xl font-bold text-amber-600">{{ $stats['below'] }}</p></div>
        <div class="bg-white p-4 shadow rounded-lg"><p class="text-sm text-gray-500">Sopra il massimo</p><p class="text-2xl font-bold text-red-600">{{ $stats['above'] }}</p></div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Arbitro</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Livello</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zona</th>
                    @include('admin.assignments.validation.partials.counts-head')
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-1/4">Carico</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($rows as $item)
                    @php
                        $n = $item['assignments_count'];
                        $rowClass = $n > $max ? 'bg-red-50' : ($n < $min ? 'bg-amber-50' : '');
                        $barClass = $n > $max ? 'bg-red-400' : ($n < $min ? 'bg-amber-400' : 'bg-blue-400');
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td class="px-4 py-2">
                            <a href="{{ route('admin.users.show', $item['referee']) }}" class="font-medium text-gray-900 hover:text-blue-700">{{ $item['referee']->name }}</a>
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-700">{{ $item['referee']->level }}</td>
                        <td class="px-4 py-2 text-sm text-gray-700">{{ $item['referee']->zone->name ?? '-' }}</td>
                        @include('admin.assignments.validation.partials.counts-cells')
                        <td class="px-4 py-2">
                            <div class="h-3 bg-gray-100 rounded">
                                <div class="h-3 rounded {{ $barClass }}" style="width: {{ round($n / $scale * 100) }}%"></div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">Nessun arbitro attivo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
