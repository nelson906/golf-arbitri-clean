<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
        @if(isset($subtitle))
            <p class="text-gray-600 mt-1">{{ $subtitle }}</p>
        @endif
    </div>

    @if(isset($actions))
        <div class="flex space-x-3">
            @foreach($actions as $action)
                <a href="{{ $action['url'] }}"
                   class="bg-{{ $action['color'] ?? 'blue' }}-600 hover:bg-{{ $action['color'] ?? 'blue' }}-700 text-white px-4 py-2 rounded-lg transition-colors">
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    @endif
</div>
