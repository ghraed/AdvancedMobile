@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'help' => null,
])

<div {{ $attributes->class(['admin-field', 'has-error' => $errors->has($name)]) }}>
    <label class="admin-label" for="{{ $name }}">{{ $label }}</label>
    @if ($type === 'textarea')
        <textarea
            id="{{ $name }}"
            name="{{ $name }}"
            class="admin-textarea"
            @required($required)
            {{ $attributes->except(['class']) }}
        >{{ old($name, $value) }}</textarea>
    @else
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $value) }}"
            class="admin-input"
            @required($required)
            {{ $attributes->except(['class']) }}
        >
    @endif

    @if ($help)
        <div class="admin-help">{{ $help }}</div>
    @endif

    @error($name)
        <div class="admin-error-text">{{ $message }}</div>
    @enderror
</div>
