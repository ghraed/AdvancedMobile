<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstallmentAccount;
use App\Models\InstallmentPayment;
use App\Services\InstallmentAccountService;
use App\Services\InstallmentPaymentService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstallmentAccountController extends Controller
{
    public function index(Request $request)
    {
        $query = InstallmentAccount::query()->with(['user', 'application', 'scheduleItems']);

        if ($request->filled('customer')) {
            $term = $request->string('customer');
            $query->where(function (Builder $q) use ($term) {
                $q->whereHas('user', fn (Builder $users) => $users->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'))
                    ->orWhereHas('application', fn (Builder $apps) => $apps->where('phone', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'));
            });
        }
        if ($request->filled('application_number')) {
            $query->whereHas('application', fn (Builder $apps) => $apps->where('application_number', 'like', '%'.$request->application_number.'%'));
        }
        if ($request->filled('account_number')) {
            $query->where('account_number', 'like', '%'.$request->account_number.'%');
        }
        if ($request->filled('status')) {
            $query->where('account_status', $request->status);
        }
        if ($request->boolean('overdue')) {
            $query->overdue();
        }
        if ($request->filled('product')) {
            $query->whereHas('application', fn (Builder $apps) => $apps->where('product_name_snapshot', 'like', '%'.$request->product.'%'));
        }
        if ($request->filled('due_from')) {
            $query->whereHas('scheduleItems', fn (Builder $items) => $items->whereDate('due_date', '>=', $request->due_from));
        }
        if ($request->filled('due_to')) {
            $query->whereHas('scheduleItems', fn (Builder $items) => $items->whereDate('due_date', '<=', $request->due_to));
        }
        if ($request->filled('from')) {
            $query->whereDate('activated_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('activated_at', '<=', $request->to);
        }

        $accounts = $query->latest('activated_at')->paginate(20)->withQueryString();
        $metrics = $this->metrics();

        return view('admin.installments.index', compact('accounts', 'metrics'));
    }

    public function show(InstallmentAccount $installment)
    {
        $installment->load([
            'user', 'application.documents', 'application.statusHistory.performer', 'scheduleItems',
            'payments.recorder', 'payments.reverser', 'notes.author', 'events.performer',
        ]);

        return view('admin.installments.show', ['account' => $installment]);
    }

    public function payment(Request $request, InstallmentAccount $installment, InstallmentPaymentService $service)
    {
        $data = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'payment_method' => ['required', 'in:cash,card,bank_transfer,other'],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        try {
            $payment = $service->recordPayment($installment, $data + ['amount_cents' => $this->toCents($data['amount'])], $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.installments.show', $installment)->with('status', 'Payment '.$payment->receipt_number.' recorded.');
    }

    public function reverse(Request $request, InstallmentAccount $installment, InstallmentPayment $payment, InstallmentPaymentService $service)
    {
        abort_unless($payment->installment_account_id === $installment->id, 404);
        $data = $request->validate(['reversal_reason' => ['required', 'string', 'max:5000']]);

        try {
            $service->reversePayment($payment, $request->user(), $data['reversal_reason']);
        } catch (DomainException $exception) {
            return back()->withErrors(['reversal_reason' => $exception->getMessage()]);
        }

        return back()->with('status', 'Payment reversed.');
    }

    public function note(Request $request, InstallmentAccount $installment)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:10000']]);
        $installment->notes()->create(['user_id' => $request->user()->id, 'note' => $data['note']]);

        return back()->with('status', 'Internal note added.');
    }

    public function cancel(Request $request, InstallmentAccount $installment, InstallmentAccountService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:5000']]);
        try {
            $service->cancel($installment, $request->user(), $data['reason']);
        } catch (DomainException $exception) {
            return back()->withErrors(['reason' => $exception->getMessage()]);
        }

        return back()->with('status', 'Installment account cancelled.');
    }

    public function receipt(InstallmentAccount $installment, InstallmentPayment $payment)
    {
        abort_unless($payment->installment_account_id === $installment->id, 404);
        $payment->load('account.application');

        return view('installment-receipts.show', compact('payment'));
    }

    private function metrics(): array
    {
        $active = InstallmentAccount::where('account_status', 'active');
        $payments = InstallmentPayment::whereNull('reversed_at');

        return [
            'active_accounts' => (clone $active)->count(),
            'total_outstanding_cents' => (int) (clone $active)->selectRaw('COALESCE(SUM(total_payable_cents - amount_paid_cents), 0) total')->value('total'),
            'overdue_accounts' => InstallmentAccount::overdue()->count(),
            'overdue_amount_cents' => (int) DB::table('installment_schedule_items')->join('installment_accounts', 'installment_accounts.id', '=', 'installment_schedule_items.installment_account_id')->where('installment_accounts.account_status', 'active')->whereDate('due_date', '<', today())->whereColumn('installment_schedule_items.amount_paid_cents', '<', 'installment_schedule_items.amount_due_cents')->sum(DB::raw('installment_schedule_items.amount_due_cents - installment_schedule_items.amount_paid_cents')),
            'payments_today_cents' => (int) (clone $payments)->whereDate('paid_at', today())->sum('amount_cents'),
            'payments_month_cents' => (int) (clone $payments)->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount_cents'),
            'due_next_7_days_cents' => (int) DB::table('installment_schedule_items')->join('installment_accounts', 'installment_accounts.id', '=', 'installment_schedule_items.installment_account_id')->where('installment_accounts.account_status', 'active')->whereBetween('due_date', [today(), today()->addDays(7)])->whereColumn('installment_schedule_items.amount_paid_cents', '<', 'installment_schedule_items.amount_due_cents')->sum(DB::raw('installment_schedule_items.amount_due_cents - installment_schedule_items.amount_paid_cents')),
        ];
    }

    private function toCents(string $amount): int
    {
        [$whole, $decimal] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
    }
}
