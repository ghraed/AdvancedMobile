@props([
    'id',
    'title' => 'Confirm action',
    'message' => 'Please confirm this action.',
])

<div id="{{ $id }}" class="admin-modal-backdrop" aria-hidden="true">
    <div class="admin-modal">
        <h3>{{ $title }}</h3>
        <p>{{ $message }}</p>
        <div class="admin-actions">
            {{ $slot }}
            <button type="button" class="admin-button admin-button--ghost" data-modal-close>Cancel</button>
        </div>
    </div>
</div>
