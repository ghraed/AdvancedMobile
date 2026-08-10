@props(['categories'])
<aside class="pm-sidebar" aria-label="Product categories">
    <h2 class="pm-sidebar-title">Shop by category</h2>
    <nav class="grid gap-1">
        @forelse($categories as $category)
            <a href="{{ route('categories.show', $category) }}" class="pm-sidebar-link {{ request()->route('category')?->is($category) ? 'is-active' : '' }}">
                <span class="flex min-w-0 items-center gap-3">
                    <span class="material-symbols-outlined text-[20px]">{{ $category->icon ?: 'category' }}</span>
                    <span class="truncate">{{ $category->name }}</span>
                </span>
                <span class="material-symbols-outlined text-base">chevron_right</span>
            </a>
        @empty
            <p class="px-3 py-4 text-sm text-[var(--pm-text-muted)]">Categories will appear here.</p>
        @endforelse
    </nav>
    <div class="pm-support-card">
        <strong class="relative z-10">Flexible payments</strong>
        <p class="relative z-10 mt-3 text-sm leading-6 text-slate-300">Choose a clear installment schedule before checkout.</p>
        <a href="{{ route('installments.landing') }}" class="relative z-10 mt-3 inline-flex rounded-xl bg-white px-3 py-2 text-sm font-extrabold text-blue-900">Installment Service / خدمة التقسيط</a>
    </div>
</aside>
