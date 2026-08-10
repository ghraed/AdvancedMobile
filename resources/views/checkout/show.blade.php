@extends('layouts.elite-mobile-marketplace')

@section('title', 'Checkout')

@section('content')
<x-public.public-header />
<x-public.search-overlay />
<main class="mx-auto max-w-2xl px-4 py-10 sm:py-14"><section class="pm-card rounded-[26px] p-6 sm:p-8"><p class="text-xs font-extrabold uppercase tracking-[.14em] text-[var(--pm-primary)]">Secure checkout</p><h1 class="mt-2 text-3xl font-black tracking-[-.04em]">Review your plan</h1>
@if(session('error'))<p class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-900">{{ session('error') }}</p>@endif
@if($changed)<p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Your price or payment schedule changed while you signed in. The updated calendar below is now in effect.</p>@endif
@php($image = app(\App\Services\ProductImageResolver::class)->resolve($product, $variant)->first())
<div class="mt-5 flex gap-4">@if($image)<img class="h-24 w-24 rounded-xl object-cover" src="{{ asset('storage/'.$image->image_path) }}" alt="{{ $image->alt_text ?: $product->name }}">@endif<div><p class="font-bold">{{ $product->name }}</p><p class="mt-1 text-sm text-[var(--pm-text-muted)]">SKU: {{ $variant->sku }} · Quantity: 1</p><p class="text-sm text-[var(--pm-text-muted)]">Storage: {{ $variant->storage_option?->display_name ?: '—' }} · Color: {{ $variant->color_option?->display_name ?: '—' }}</p></div></div>
<p class="mt-4 text-sm text-[var(--pm-text-muted)]">{{ $plan->number_of_payments }} payments · {{ ucfirst($plan->interval_type) }}</p>
<dl class="mt-5 space-y-2 text-sm"><div class="flex justify-between"><dt>Variant price</dt><dd>{{ number_format($preview['price'], 2) }}</dd></div><div class="flex justify-between"><dt>Financing fee</dt><dd>{{ number_format($preview['financing_fee'], 2) }}</dd></div><div class="flex justify-between"><dt>Total financed amount</dt><dd>{{ number_format($preview['total_financed_amount'], 2) }}</dd></div><div class="flex justify-between"><dt>Amount due today</dt><dd class="font-bold">{{ number_format($preview['amount_due_now'], 2) }}</dd></div><div class="flex justify-between"><dt>Future payments</dt><dd>{{ $preview['future_payment_count'] }}</dd></div><div class="flex justify-between"><dt>Total</dt><dd class="font-bold">{{ number_format($preview['total_amount'], 2) }}</dd></div></dl>
<ol class="mt-5 space-y-2">@foreach($preview['future_installments'] as $payment)<li class="rounded-xl border p-3 text-sm">Payment {{ $payment['sequence'] + 1 }} · {{ $payment['due_date'] }} · {{ number_format($payment['amount'], 2) }}</li>@endforeach</ol>
<div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm"><p class="font-bold">Customer information</p><p>{{ auth()->user()->name }}</p><p>{{ auth()->user()->email }}</p></div>
<p class="mt-4 text-sm text-[var(--pm-text-muted)]">Delivery and pickup are not configured for this store. Your order will be confirmed after you submit it.</p>
<form method="POST" action="{{ route('checkout.store') }}" class="mt-6" data-order-form>@csrf<button class="pm-button pm-button--accent w-full justify-center disabled:cursor-not-allowed disabled:opacity-60" type="submit">Confirm order</button></form>
</section></main>
@endsection

@push('scripts')
<script>document.querySelector('[data-order-form]')?.addEventListener('submit', event => { const button=event.currentTarget.querySelector('button[type="submit"]'); if(button.disabled){event.preventDefault();return;} button.disabled=true;button.textContent='Creating order…'; });</script>
@endpush
