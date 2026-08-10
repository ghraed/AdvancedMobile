@props([
    'name',
    'label',
    'options' => [],
    'selected' => null,
    'placeholder' => 'Select an option',
    'required' => false,
    'help' => null,
])

<div {{ $attributes->class(['admin-field', 'has-error' => $errors->has($name)]) }}>
    <label class="admin-label" for="{{ $name }}">{{ $label }}</label>
    <select id="{{ $name }}" name="{{ $name }}" class="admin-select" @required($required) {{ $attributes->except(['class']) }}>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected((string) old($name, $selected) === (string) $value)>{{ $text }}</option>
        @endforeach
    </select>

    @if ($help)
        <div class="admin-help">{{ $help }}</div>
    @endif

    @error($name)
        <div class="admin-error-text">{{ $message }}</div>
    @enderror
</div>
