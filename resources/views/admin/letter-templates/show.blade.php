@extends('layouts.admin')

@section('title', 'Dettagli Template')

@section('content')
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.letter-templates.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-gray-900">{{ $template->name }}</h1>

                {{-- Status Badges --}}
                <div class="flex space-x-2">
                    @if($template->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Attivo
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            Inattivo
                        </span>
                    @endif

                    @if($template->is_default)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            ⭐ Predefinito
                        </span>
                    @endif

                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ ucfirst($template->type) }}
                    </span>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex space-x-2">
                <a href="{{ route('admin.letter-templates.preview', $template) }}"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Anteprima
                </a>

                <a href="{{ route('admin.letter-templates.edit', $template) }}"
                   class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifica
                </a>
            </div>
        </div>
    </div>

    {{-- Template Details --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Subject --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Oggetto Email</h3>
                <div class="bg-gray-50 rounded-md p-4">
                    <p class="text-gray-800 font-medium">{{ $template->subject }}</p>
                </div>
            </div>

            {{-- Body --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Contenuto Template</h3>
                <div class="bg-gray-50 rounded-md p-4">
                    <div class="prose max-w-none">
                        {!! nl2br(e($template->body)) !!}
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Template Info --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Informazioni Template</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Tipo</dt>
                        <dd class="text-sm text-gray-900">{{ ucfirst($template->type) }}</dd>
                    </div>

                    @if($template->zone)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Zona</dt>
                        <dd class="text-sm text-gray-900">{{ $template->zone->name }}</dd>
                    </div>
                    @endif

                    @if($template->tournamentType)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Tipo Torneo</dt>
                        <dd class="text-sm text-gray-900">{{ $template->tournamentType->name }}</dd>
                    </div>
                    @endif

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Stato</dt>
                        <dd class="text-sm text-gray-900">
                            {{ $template->is_active ? 'Attivo' : 'Inattivo' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Predefinito</dt>
                        <dd class="text-sm text-gray-900">
                            {{ $template->is_default ? 'Sì' : 'No' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Creato</dt>
                        <dd class="text-sm text-gray-900">{{ $template->created_at->format('d/m/Y H:i') }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Ultimo Aggiornamento</dt>
                        <dd class="text-sm text-gray-900">{{ $template->updated_at->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Variables Info --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Variabili Disponibili</h3>
                <div class="space-y-2">
                    @php
                        $availableVariables = [
                            'tournament_name' => 'Nome del torneo',
                            'tournament_dates' => 'Date del torneo',
                            'club_name' => 'Nome del circolo',
                            'club_address' => 'Indirizzo del circolo',
                            'referee_name' => 'Nome dell\'arbitro',
                            'assignment_role' => 'Ruolo assegnazione',
                            'zone_name' => 'Nome della zona',
                            'assigned_date' => 'Data di assegnazione',
                            'tournament_category' => 'Categoria torneo',
                        ];
                    @endphp

                    @foreach($availableVariables as $variable => $description)
                        <div class="flex justify-between items-center text-xs">
                            <code class="bg-gray-100 px-2 py-1 rounded">{{$variable}}</code>
                            <span class="text-gray-500">{{ $description }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Actions --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Azioni</h3>
                <div class="space-y-3">
                    <form method="POST" action="{{ route('admin.letter-templates.duplicate', $template) }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">
                            📋 Duplica Template
                        </button>
                    </form>

                    @if(!$template->is_default)
                    <button onclick="setAsDefault({{ $template->id }})"
                            class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">
                        ⭐ Imposta come Predefinito
                    </button>
                    @endif

                    <button onclick="toggleActive({{ $template->id }})"
                            class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">
                        {{ $template->is_active ? '🔒 Disattiva' : '✅ Attiva' }}
                    </button>

                    <form method="POST" action="{{ route('admin.letter-templates.destroy', $template) }}"
                          onsubmit="return confirm('Sei sicuro di voler eliminare questo template?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-md">
                            🗑️ Elimina Template
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleActive(templateId) {
    fetch(`/admin.letter-templates/${templateId}/toggle-active`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function setAsDefault(templateId) {
    fetch(`/admin.letter-templates/${templateId}/set-default`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}
</script>
@endsection
