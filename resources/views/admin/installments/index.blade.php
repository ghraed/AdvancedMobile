@extends('admin.layouts.app')
@section('heading', 'Installment accounts')
@section('page_description', 'Outstanding balances, overdue accounts, collections, and upcoming dues.')
@section('content')
<div class="admin-grid admin-grid-4">
    @foreach(['active_accounts'=>'Active accounts','total_outstanding_cents'=>'Outstanding','overdue_accounts'=>'Overdue accounts','overdue_amount_cents'=>'Overdue amount','payments_today_cents'=>'Collected today','payments_month_cents'=>'Collected this month','due_next_7_days_cents'=>'Due next 7 days'] as $key=>$label)
        <section class="admin-card admin-card--tight admin-kpi"><span class="admin-kpi__label">{{ $label }}</span><strong class="admin-kpi__value">{{ str_ends_with($key, '_cents') ? number_format($metrics[$key]/100, 2) : number_format($metrics[$key]) }}</strong></section>
    @endforeach
</div>
<section class="admin-card" style="margin-top:20px">
    <form method="GET" class="admin-filter-bar">
        <input class="admin-input" name="customer" value="{{ request('customer') }}" placeholder="Customer, phone, or email">
        <input class="admin-input" name="application_number" value="{{ request('application_number') }}" placeholder="Application number">
        <input class="admin-input" name="account_number" value="{{ request('account_number') }}" placeholder="Account number">
        <input class="admin-input" name="product" value="{{ request('product') }}" placeholder="Product">
        <select class="admin-select" name="status"><option value="">All statuses</option>@foreach(\App\Models\InstallmentAccount::STATUSES as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <label class="admin-field"><span class="admin-label">Due from</span><input class="admin-input" type="date" name="due_from" value="{{ request('due_from') }}"></label>
        <label class="admin-field"><span class="admin-label">Due to</span><input class="admin-input" type="date" name="due_to" value="{{ request('due_to') }}"></label>
        <label class="admin-inline"><input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))> Overdue only</label>
        <button class="admin-button">Filter</button><a class="admin-link-button" href="{{ route('admin.installments.index') }}">Reset</a>
    </form>
</section>
<section class="admin-card" style="margin-top:20px"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Account</th><th>Customer</th><th>Product</th><th>Status</th><th>Paid</th><th>Remaining</th><th>Overdue</th><th></th></tr></thead><tbody>@forelse($accounts as $account)<tr><td><strong>{{ $account->account_number }}</strong><br><small>{{ $account->application->application_number }}</small></td><td>{{ $account->user->name }}<br><small>{{ $account->application->phone }}</small></td><td>{{ $account->application->product_name_snapshot }}</td><td><span class="admin-status-badge admin-status-badge--{{ $account->account_status==='active'?'success':'neutral' }}">{{ $account->account_status }}</span></td><td>{{ number_format($account->amount_paid_cents/100,2) }}</td><td>{{ number_format($account->remaining_balance_cents/100,2) }}</td><td>{{ number_format($account->overdue_amount_cents/100,2) }}</td><td><a class="admin-link-button" href="{{ route('admin.installments.show',$account) }}">View</a></td></tr>@empty<tr><td colspan="8">No accounts match these filters.</td></tr>@endforelse</tbody></table></div><div class="admin-pagination">{{ $accounts->links() }}</div></section>
@endsection
