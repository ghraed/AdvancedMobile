<?php

namespace App\Http\Controllers;

use App\Models\InstallmentAccount;
use App\Models\InstallmentPayment;

class InstallmentAccountController extends Controller
{
    public function index()
    {
        $accounts = auth()->user()->installmentAccounts()
            ->with(['application', 'scheduleItems'])
            ->latest('activated_at')
            ->paginate(12);

        return view('account.installments.index', compact('accounts'));
    }

    public function show(InstallmentAccount $account)
    {
        $this->authorize('view', $account);
        $account->load(['application.statusHistory.performer', 'application.documents', 'scheduleItems', 'payments.recorder']);

        return view('account.installments.show', compact('account'));
    }

    public function receipt(InstallmentAccount $account, InstallmentPayment $payment)
    {
        $this->authorize('view', $account);
        abort_unless($payment->installment_account_id === $account->id, 404);
        $this->authorize('view', $payment);
        $payment->load('account.application');

        return view('installment-receipts.show', compact('payment'));
    }
}
