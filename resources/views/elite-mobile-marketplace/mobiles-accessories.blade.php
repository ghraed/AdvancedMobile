@extends('layouts.elite-mobile-marketplace')

@section('title', 'Elite Mobile Marketplace - Mobiles')

@section('content')
    <header class="pm-topbar">
        <div class="flex items-center gap-3">
            <a href="{{ route('elite-mobile-marketplace.home') }}" class="material-symbols-outlined text-[var(--pm-primary)]">menu</a>
            <span class="text-lg font-semibold text-[var(--pm-text)]">PhoneMart</span>
        </div>
        <span class="material-symbols-outlined text-[var(--pm-primary)]">shopping_cart</span>
    </header>

    <main class="px-4 pb-28 pt-4 lg:px-8 lg:pb-12 lg:pt-8">
        <section>
            <div class="mb-4 flex items-center justify-between gap-3">
                <h1 class="text-[1.9rem] font-bold leading-none text-[var(--pm-text)]">{{ $pageTitle }}</h1>
                <button class="pm-filter-button">
                    <span class="material-symbols-outlined text-[18px]">tune</span>
                    Filter
                </button>
            </div>

            <div class="rounded-full border border-[var(--pm-border)] bg-white px-4 py-3 shadow-[0_10px_24px_rgba(12,33,58,0.05)]">
                <input
                    type="text"
                    class="w-full bg-transparent text-sm text-[var(--pm-text)] outline-none placeholder:text-slate-400"
                    placeholder="Search smartphones..."
                >
            </div>

            <div class="hide-scrollbar mt-4 flex gap-2 overflow-x-auto pb-1 lg:flex-wrap lg:overflow-visible">
                @foreach ($filters as $filter)
                    <button class="rounded-full px-4 py-2 text-xs font-semibold {{ $loop->first ? 'bg-[var(--pm-primary)] text-white' : 'bg-[var(--pm-surface-muted)] text-[var(--pm-text-muted)]' }}">
                        {{ $filter }}
                    </button>
                @endforeach
            </div>
        </section>

        <section class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-5">
            @foreach ($products as $product)
                <article class="pm-card overflow-hidden p-3">
                    <div class="relative mb-3 aspect-square overflow-hidden rounded-[20px] bg-[var(--pm-surface-soft)]">
                        <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" class="h-full w-full object-contain p-4 transition duration-300 hover:scale-105">
                        @if ($product['badge'])
                            <span class="pm-badge {{ $product['badge_style'] === 'danger' ? 'pm-badge--danger' : 'pm-badge--secondary' }}">
                                {{ $product['badge'] }}
                            </span>
                        @endif
                    </div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $product['brand'] }}</p>
                    <h2 class="pm-clamp-2 mt-1 text-sm font-semibold leading-5 text-[var(--pm-text)]">{{ $product['name'] }}</h2>
                    <div class="mt-3">
                        <p class="text-lg font-extrabold text-[var(--pm-primary)]">{{ $product['price'] }}</p>
                        <div class="mt-1 flex items-center gap-1.5 text-[11px] font-medium text-[var(--pm-secondary)]">
                            <span class="material-symbols-outlined text-[14px]">payments</span>
                            <span>{{ $product['installment'] }}</span>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>
    </main>
@endsection
