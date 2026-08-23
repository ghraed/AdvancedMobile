@extends('layouts.elite-mobile-marketplace')

@section('content')
<x-public.public-header />
<main class="mx-auto max-w-6xl px-4 py-8">
    <a class="text-sm font-semibold text-blue-700" href="{{ route('account.installments.index') }}">← All installment accounts</a>
    <div class="mt-4 flex flex-wrap items-start justify-between gap-4"><div><h1 class="text-2xl font-bold">{{ $account->application->product_name_snapshot }}</h1><p class="text-sm text-slate-500">{{ $account->account_number }} · Application {{ $account->application->application_number }}</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold uppercase">{{ $account->account_status }}</span></div>

    <section class="pm-card mt-6">
        <h2 class="text-lg font-bold">Summary</h2>
        <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
            <div><dt class="text-slate-500">Variant</dt><dd>{{ collect([$account->application->storage_snapshot, $account->application->color_snapshot])->filter()->join(' / ') ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Purchase price</dt><dd>{{ number_format($account->original_principal_cents / 100, 2) }} {{ $account->currency }}</dd></div>
            <div><dt class="text-slate-500">Financing fee</dt><dd>{{ number_format($account->financing_fee_cents / 100, 2) }} {{ $account->currency }}</dd></div>
            <div><dt class="text-slate-500">Total payable</dt><dd>{{ number_format($account->total_payable_cents / 100, 2) }} {{ $account->currency }}</dd></div>
            <div><dt class="text-slate-500">Payments</dt><dd>{{ $account->payment_count }}</dd></div>
            <div><dt class="text-slate-500">Amount paid</dt><dd>{{ number_format($account->amount_paid_cents / 100, 2) }} {{ $account->currency }}</dd></div>
            <div><dt class="text-slate-500">Remaining</dt><dd>{{ number_format($account->remaining_balance_cents / 100, 2) }} {{ $account->currency }}</dd></div>
            <div><dt class="text-slate-500">Overdue</dt><dd>{{ number_format($account->overdue_amount_cents / 100, 2) }} {{ $account->currency }}</dd></div>
            <div><dt class="text-slate-500">Next payment</dt><dd>@if($account->next_schedule_item){{ number_format($account->next_schedule_item->remaining_cents / 100, 2) }} {{ $account->currency }}@else—@endif</dd></div>
            <div><dt class="text-slate-500">Next due date</dt><dd>{{ $account->next_schedule_item?->due_date?->format('M j, Y') ?? '—' }}</dd></div>
        </dl>
    </section>

    <section class="pm-card mt-6 overflow-x-auto"><h2 class="text-lg font-bold">Payment schedule</h2><table class="mt-4 w-full min-w-[650px] text-left text-sm"><thead><tr class="border-b text-slate-500"><th class="p-3">#</th><th>Due date</th><th>Amount</th><th>Paid</th><th>Remaining</th><th>Status</th></tr></thead><tbody>@foreach($account->scheduleItems as $item)<tr class="border-b"><td class="p-3">{{ $item->installment_number }}</td><td>{{ $item->due_date->format('M j, Y') }}</td><td>{{ number_format($item->amount_due_cents/100, 2) }}</td><td>{{ number_format($item->amount_paid_cents/100, 2) }}</td><td>{{ number_format($item->remaining_cents/100, 2) }}</td><td><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold uppercase">{{ $item->effective_status }}</span></td></tr>@endforeach</tbody></table></section>

    <section class="pm-card mt-6 overflow-x-auto"><h2 class="text-lg font-bold">Payment history</h2><table class="mt-4 w-full min-w-[650px] text-left text-sm"><thead><tr class="border-b text-slate-500"><th class="p-3">Date</th><th>Receipt</th><th>Amount</th><th>Method</th><th>Reference</th><th></th></tr></thead><tbody>@forelse($account->payments as $payment)<tr class="border-b {{ $payment->is_reversed ? 'opacity-60' : '' }}"><td class="p-3">{{ $payment->paid_at->format('M j, Y H:i') }}</td><td>{{ $payment->receipt_number }} @if($payment->is_reversed)<strong class="text-red-700">(Reversed)</strong>@endif</td><td>{{ number_format($payment->amount_cents/100, 2) }} {{ $account->currency }}</td><td>{{ str($payment->payment_method)->replace('_',' ')->headline() }}</td><td>{{ $payment->reference ?: '—' }}</td><td><a class="font-semibold text-blue-700" href="{{ route('account.installments.payments.receipt', [$account, $payment]) }}">Receipt</a></td></tr>@empty<tr><td class="p-3" colspan="6">No payments recorded.</td></tr>@endforelse</tbody></table></section>

    <section class="pm-card mt-6"><h2 class="text-lg font-bold">Application timeline</h2><div class="mt-4 grid gap-3">@foreach($account->application->statusHistory->sortBy('created_at') as $history)<div class="border-l-2 pl-4"><strong>{{ str($history->to_status)->replace('_',' ')->headline() }}</strong><div class="text-sm text-slate-500">{{ $history->created_at->format('M j, Y H:i') }} @if($history->note)· {{ $history->note }}@endif</div></div>@endforeach</div></section>
</main>
@endsection
