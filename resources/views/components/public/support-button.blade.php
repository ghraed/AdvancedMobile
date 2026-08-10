@props(['href' => null, 'label' => 'Support'])
@if ($href)
    <a href="{{ $href }}" class="fixed bottom-5 right-5 z-10 inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg hover:bg-slate-700"><span class="material-symbols-outlined">chat</span>{{ $label }}</a>
@endif
