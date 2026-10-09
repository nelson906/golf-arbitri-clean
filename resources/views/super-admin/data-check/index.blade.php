@extends('layouts.admin')

@section('title', 'Controllo dati')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900">Controllo dati</h1>
    <p class="mt-2 mb-6 text-sm text-gray-600">
        Anomalie nei dati del database in uso. La pagina non corregge niente: ogni riga porta al torneo o all'arbitro da sistemare.
        <span class="ml-2 inline-block px-2 rounded bg-red-100 text-red-800">errore</span>
        <span class="ml-1 inline-block px-2 rounded bg-amber-100 text-amber-800">da verificare</span>
    </p>

    <div class="space-y-3">
        @foreach($checks as $check)
            @php $n = count($check['rows']); @endphp
            <details class="bg-white shadow rounded-lg" @if($n > 0 && $check['level'] === 'errore') open @endif>
                <summary class="px-4 py-3 cursor-pointer flex items-center gap-3">
                    @if($n === 0)
                        <span class="w-10 text-center px-2 py-0.5 rounded bg-green-100 text-green-800 text-sm font-bold">✓</span>
                    @else
                        <span class="w-10 text-center px-2 py-0.5 rounded text-sm font-bold {{ $check['level'] === 'errore' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}">{{ $n }}</span>
                    @endif
                    <span class="font-medium text-gray-900">{{ $check['title'] }}</span>
                </summary>
                <div class="px-4 pb-4">
                    <p class="text-xs text-gray-500 mb-2">{{ $check['why'] }}</p>
                    @if($n > 0)
                        <ul class="text-sm space-y-1">
                            @foreach($check['rows'] as $row)
                                <li>
                                    @if($row['url'])
                                        <a href="{{ $row['url'] }}" class="text-blue-700 hover:underline">{{ $row['label'] }}</a>
                                    @else
                                        {{ $row['label'] }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </details>
        @endforeach
    </div>
</div>
@endsection
