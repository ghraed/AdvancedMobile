@props(['categories'])
<div data-category-drawer class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <button type="button" data-category-overlay class="absolute inset-0 cursor-default bg-slate-950/45" aria-label="Close category menu"></button>
    <aside data-category-dialog class="relative flex h-full w-full max-w-sm flex-col bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="Categories" tabindex="-1">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-bold text-slate-900">Categories</h2>
            <button type="button" data-category-close class="rounded-lg p-2 hover:bg-slate-100" aria-label="Close category menu"><span class="material-symbols-outlined">close</span></button>
        </div>
        <nav class="flex-1 overflow-y-auto p-3">
            <a href="{{ route('installments.landing') }}" class="mb-3 flex items-center justify-between rounded-xl bg-blue-50 px-4 py-3 font-bold text-blue-800">
                <span>Installment Service / خدمة التقسيط</span>
                <span class="material-symbols-outlined">payments</span>
            </a>
            <div data-category-root>
                @forelse ($categories as $category)
                    <x-public.category-row :category="$category" :has-children="$category->childrenRecursive->isNotEmpty()" />
                @empty
                    <p class="p-3 text-sm text-slate-500">No categories are available yet.</p>
                @endforelse
            </div>
            @foreach ($categories as $category)
                @if ($category->childrenRecursive->isNotEmpty())
                    <x-public.subcategory-list :categories="$category->childrenRecursive" :panel-id="$category->id" />
                @endif
            @endforeach
        </nav>
    </aside>
</div>
