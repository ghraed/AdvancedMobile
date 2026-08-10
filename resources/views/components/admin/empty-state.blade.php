@props([
    'title' => 'Nothing found',
    'message' => 'There is no data to show yet.',
])

<div {{ $attributes->class('admin-empty-state') }}>
    <h3>{{ $title }}</h3>
    <p>{{ $message }}</p>
    @if (trim($slot) !== '')
        <div class="admin-actions">{{ $slot }}</div>
    @endif
</div>
