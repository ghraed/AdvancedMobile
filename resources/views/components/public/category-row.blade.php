@props(['category', 'hasChildren' => false])
<a href="{{ $hasChildren ? '#category-'.$category->id : route('categories.show', $category) }}" data-category-trigger="{{ $hasChildren ? $category->id : '' }}" class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left hover:bg-slate-50 focus:bg-slate-50">
    <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-600">
        @if ($category->image)
            <img src="{{ asset('storage/'.$category->image) }}" alt="" class="h-full w-full object-cover">
        @elseif ($category->icon)
            <span class="material-symbols-outlined">{{ $category->icon }}</span>
        @else
            <span class="material-symbols-outlined">category</span>
        @endif
    </span>
    <span class="min-w-0 flex-1 truncate font-medium text-slate-900">{{ $category->name }}</span>
    @if ($hasChildren)<span class="material-symbols-outlined text-slate-400">chevron_right</span>@endif
</a>
