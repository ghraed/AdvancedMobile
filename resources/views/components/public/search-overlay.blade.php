<div data-search-overlay class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <button type="button" data-search-close class="absolute inset-0 cursor-default bg-slate-950/45" aria-label="Close search"></button>
    <section class="relative mx-auto mt-5 w-[calc(100%-2rem)] max-w-2xl rounded-2xl bg-white p-4 shadow-2xl" role="dialog" aria-modal="true" aria-label="Search">
        <form action="{{ route('search') }}" class="flex items-center gap-2">
            <label for="storefront-search" class="sr-only">Search products</label>
            <span class="material-symbols-outlined text-slate-500">search</span>
            <input id="storefront-search" name="q" value="{{ request('q') }}" data-search-input class="min-w-0 flex-1 border-0 p-2 text-base outline-none ring-0" placeholder="Search the catalog" autocomplete="off">
            <button type="button" data-search-close class="rounded-lg p-2 hover:bg-slate-100" aria-label="Close search"><span class="material-symbols-outlined">close</span></button>
        </form>
    </section>
</div>
