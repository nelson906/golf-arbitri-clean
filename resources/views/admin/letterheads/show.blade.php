@extends('layouts.admin')

@section('title', 'Dettagli Letterhead')

@section('content')
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.letterheads.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-gray-900">{{ $letterhead->title }}</h1>

                {{-- Status Badges --}}
                <div class="flex space-x-2">
                    @if($letterhead->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Attiva
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            Inattiva
                        </span>
                    @endif

                    @if($letterhead->is_default)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            ⭐ Predefinita
                        </span>
                    @endif

                    @if($letterhead->zone)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            {{ $letterhead->zone->name }}
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            Globale
                        </span>
                    @endif
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex space-x-2">
                <a href="{{ route('admin.letterheads.preview', $letterhead) }}"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Anteprima
                </a>

                <a href="{{ route('admin.letterheads.edit', $letterhead) }}"
                   class="inline-flex items-center px-3 py-2 border border-transparent shadow-sm text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifica
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Informazioni Generali --}}
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Informazioni Generali</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Titolo</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $letterhead->title }}</dd>
                        </div>

                        @if($letterhead->description)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Descrizione</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $letterhead->description }}</dd>
                            </div>
                        @endif

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Zona</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $letterhead->zone ? $letterhead->zone->name : 'Globale' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Stato</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $letterhead->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $letterhead->is_active ? 'Attiva' : 'Inattiva' }}
                                </span>
                                @if($letterhead->is_default)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 ml-2">
                                        Predefinita
                                    </span>
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Creata</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $letterhead->created_at->format('d/m/Y H:i') }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Ultima modifica</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $letterhead->updated_at->format('d/m/Y H:i') }}
                                @if($letterhead->updatedBy)
                                    <br><span class="text-xs text-gray-500">da {{ $letterhead->updatedBy->name }}</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Testo Header e Footer --}}
            @if($letterhead->header_text || $letterhead->footer_text)
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Contenuto</h3>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        @if($letterhead->header_text)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 mb-2">Testo Header</dt>
                                <dd class="bg-gray-50 rounded-md p-3 text-sm text-gray-900 whitespace-pre-line">{{ $letterhead->header_text }}</dd>
                            </div>
                        @endif

                        @if($letterhead->footer_text)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 mb-2">Testo Footer</dt>
                                <dd class="bg-gray-50 rounded-md p-3 text-sm text-gray-900 whitespace-pre-line">{{ $letterhead->footer_text }}</dd>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Informazioni di Contatto --}}
            @if($letterhead->contact_info && array_filter($letterhead->contact_info))
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Informazioni di Contatto</h3>
                    </div>
                    <div class="px-6 py-4">
                        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @if(!empty($letterhead->contact_info['address']))
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Indirizzo</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $letterhead->contact_info['address'] }}</dd>
                                </div>
                            @endif

                            @if(!empty($letterhead->contact_info['phone']))
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Telefono</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <a href="tel:{{ $letterhead->contact_info['phone'] }}" class="text-indigo-600 hover:text-indigo-500">
                                            {{ $letterhead->contact_info['phone'] }}
                                        </a>
                                    </dd>
                                </div>
                            @endif

                            @if(!empty($letterhead->contact_info['email']))
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <a href="mailto:{{ $letterhead->contact_info['email'] }}" class="text-indigo-600 hover:text-indigo-500">
                                            {{ $letterhead->contact_info['email'] }}
                                        </a>
                                    </dd>
                                </div>
                            @endif

                            @if(!empty($letterhead->contact_info['website']))
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Sito Web</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <a href="{{ $letterhead->contact_info['website'] }}" target="_blank" class="text-indigo-600 hover:text-indigo-500">
                                            {{ $letterhead->contact_info['website'] }}
                                            <svg class="w-3 h-3 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                            </svg>
                                        </a>
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Logo --}}
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Logo</h3>
                </div>
                <div class="px-6 py-4">
                    @if($letterhead->logo_path)
                        <div class="text-center">
                            <img src="{{ Storage::url($letterhead->logo_path) }}"
                                 alt="Logo {{ $letterhead->title }}"
                                 class="max-h-32 max-w-full mx-auto object-contain border rounded">
                            <p class="mt-2 text-xs text-gray-500">{{ basename($letterhead->logo_path) }}</p>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="mt-2 text-sm text-gray-500">Nessun logo caricato</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Impostazioni --}}
            @if($letterhead->settings && array_filter($letterhead->settings))
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Impostazioni Layout</h3>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        {{-- Font Settings --}}
                        @if(!empty($letterhead->settings['font']))
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Font</h4>
                                <dl class="text-sm space-y-1">
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">Famiglia:</dt>
                                        <dd class="text-gray-900">{{ $letterhead->settings['font']['family'] ?? 'Arial' }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">Dimensione:</dt>
                                        <dd class="text-gray-900">{{ $letterhead->settings['font']['size'] ?? 11 }}pt</dd>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <dt class="text-gray-500">Colore:</dt>
                                        <dd class="flex items-center space-x-2">
                                            <span class="w-4 h-4 rounded border" style="background-color: {{ $letterhead->settings['font']['color'] ?? '#000000' }}"></span>
                                            <span class="text-gray-900">{{ $letterhead->settings['font']['color'] ?? '#000000' }}</span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        @endif

                        {{-- Margin Settings --}}
                        @if(!empty($letterhead->settings['margins']))
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Margini (mm)</h4>
                                <dl class="text-sm space-y-1">
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">Alto:</dt>
                                        <dd class="text-gray-900">{{ $letterhead->settings['margins']['top'] ?? 20 }}mm</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">Basso:</dt>
                                        <dd class="text-gray-900">{{ $letterhead->settings['margins']['bottom'] ?? 20 }}mm</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">Sinistro:</dt>
                                        <dd class="text-gray-900">{{ $letterhead->settings['margins']['left'] ?? 25 }}mm</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">Destro:</dt>
                                        <dd class="text-gray-900">{{ $letterhead->settings['margins']['right'] ?? 25 }}mm</dd>
                                    </div>
                                </dl>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Quick Actions --}}
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Azioni Rapide</h3>
                </div>
                <div class="px-6 py-4 space-y-3">
                    <a href="{{ route('admin.letterheads.edit', $letterhead) }}"
                       class="w-full inline-flex justify-center items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        ✏️ Modifica
                    </a>

                    <a href="{{ route('admin.letterheads.preview', $letterhead) }}"
                       class="w-full inline-flex justify-center items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                       target="_blank">
                        🔍 Anteprima
                    </a>

                    @if(!$letterhead->is_default)
                        <form method="POST" action="{{ route('admin.letterheads.duplicate', $letterhead) }}">
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex justify-center items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                📋 Duplica
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
