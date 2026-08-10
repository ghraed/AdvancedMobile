@props([
    'name' => 'search',
    'label' => 'Search',
    'value' => '',
    'placeholder' => 'Search',
])

<div class="admin-field">
    <label class="admin-label" for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}" class="admin-input">
</div>
