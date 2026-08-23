<?php

namespace App\Services;

use App\Models\InstallmentAccount;
use App\Models\InstallmentApplication;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class InstallmentAccountService
{
    public function __construct(private readonly InstallmentCalculatorService $calculator) {}

    public function activate(InstallmentApplication $application, string|CarbonImmutable $firstDueDate, User $admin): InstallmentAccount
    {
        $firstDue = $firstDueDate instanceof CarbonImmutable
            ? $firstDueDate->startOfDay()
            : CarbonImmutable::parse($firstDueDate)->startOfDay();

        try {
            return DB::transaction(function () use ($application, $firstDue, $admin) {
                $application = InstallmentApplication::query()->lockForUpdate()->findOrFail($application->id);

                if ($application->status !== 'approved') {
                    throw new DomainException('Only approved applications can start an installment account.');
                }
                if (! $application->user_id) {
                    throw new DomainException('The application must belong to a customer account before activation.');
                }
                if ($application->installmentAccount()->exists()) {
                    throw new DomainException('This application already has an installment account.');
                }

                $principal = $this->toCents((string) $application->product_price);
                $total = $this->toCents((string) $application->total_payable);
                $amounts = $this->calculator->installmentAmountsInCents($total, (int) $application->installment_months);

                $account = InstallmentAccount::create([
                    'account_number' => $this->nextAccountNumber(),
                    'installment_application_id' => $application->id,
                    'user_id' => $application->user_id,
                    'original_principal_cents' => $principal,
                    'financing_fee_cents' => max(0, $total - $principal),
                    'total_payable_cents' => $total,
                    'payment_count' => count($amounts),
                    'first_due_date' => $firstDue,
                    'account_status' => InstallmentAccount::STATUS_ACTIVE,
                    'amount_paid_cents' => 0,
                    'currency' => $application->currency,
                    'activated_at' => now(),
                    'created_by' => $admin->id,
                ]);

                foreach ($amounts as $index => $amount) {
                    // Every date is calculated from the original anchor. Thus a
                    // Jan 31 schedule becomes Feb 28/29 and then Mar 31.
                    $dueDate = $firstDue->addMonthsNoOverflow($index);
                    $account->scheduleItems()->create([
                        'installment_number' => $index + 1,
                        'due_date' => $dueDate,
                        'amount_due_cents' => $amount,
                        'amount_paid_cents' => 0,
                        'status' => $dueDate->isSameDay(today()) ? 'due' : 'upcoming',
                    ]);
                }

                $account->events()->create([
                    'event_type' => 'account_activated',
                    'description' => 'Installment account activated.',
                    'metadata' => ['first_due_date' => $firstDue->toDateString()],
                    'performed_by' => $admin->id,
                    'created_at' => now(),
                ]);

                return $account->load('scheduleItems');
            }, 3);
        } catch (QueryException $exception) {
            if (InstallmentAccount::where('installment_application_id', $application->id)->exists()) {
                throw new DomainException('This application already has an installment account.', previous: $exception);
            }

            throw $exception;
        }
    }

    public function cancel(InstallmentAccount $account, User $admin, string $reason): InstallmentAccount
    {
        return DB::transaction(function () use ($account, $admin, $reason) {
            $account = InstallmentAccount::query()->lockForUpdate()->findOrFail($account->id);
            if ($account->account_status !== InstallmentAccount::STATUS_ACTIVE) {
                throw new DomainException('Only active accounts can be cancelled.');
            }

            $account->update(['account_status' => InstallmentAccount::STATUS_CANCELLED, 'cancelled_at' => now()]);
            $account->scheduleItems()->whereColumn('amount_paid_cents', '<', 'amount_due_cents')->update(['status' => 'cancelled']);
            $account->events()->create([
                'event_type' => 'account_cancelled',
                'description' => 'Installment account cancelled: '.$reason,
                'metadata' => ['reason' => $reason],
                'performed_by' => $admin->id,
                'created_at' => now(),
            ]);

            return $account->refresh();
        }, 3);
    }

    private function nextAccountNumber(): string
    {
        $prefix = 'ACC-'.now()->format('Ymd').'-';
        $last = InstallmentAccount::query()->where('account_number', 'like', $prefix.'%')->lockForUpdate()->max('account_number');
        $sequence = $last ? ((int) substr($last, -6)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    private function toCents(string $amount): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new DomainException('Invalid application price snapshot.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }
}
