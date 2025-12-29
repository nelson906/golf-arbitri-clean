@props([
    'entity',
    'route_prefix',
    'can_manage' => false,
    'can_delete' => false,
    'show_details' => true,
    'show_edit' => true,
    'show_delete' => true,
    'current_user_id' => null,
    'prevent_self_delete' => true,
    'custom_delete_confirm' => null,
    'extra_info' => null,
    'developer_mode' => false
])

@php
    use App\Http\Helpers\PermissionHelper;

    // Controlla se l'entità è dell'utente corrente
    $isOwnEntity = $current_user_id &&
                   ((method_exists($entity, 'user_id') && $entity->user_id === $current_user_id) ||
                    (method_exists($entity, 'id') && $entity->id === $current_user_id));

    // Determina se mostrare il pulsante elimina
    $showDeleteButton = $show_delete &&
                       $can_delete &&
                       (!$prevent_self_delete || !$isOwnEntity);

    // Messaggio di conferma predefinito
    $defaultConfirm = "⚠️ ELIMINAZIONE RECORD\n\n🔴 ATTENZIONE: Questa azione è IRREVERSIBILE!\n\n❌ Questa operazione NON può essere annullata!\n\n✅ Confermi l'eliminazione definitiva?";
    $confirmMessage = $custom_delete_confirm ?? $defaultConfirm;
@endphp

<div class="flex items-center justify-end space-x-2">
    {{-- Pulsante Dettagli --}}
    @if($show_details)
        <x-button
            type="primary"
            size="sm"
            icon="eye"
            href="{{ route($route_prefix . '.show', $entity) }}">
            Dettagli
        </x-button>
    @endif

    {{-- Pulsante Modifica --}}
    @if($show_edit && $can_manage)
        <x-button
            type="secondary"
            size="sm"
            icon="edit"
            href="{{ route($route_prefix . '.edit', $entity) }}">
            Modifica
        </x-button>
    @endif

    {{-- Pulsante Elimina --}}
    @if($showDeleteButton)
        <x-button
            type="danger"
            size="sm"
            icon="trash"
            action="{{ route($route_prefix . '.destroy', $entity) }}"
            method="DELETE"
            confirm="{{ $confirmMessage }}">
            Elimina
        </x-button>
    @endif

    {{-- Badge per il proprio profilo/entità --}}
    @if($isOwnEntity)
        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
            @if(str_contains($route_prefix, 'referee'))
                Il tuo profilo
            @else
                Tuo record
            @endif
        </span>
    @endif

    {{-- Badge per modalità sviluppatore --}}
    @if($developer_mode && isset($entity->user) && $entity->user->email === 'superadmin@grippa.it')
        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
            🔧 Account Sviluppatore
        </span>
    @endif

    {{-- Informazioni extra --}}
    @if($extra_info)
        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
            {{ $extra_info }}
        </span>
    @endif

    {{-- Slot per pulsanti personalizzati --}}
    {{ $slot }}
</div>
