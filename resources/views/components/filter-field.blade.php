@props([
    'name',
    'label',
    'type' => 'text',
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'empty_option' => 'Tutti',
    'multiple' => false,
])

@php
    $inputClasses =
        'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50';
    $currentValue = old($name, $value);

    // ✅ FIX SISTEMICO: Converte Collections Laravel in array associativi corretti
    if (is_object($options) && method_exists($options, 'pluck')) {
        // Collection Laravel → [id => name]
        $options = $options->pluck('name', 'id')->toArray();
    }
@endphp
<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1">
        {{ $label }}
    </label>

    @if ($type === 'select')
        <select name="{{ $name }}" id="{{ $name }}" class="{{ $inputClasses }}"
            @if ($multiple) multiple @endif {{ $attributes }}>
            @if (!$multiple && $empty_option)
                <option value="">{{ $empty_option }}</option>
            @endif

            @if (is_array($options) && count($options) > 0)
                @foreach ($options as $key => $option)
                    <option value="{{ $key }}" {{ $currentValue == $key ? 'selected' : '' }}>
                        {{ $option }}
                    </option>
                @endforeach
            @endif
        </select>
    @elseif($type === 'date')
        <input type="date" name="{{ $name }}" id="{{ $name }}" value="{{ $currentValue }}"
            class="{{ $inputClasses }}" {{ $attributes }}>
    @elseif($type === 'number')
        <input type="number" name="{{ $name }}" id="{{ $name }}" value="{{ $currentValue }}"
            class="{{ $inputClasses }}" @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            {{ $attributes }}>
    @elseif($type === 'email')
        <input type="email" name="{{ $name }}" id="{{ $name }}" value="{{ $currentValue }}"
            class="{{ $inputClasses }}" @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            {{ $attributes }}>
    @elseif($type === 'search')
        <input type="search" name="{{ $name }}" id="{{ $name }}" value="{{ $currentValue }}"
            class="{{ $inputClasses }}" @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            {{ $attributes }}>
    @else
        <input type="text" name="{{ $name }}" id="{{ $name }}" value="{{ $currentValue }}"
            class="{{ $inputClasses }}" @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            {{ $attributes }}>
    @endif
</div>
