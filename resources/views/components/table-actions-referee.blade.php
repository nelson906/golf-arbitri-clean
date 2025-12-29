@props(['user' => null, 'referee' => null])

@php
    // Supporta entrambi i nomi di variabile per flessibilità
    $referee = $referee ?? $user;
@endphp

<div class="flex items-center justify-end space-x-2">
    {{-- Visualizza --}}
    <a href="{{ route('admin.users.show', $referee) }}"
       class="text-indigo-600 hover:text-indigo-900"
       title="Visualizza">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
        </svg>
    </a>

    {{-- Modifica --}}
    <a href="{{ route('admin.users.edit', $referee) }}"
       class="text-blue-600 hover:text-blue-900"
       title="Modifica">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
        </svg>
    </a>

    {{-- Disponibilità --}}
    <a href="{{ route('admin.users.show', $referee) }}#availabilities"
       class="text-green-600 hover:text-green-900"
       title="Visualizza Disponibilità">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
    </a>

    {{-- Attiva/Disattiva --}}
    @if($referee->is_active)
        <form action="{{ route('admin.users.toggle-active', $referee) }}"
              method="POST"
              class="inline"
              onsubmit="return confirm('Sei sicuro di voler disattivare questo arbitro?');">
            @csrf
            <button type="submit"
                    class="text-yellow-600 hover:text-yellow-900"
                    title="Disattiva">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z"></path>
                </svg>
            </button>
        </form>
    @else
        <form action="{{ route('admin.users.toggle-active', $referee) }}"
              method="POST"
              class="inline">
            @csrf
            <button type="submit"
                    class="text-green-600 hover:text-green-900"
                    title="Attiva">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </button>
        </form>
    @endif

    {{-- Elimina  --}}
        <form action="{{ route('admin.users.destroy', $referee) }}"
              method="POST"
              class="inline"
              onsubmit="return confirm('Sei sicuro di voler eliminare questo arbitro? Questa azione non può essere annullata.');">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="text-red-600 hover:text-red-900"
                    title="Elimina">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        </form>
</div>
