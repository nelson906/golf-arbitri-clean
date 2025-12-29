@props(['club'])

<div class="flex items-center justify-end space-x-2">
    {{-- Visualizza --}}
    <a href="{{ route('admin.clubs.show', $club) }}"
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
    <a href="{{ route('admin.clubs.edit', $club) }}"
       class="text-blue-600 hover:text-blue-900"
       title="Modifica">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
        </svg>
    </a>

    {{-- Attiva/Disattiva --}}
    <form action="{{ route('admin.clubs.toggle-active', $club) }}"
          method="POST"
          class="inline"
          onsubmit="return confirm('Sei sicuro di voler {{ $club->is_active ? 'disattivare' : 'attivare' }} questo club?');">
        @csrf
        <button type="submit"
                class="text-{{ $club->is_active ? 'yellow' : 'green' }}-600 hover:text-{{ $club->is_active ? 'yellow' : 'green' }}-900"
                title="{{ $club->is_active ? 'Disattiva' : 'Attiva' }}">
            @if($club->is_active)
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10 9V6a4 4 0 118 0v3M5 12h14l-1 7H6l-1-7z"></path>
                </svg>
            @else
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path>
                </svg>
            @endif
        </button>
    </form>

    {{-- Visualizza Tornei --}}
    <a href="{{ route('admin.tournaments.index', ['club_id' => $club->id]) }}"
       class="text-purple-600 hover:text-purple-900"
       title="Visualizza Tornei">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
        </svg>
    </a>

    {{-- Elimina (solo se non ha tornei attivi) --}}
    @if(!$club->tournaments()->whereIn('status', ['open', 'closed', 'assigned'])->exists())
        <form action="{{ route('admin.clubs.destroy', $club) }}"
              method="POST"
              class="inline"
              onsubmit="return confirm('Sei sicuro di voler eliminare questo club? Questa azione non può essere annullata.');">
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
    @endif
</div>
