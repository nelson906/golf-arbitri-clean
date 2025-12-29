@props(['status' => 'default'])

<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
    @if($status === 'Attivo') bg-green-100 text-green-800
    @elseif($status === 'Inattivo') bg-red-100 text-red-800
    @else bg-gray-100 text-gray-700 @endif">
    {{ $status }}
</span>
