@props([
    'paginator',
])

@if ($paginator->hasPages())
    <div class="admin-pagination">
        {{ $paginator->links() }}
    </div>
@endif
