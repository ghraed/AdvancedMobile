<?php

namespace App\Services;

use App\Models\InstallmentAccount;
use App\Models\InstallmentPayment;
use App\Models\InstallmentScheduleItem;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstallmentPaymentService
{
    public function recordPayment(InstallmentAccount $account, array $data, User $recordedBy): InstallmentPayment
    {
        return $this->record(
            $account,
            (int) $data['amount_cents'],
            (string) $data['payment_method'],
            $recordedBy,
            $data['reference'] ?? null,
            $data['notes'] ?? null,
            $data['idempotency_key'] ?? null,
            $data['paid_at'] ?? null,
        );
    }

    public function record(
        InstallmentAccount $account,
        int $amountCents,
        string $paymentMethod,
        User $recordedBy,
        ?string $reference = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
        CarbonInterface|string|null $paidAt = null,
    ): InstallmentPayment {
        if ($amountCents <= 0) {
            throw new DomainException('Payment amount must be greater than zero.');
        }
        if (! in_array($paymentMethod, InstallmentPayment::METHODS, true)) {
            throw new DomainException('Unsupported payment method.');
        }

        return DB::transaction(function () use ($account, $amountCents, $paymentMethod, $recordedBy, $reference, $notes, $idempotencyKey, $paidAt) {
            $account = InstallmentAccount::query()->lockForUpdate()->findOrFail($account->id);

            if ($idempotencyKey) {
                $existing = $account->payments()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }
            if ($account->account_status !== InstallmentAccount::STATUS_ACTIVE) {
                throw new DomainException('Payments can only be recorded against active accounts.');
            }
            if ($amountCents > $account->remaining_balance_cents) {
                throw new DomainException('Payment cannot exceed the remaining balance.');
            }

            $items = InstallmentScheduleItem::query()
                ->where('installment_account_id', $account->id)
                ->orderBy('installment_number')
                ->lockForUpdate()
                ->get();

            $payment = $account->payments()->create([
                'receipt_number' => $this->receiptNumber(),
                'amount_cents' => $amountCents,
                'remaining_balance_after_cents' => $account->remaining_balance_cents - $amountCents,
                'payment_method' => $paymentMethod,
                'reference' => $reference,
                'paid_at' => $paidAt ?? now(),
                'recorded_by' => $recordedBy->id,
                'notes' => $notes,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->allocatePayment($payment, $items, $amountCents);
            $newPaid = $account->amount_paid_cents + $amountCents;
            $changes = ['amount_paid_cents' => $newPaid];
            if ($newPaid === $account->total_payable_cents) {
                $changes += ['account_status' => InstallmentAccount::STATUS_COMPLETED, 'completed_at' => now()];
            }
            $account->update($changes);
            $account->events()->create([
                'event_type' => 'payment_recorded',
                'description' => 'Payment '.$payment->receipt_number.' recorded.',
                'metadata' => ['payment_id' => $payment->id, 'amount_cents' => $amountCents],
                'performed_by' => $recordedBy->id,
                'created_at' => now(),
            ]);
            if ($newPaid === $account->total_payable_cents) {
                $account->events()->create([
                    'event_type' => 'account_completed',
                    'description' => 'Installment account paid in full.',
                    'performed_by' => $recordedBy->id,
                    'created_at' => now(),
                ]);
            }

            return $payment->load('allocations.scheduleItem', 'account.application');
        }, 3);
    }

    public function reversePayment(InstallmentPayment $payment, User $reversedBy, string $reason): InstallmentPayment
    {
        if (trim($reason) === '') {
            throw new DomainException('A reversal reason is required.');
        }

        return DB::transaction(function () use ($payment, $reversedBy, $reason) {
            $payment = InstallmentPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $account = InstallmentAccount::query()->lockForUpdate()->findOrFail($payment->installment_account_id);
            InstallmentScheduleItem::query()->where('installment_account_id', $account->id)->lockForUpdate()->get();

            if ($payment->reversed_at) {
                throw new DomainException('This payment has already been reversed.');
            }
            $payment->update(['reversed_at' => now(), 'reversed_by' => $reversedBy->id, 'reversal_reason' => $reason]);
            $this->rebuildAllocations($account);
            $account->refresh();
            $account->events()->create([
                'event_type' => 'payment_reversed',
                'description' => 'Payment '.$payment->receipt_number.' reversed: '.$reason,
                'metadata' => ['payment_id' => $payment->id, 'amount_cents' => $payment->amount_cents],
                'performed_by' => $reversedBy->id,
                'created_at' => now(),
            ]);

            return $payment->refresh()->load('allocations.scheduleItem', 'account');
        }, 3);
    }

    private function rebuildAllocations(InstallmentAccount $account): void
    {
        $wasCancelled = $account->account_status === InstallmentAccount::STATUS_CANCELLED;
        $items = InstallmentScheduleItem::query()->where('installment_account_id', $account->id)->orderBy('installment_number')->get();
        DB::table('installment_payment_allocations')->whereIn('installment_schedule_item_id', $items->modelKeys())->delete();
        foreach ($items as $item) {
            $item->update(['amount_paid_cents' => 0, 'paid_at' => null, 'status' => 'upcoming']);
        }

        $runningPaid = 0;
        $payments = InstallmentPayment::query()->where('installment_account_id', $account->id)
            ->whereNull('reversed_at')->orderBy('paid_at')->orderBy('id')->get();
        foreach ($payments as $activePayment) {
            $this->allocatePayment($activePayment, $items, $activePayment->amount_cents);
            $runningPaid += $activePayment->amount_cents;
            $activePayment->update(['remaining_balance_after_cents' => $account->total_payable_cents - $runningPaid]);
            $items = $items->map(fn ($item) => $item->refresh());
        }

        $completed = $runningPaid === $account->total_payable_cents;
        $account->update([
            'amount_paid_cents' => $runningPaid,
            'account_status' => $wasCancelled ? InstallmentAccount::STATUS_CANCELLED : ($completed ? InstallmentAccount::STATUS_COMPLETED : InstallmentAccount::STATUS_ACTIVE),
            'completed_at' => ! $wasCancelled && $completed ? ($account->completed_at ?? now()) : null,
        ]);
        if ($wasCancelled) {
            InstallmentScheduleItem::query()->where('installment_account_id', $account->id)
                ->whereColumn('amount_paid_cents', '<', 'amount_due_cents')->update(['status' => 'cancelled']);
        }
    }

    private function allocatePayment(InstallmentPayment $payment, $items, int $amountCents): void
    {
        $remaining = $amountCents;
        $firstItemId = null;
        foreach ($items as $item) {
            $available = $item->amount_due_cents - $item->amount_paid_cents;
            if ($available <= 0) {
                continue;
            }
            $allocated = min($available, $remaining);
            $firstItemId ??= $item->id;
            $payment->allocations()->create(['installment_schedule_item_id' => $item->id, 'amount_cents' => $allocated]);
            $newPaid = $item->amount_paid_cents + $allocated;
            $item->update([
                'amount_paid_cents' => $newPaid,
                'status' => $newPaid === $item->amount_due_cents ? 'paid' : 'partial',
                'paid_at' => $newPaid === $item->amount_due_cents ? $payment->paid_at : null,
            ]);
            $remaining -= $allocated;
            if ($remaining === 0) {
                break;
            }
        }
        if ($remaining !== 0) {
            throw new DomainException('Payment could not be fully allocated.');
        }
        $payment->update(['schedule_item_id' => $firstItemId]);
    }

    private function receiptNumber(): string
    {
        do {
            $number = 'RCP-'.now()->format('Ymd').'-'.strtoupper(Str::random(10));
        } while (InstallmentPayment::where('receipt_number', $number)->exists());

        return $number;
    }
}
