@props(['categories', 'panelId'])
<div id="category-{{ $panelId }}" data-category-panel="{{ $panelId }}" class="hidden" aria-hidden="true">
    <div class="mb-3 flex items-center gap-2 border-b border-slate-200 pb-3">
        <button type="button" data-category-back class="rounded-lg p-2 hover:bg-slate-100" aria-label="Back to categories"><span class="material-symbols-outlined">arrow_back</span></button>
        <p class="font-semibold text-slate-900">Categories</p>
    </div>
    @foreach ($categories as $category)
        <x-public.category-row :category="$category" :has-children="$category->childrenRecursive->isNotEmpty()" />
    @endforeach
</div>
@foreach ($categories as $category)
    @if ($category->childrenRecursive->isNotEmpty())
        <x-public.subcategory-list :categories="$category->childrenRecursive" :panel-id="$category->id" />
    @endif
@endforeach
