@extends('layouts.admin')

@section('title', 'Anteprima Template')

@section('content')
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.letter-templates.show', $template) }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-gray-900">Anteprima: {{ $template->name }}</h1>

                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ ucfirst($template->type) }}
                </span>
            </div>

            <div class="flex space-x-2">
                <a href="{{ route('admin.letter-templates.edit', $template) }}"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifica Template
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {{-- Email Preview --}}
        <div class="lg:col-span-3">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                {{-- Email Header --}}
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center space-x-4">
                        <div class="flex-1">
                            <div class="text-sm text-gray-500">Da:</div>
                            <div class="font-medium">Federazione Italiana Golf &lt;noreply@federgolf.it&gt;</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">Data:</div>
                            <div class="font-medium">{{ now()->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="text-sm text-gray-500">Oggetto:</div>
                        <div class="font-bold text-lg text-gray-900">{{ $previewSubject }}</div>
                    </div>
                </div>

                {{-- Email Body --}}
                <div class="px-6 py-8">
                    <div class="prose max-w-none">
                        {!! nl2br(e($previewBody)) !!}
                    </div>

                    {{-- Email Footer --}}
                    <div class="mt-8 pt-6 border-t border-gray-200 text-sm text-gray-500">
                        <p>
                            <strong>Federazione Italiana Golf</strong><br>
                            Via Flaminia Nuova, 830 - 00191 Roma<br>
                            Tel: 06 3231 8941 | Email: info@federgolf.it<br>
                            <a href="https://www.federgolf.it" class="text-blue-600">www.federgolf.it</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sample Data Sidebar --}}
        <div class="space-y-6">
            {{-- Template Info --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">ℹ️ Informazioni</h3>
                <div class="space-y-2 text-sm">
                    <div>
                        <span class="font-medium text-gray-500">Template:</span>
                        <div class="text-gray-900">{{ $template->name }}</div>
                    </div>

                    <div>
                        <span class="font-medium text-gray-500">Tipo:</span>
                        <div class="text-gray-900">{{ ucfirst($template->type) }}</div>
                    </div>

                    @if($template->zone)
                    <div>
                        <span class="font-medium text-gray-500">Zona:</span>
                        <div class="text-gray-900">{{ $template->zone->name }}</div>
                    </div>
                    @endif

                    @if($template->tournamentType)
                    <div>
                        <span class="font-medium text-gray-500">Tipo Torneo:</span>
                        <div class="text-gray-900">{{ $template->tournamentType->name }}</div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Sample Data Used --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">🎭 Dati di Esempio</h3>
                <div class="space-y-2">
                    @foreach($sampleData as $variable => $value)
                        <div class="text-xs">
                            <div class="font-mono text-gray-500">{{$variable}}</div>
                            <div class="text-gray-900 font-medium">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 p-3 bg-blue-50 rounded-md">
                    <p class="text-xs text-blue-700">
                        💡 <strong>Nota:</strong> Questi sono dati di esempio utilizzati per mostrare come apparirà l'email finale. I dati reali verranno sostituiti automaticamente durante l'invio.
                    </p>
                </div>
            </div>

            {{-- Variables Found --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">🔧 Variabili Trovate</h3>

                @php
                    $templateText = $template->subject . ' ' . $template->body;
                    preg_match_all('/\{\{([^}]+)\}\}/', $templateText, $matches);
                    $foundVariables = array_unique($matches[1]);
                @endphp

                @if(count($foundVariables) > 0)
                    <div class="space-y-1">
                        @foreach($foundVariables as $variable)
                            <div class="flex items-center justify-between text-xs">
                                <code class="bg-gray-100 px-2 py-1 rounded">{{$variable}}</code>
                                @if(array_key_exists($variable, $sampleData))
                                    <span class="text-green-600">✓</span>
                                @else
                                    <span class="text-red-600">⚠️</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if(count(array_diff($foundVariables, array_keys($sampleData))) > 0)
                        <div class="mt-3 p-2 bg-yellow-50 rounded-md">
                            <p class="text-xs text-yellow-700">
                                ⚠️ Alcune variabili nel template non hanno dati di esempio.
                            </p>
                        </div>
                    @endif
                @else
                    <p class="text-xs text-gray-500">
                        Nessuna variabile trovata in questo template.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
