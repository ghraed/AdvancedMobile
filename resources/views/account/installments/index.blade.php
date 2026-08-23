@extends('layouts.elite-mobile-marketplace')

@section('content')
<x-public.public-header />
<main class="mx-auto max-w-5xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div><h1 class="text-2xl font-bold">My installment accounts</h1><p class="text-sm text-slate-500">Balances, schedules, and payment receipts.</p></div>
        <a class="rounded-xl border px-4 py-2 text-sm font-semibold" href="{{ route('account.installment-applications.index') }}">Applications</a>
    </div>
    <div class="grid gap-4">
        @forelse ($accounts as $account)
            <a class="pm-card block" href="{{ route('account.installments.show', $account) }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><strong>{{ $account->application->product_name_snapshot }}</strong><div class="text-sm text-slate-500">{{ $account->account_number }} · {{ $account->application->application_number }}</div></div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold uppercase">{{ $account->account_status }}</span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <div><span class="block text-slate-500">Total</span>{{ number_format($account->total_payable_cents / 100, 2) }} {{ $account->currency }}</div>
                    <div><span class="block text-slate-500">Paid</span>{{ number_format($account->amount_paid_cents / 100, 2) }} {{ $account->currency }}</div>
                    <div><span class="block text-slate-500">Remaining</span>{{ number_format($account->remaining_balance_cents / 100, 2) }} {{ $account->currency }}</div>
                    <div><span class="block text-slate-500">Overdue</span>{{ number_format($account->overdue_amount_cents / 100, 2) }} {{ $account->currency }}</div>
                </div>
            </a>
        @empty
            <section class="pm-card"><h2 class="font-bold">No installment accounts yet</h2><p class="mt-1 text-sm text-slate-500">An account appears here after an approved application is activated.</p></section>
        @endforelse
    </div>
    <div class="mt-6">{{ $accounts->links() }}</div>
</main>
@endsection
