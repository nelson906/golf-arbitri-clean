@props([
    'title' => '',
    'description' => '',
    'createRoute' => null,
    'createText' => 'Nuovo',
    'createColor' => 'indigo',
    'secondaryRoute' => null,
    'secondaryText' => '',
    'secondaryColor' => 'green',
    'additionalActions' => ''
])

<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">{{ $title }}</h1>
        @if($description)
            <p class="mt-2 text-gray-600">{{ $description }}</p>
        @endif
    </div>
    <div class="flex space-x-4">
        @if($additionalActions)
            {!! $additionalActions !!}
        @endif

        @if($secondaryRoute)
            <a href="{{ $secondaryRoute }}"
               class="bg-{{ $secondaryColor }}-600 text-white px-4 py-2 rounded-lg hover:bg-{{ $secondaryColor }}-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                {{ $secondaryText }}
            </a>
        @endif

        @if($createRoute)
            <a href="{{ $createRoute }}"
               class="bg-{{ $createColor }}-600 text-white px-4 py-2 rounded-lg hover:bg-{{ $createColor }}-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                {{ $createText }}
            </a>
        @endif
    </div>
</div>
