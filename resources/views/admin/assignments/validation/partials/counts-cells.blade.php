{{-- La colonna che conta per il livello dell'arbitro e' in evidenza --}}
@php
    $on = 'px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800 font-bold';
    $off = 'px-3 py-1 rounded-full text-sm text-gray-600';
@endphp
<td class="px-4 py-2 text-center">
    <span class="{{ $item['basis'] === 'zonal' ? $on : $off }}">{{ $item['zonal_count'] }}</span>
</td>
<td class="px-4 py-2 text-center">
    <span class="{{ $item['basis'] === 'national' ? $on : $off }}">{{ $item['national_count'] }}</span>
    @if($item['national_observers'] > 0)
        <div class="text-xs text-gray-500 mt-1">di cui {{ $item['national_observers'] }} da osservatore</div>
    @endif
</td>
<td class="px-4 py-2 text-center text-sm {{ $item['zonal_availabilities'] > 0 ? 'text-gray-800' : 'text-red-600' }}">{{ $item['zonal_availabilities'] }}</td>
<td class="px-4 py-2 text-center text-sm text-gray-800">{{ $item['national_availabilities'] }}</td>
