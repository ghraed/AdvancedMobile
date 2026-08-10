@props([
    'name',
    'label',
    'accept' => null,
    'help' => null,
])

<div {{ $attributes->class(['admin-field', 'has-error' => $errors->has($name)]) }}>
    <label class="admin-label" for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="file" class="admin-file-input" @if($accept) accept="{{ $accept }}" @endif {{ $attributes->except(['class']) }}>

    @if ($help)
        <div class="admin-help">{{ $help }}</div>
    @endif

    @error($name)
        <div class="admin-error-text">{{ $message }}</div>
    @enderror
</div>
