@extends('layouts.elite-mobile-marketplace')

@section('title', 'Order confirmed')

@section('content')
<x-public.public-header />
<x-public.search-overlay />
<main class="mx-auto max-w-2xl px-4 py-10 sm:py-14"><section class="pm-card rounded-[26px] p-6 sm:p-8"><div class="mb-5 grid h-14 w-14 place-items-center rounded-2xl bg-green-100 text-green-700"><span class="material-symbols-outlined text-3xl">check_circle</span></div><p class="text-xs font-extrabold uppercase tracking-[.14em] text-green-700">Purchase complete</p><h1 class="mt-2 text-3xl font-black tracking-[-.04em]">Order confirmed</h1>
<p class="mt-3 text-sm text-[var(--pm-text-muted)]">Reference: <span class="font-bold text-[var(--pm-text)]">{{ $order->reference }}</span></p>
<p class="mt-5 font-bold">{{ $order->product_name }}</p><p class="text-sm text-[var(--pm-text-muted)]">{{ $order->sku }} · {{ $order->storage ?: '—' }} · {{ $order->color ?: '—' }}</p>
<dl class="mt-5 space-y-2 text-sm"><div class="flex justify-between"><dt>Amount due today</dt><dd class="font-bold">{{ number_format($order->amount_due_today, 2) }}</dd></div><div class="flex justify-between"><dt>Order status</dt><dd class="font-bold capitalize">{{ $order->status }}</dd></div></dl>
<h2 class="mt-6 font-bold">Future payment schedule</h2><ol class="mt-3 space-y-2">@foreach($order->installments->where('sequence', '>', 1) as $payment)<li class="rounded-xl border p-3 text-sm">Payment {{ $payment->sequence }} · {{ $payment->due_date->toDateString() }} · {{ number_format($payment->amount, 2) }}</li>@endforeach</ol>
<p class="mt-6 text-sm text-[var(--pm-text-muted)]">Next action: follow the payment instructions sent to {{ $order->customer_snapshot['email'] }}.</p>
<a class="mt-5 inline-block font-bold text-[var(--pm-primary)]" href="{{ route('orders.confirmation', $order) }}">View order</a>
</section></main>
@endsection
