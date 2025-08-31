@props([
    'type' => 'primary',
    'size' => 'sm',
    'action' => null,
    'icon' => null,
    'href' => null,
    'confirm' => null,
    'method' => 'GET'
])

@php
    $buttonClasses = [
        'primary' => 'bg-indigo-600 text-white hover:bg-indigo-700 focus:ring-indigo-500',
        'secondary' => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
        'success' => 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500',
        'warning' => 'bg-yellow-600 text-white hover:bg-yellow-700 focus:ring-yellow-500',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
        'info' => 'bg-blue-500 text-white hover:bg-blue-600 focus:ring-blue-400',
        'light' => 'bg-gray-100 text-gray-700 hover:bg-gray-200 focus:ring-gray-400',
        'dark' => 'bg-gray-800 text-white hover:bg-gray-900 focus:ring-gray-600',
        'outline-primary' => 'border border-indigo-500 text-indigo-600 hover:bg-indigo-50 focus:ring-indigo-500',
        'outline-secondary' => 'border border-gray-500 text-gray-600 hover:bg-gray-50 focus:ring-gray-500'
    ][$type] ?? 'bg-indigo-600 text-white hover:bg-indigo-700 focus:ring-indigo-500';

    $sizeClasses = [
        'xs' => 'px-2 py-1 text-xs',
        'sm' => 'px-3 py-1 text-sm',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-6 py-3 text-base',
        'xl' => 'px-8 py-4 text-lg'
    ][$size] ?? 'px-3 py-1 text-sm';

    $iconSizes = [
        'xs' => 'w-3 h-3',
        'sm' => 'w-3 h-3',
        'md' => 'w-4 h-4',
        'lg' => 'w-5 h-5',
        'xl' => 'w-6 h-6'
    ][$size] ?? 'w-3 h-3';

    $baseClasses = 'inline-flex items-center rounded-md font-medium focus:outline-none focus:ring-2 transition duration-150 ease-in-out';
    $classes = "{$baseClasses} {$buttonClasses} {$sizeClasses}";

    $icons = [
        'eye' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>',
        'edit' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>',
        'trash' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>',
        'plus' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>',
        'search' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>',
        'filter' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"></path>',
        'clear' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>',
        'save' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3-3-3m3-3v12"></path>',
        'back' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>',
        'download' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>',
        'upload' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>',
        'refresh' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>'
    ];
@endphp

@if($href)
    <a href="{{ $href }}"
       class="{{ $classes }}"
       {{ $attributes->except(['type', 'size', 'action', 'icon', 'href', 'confirm', 'method']) }}>
        @if($icon && isset($icons[$icon]))
            <svg class="{{ $iconSizes }} mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {!! $icons[$icon] !!}
            </svg>
        @endif
        {{ $slot }}
    </a>
@elseif($action && $method === 'DELETE')
    <form action="{{ $action }}" method="POST" class="inline-block"
          @if($confirm) onsubmit="return confirm('{{ $confirm }}')" @endif>
        @csrf
        @method('DELETE')
        <button type="submit"
                class="{{ $classes }}"
                {{ $attributes->except(['type', 'size', 'action', 'icon', 'href', 'confirm', 'method']) }}>
            @if($icon && isset($icons[$icon]))
                <svg class="{{ $iconSizes }} mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    {!! $icons[$icon] !!}
                </svg>
            @endif
            {{ $slot }}
        </button>
    </form>
@elseif($action && $method === 'POST')
    <form action="{{ $action }}" method="POST" class="inline-block"
          @if($confirm) onsubmit="return confirm('{{ $confirm }}')" @endif>
        @csrf
        <button type="submit"
                class="{{ $classes }}"
                {{ $attributes->except(['type', 'size', 'action', 'icon', 'href', 'confirm', 'method']) }}>
            @if($icon && isset($icons[$icon]))
                <svg class="{{ $iconSizes }} mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    {!! $icons[$icon] !!}
                </svg>
            @endif
            {{ $slot }}
        </button>
    </form>
@else
    <button type="{{ $action ?? 'button' }}"
            class="{{ $classes }}"
            {{ $attributes->except(['type', 'size', 'action', 'icon', 'href', 'confirm', 'method']) }}
            @if($confirm) onclick="return confirm('{{ $confirm }}')" @endif>
        @if($icon && isset($icons[$icon]))
            <svg class="{{ $iconSizes }} mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {!! $icons[$icon] !!}
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif
