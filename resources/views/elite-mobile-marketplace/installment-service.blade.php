@extends('layouts.elite-mobile-marketplace')

@section('title', 'Elite Mobile Marketplace - Installment Service')

@section('content')
    <header class="pm-topbar">
        <div class="flex items-center gap-3">
            <a href="{{ route('elite-mobile-marketplace.home') }}" class="material-symbols-outlined text-[var(--pm-primary)]">arrow_back</a>
            <span class="font-semibold text-[var(--pm-primary)]">PhoneMart</span>
        </div>
        <span class="material-symbols-outlined text-[var(--pm-primary)]">shopping_cart</span>
    </header>

    <main class="px-4 pb-28 pt-4 lg:px-8 lg:pb-12 lg:pt-8">
        <section class="overflow-hidden rounded-[28px] shadow-[0_18px_50px_rgba(12,33,58,0.18)]">
            <div class="relative h-[300px] overflow-hidden lg:h-[420px]">
                <img src="{{ $hero['image'] }}" alt="{{ $hero['title'] }}" class="h-full w-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-[rgba(12,33,58,0.82)] via-[rgba(12,33,58,0.35)] to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0 p-5 text-white lg:p-8">
                    <h1 class="text-[2rem] font-extrabold leading-tight lg:max-w-[44rem] lg:text-[3.6rem]">{{ $hero['title'] }}</h1>
                    <p class="mt-2 text-sm leading-6 text-blue-100 lg:max-w-[40rem] lg:text-lg lg:leading-8">{{ $hero['description'] }}</p>
                </div>
            </div>
        </section>

        <section class="mt-5 flex flex-wrap justify-center gap-2">
            @foreach ($trustBadges as $badge)
                <div class="pm-pill">
                    <span class="material-symbols-outlined text-[var(--pm-secondary)]" style="font-variation-settings: 'FILL' 1;">{{ $badge['icon'] }}</span>
                    <span>{{ $badge['label'] }}</span>
                </div>
            @endforeach
        </section>

        <section class="mt-6 grid gap-3 lg:grid-cols-3">
            @foreach ($benefits as $benefit)
                <article class="{{ $benefit['featured'] ? 'lg:col-span-3 bg-[var(--pm-primary)] text-white' : 'pm-card' }} rounded-[26px] p-5 shadow-sm">
                    <span class="material-symbols-outlined text-3xl {{ $benefit['featured'] ? 'text-cyan-200' : 'text-[var(--pm-secondary)]' }}">{{ $benefit['icon'] }}</span>
                    <h2 class="mt-4 text-xl font-bold {{ $benefit['featured'] ? 'text-white' : 'text-[var(--pm-text)]' }}">{{ $benefit['title'] }}</h2>
                    <p class="mt-3 text-sm leading-6 {{ $benefit['featured'] ? 'text-blue-100' : 'text-[var(--pm-text-muted)]' }}">{{ $benefit['description'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="mt-7">
            <h2 class="text-center text-2xl font-bold text-[var(--pm-text)]">How it Works</h2>
            <div class="mt-6 space-y-4 lg:grid lg:grid-cols-3 lg:gap-5 lg:space-y-0">
                @foreach ($steps as $step)
                    <article class="relative rounded-[24px] border border-[var(--pm-border)] bg-white px-4 py-5 shadow-[0_8px_24px_rgba(12,33,58,0.06)]">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[var(--pm-secondary)] text-lg font-bold text-white">
                                {{ $loop->iteration }}
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-[var(--pm-text)]">{{ $step['title'] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-[var(--pm-text-muted)]">{{ $step['description'] }}</p>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="relative mt-7 overflow-hidden rounded-[28px] bg-[var(--pm-secondary)] px-5 py-6 text-white lg:px-8 lg:py-8">
            <div class="relative z-10 max-w-[240px] lg:max-w-[32rem]">
                <h2 class="text-2xl font-bold">Ready to upgrade?</h2>
                <p class="mt-3 text-sm leading-6 text-blue-50/90">
                    Find out your eligibility and spending limit in seconds. No impact on your credit score for checking.
                </p>
                <button class="pm-button mt-5 bg-white text-[var(--pm-primary)]">Check Eligibility</button>
            </div>
            <div class="absolute -right-10 -top-8 h-36 w-36 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -bottom-10 left-5 h-28 w-28 rounded-full bg-[rgba(12,33,58,0.18)] blur-2xl"></div>
        </section>

        <section class="mt-7">
            <h2 class="text-2xl font-bold text-[var(--pm-text)]">Common Questions</h2>
            <div class="mt-4 space-y-3 lg:grid lg:grid-cols-2 lg:gap-4 lg:space-y-0">
                @foreach ($faqs as $faq)
                    <details class="group rounded-[22px] border border-[var(--pm-border)] bg-white p-4 shadow-[0_8px_24px_rgba(12,33,58,0.05)]">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                            <span class="text-sm font-semibold leading-6 text-[var(--pm-text)]">{{ $faq['question'] }}</span>
                            <span class="material-symbols-outlined text-[var(--pm-text-muted)] transition group-open:rotate-180">expand_more</span>
                        </summary>
                        <p class="mt-3 border-t border-[var(--pm-border)] pt-3 text-sm leading-6 text-[var(--pm-text-muted)]">
                            {{ $faq['answer'] }}
                        </p>
                    </details>
                @endforeach
            </div>
        </section>
    </main>
@endsection
