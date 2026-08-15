<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DomainException;

class InstallmentCalculatorService
{
    public const INTERVAL_MONTHLY = 'monthly';

    public const INTERVAL_BIWEEKLY = 'biweekly';

    public const INTERVAL_WEEKLY = 'weekly';

    public function calculate(
        float $price,
        int $numberOfPayments,
        float $downPayment = 0,
        float $financingFee = 0,
        string $intervalType = self::INTERVAL_MONTHLY,
        ?CarbonImmutable $startingAt = null,
    ): array {
        if ($numberOfPayments < 2) {
            throw new DomainException('Installment payments must be at least 2.');
        }

        if ($downPayment < 0) {
            throw new DomainException('Down payment cannot be negative.');
        }

        if ($financingFee < 0) {
            throw new DomainException('Financing fee cannot be negative.');
        }

        if (! in_array($intervalType, $this->intervalTypes(), true)) {
            throw new DomainException('Unsupported installment interval.');
        }

        $priceCents = $this->toCents($price);
        $financingFeeCents = $this->toCents($financingFee);
        $totalAmountCents = $priceCents + $financingFeeCents;

        // The product or variant price is the plan total. The payment count
        // includes today's first installment, so each plan divides that total
        // by its full number of payments. The final payment absorbs rounding.
        // Down payments are retained in the method signature for backwards
        // compatibility with stored plans, but do not affect this calculation.
        $futurePaymentCount = $numberOfPayments - 1;
        $regularInstallmentCents = (int) round($totalAmountCents / $numberOfPayments);
        $finalInstallmentCents = $totalAmountCents - ($regularInstallmentCents * ($numberOfPayments - 1));
        $dueNowCents = $regularInstallmentCents;
        $scheduleStart = $startingAt ?? CarbonImmutable::now();

        $futureInstallments = [];

        for ($index = 0; $index < $futurePaymentCount; $index++) {
            $futureInstallments[] = [
                'sequence' => $index + 1,
                'due_date' => $this->dueDateFor($scheduleStart, $intervalType, $index + 1)->toDateString(),
                'amount' => $this->fromCents($index === $futurePaymentCount - 1 ? $finalInstallmentCents : $regularInstallmentCents),
            ];
        }

        return [
            'price' => $this->fromCents($priceCents),
            'down_payment' => 0.0,
            'financing_fee' => $this->fromCents($financingFeeCents),
            'amount_due_now' => $this->fromCents($dueNowCents),
            'number_of_payments' => $numberOfPayments,
            'future_payment_count' => $futurePaymentCount,
            'installment_amount' => $this->fromCents($regularInstallmentCents),
            'final_installment_amount' => $this->fromCents($finalInstallmentCents),
            'future_installments' => $futureInstallments,
            'total_amount' => $this->fromCents($totalAmountCents),
            'total_financed_amount' => $this->fromCents($totalAmountCents),
            'interval_type' => $intervalType,
        ];
    }

    public function intervalTypes(): array
    {
        return [
            self::INTERVAL_MONTHLY,
            self::INTERVAL_BIWEEKLY,
            self::INTERVAL_WEEKLY,
        ];
    }

    protected function dueDateFor(CarbonImmutable $startingAt, string $intervalType, int $sequence): CarbonImmutable
    {
        return match ($intervalType) {
            self::INTERVAL_WEEKLY => $startingAt->addWeeks($sequence),
            self::INTERVAL_BIWEEKLY => $startingAt->addDays($sequence * 14),
            default => $startingAt->addMonthsNoOverflow($sequence),
        };
    }

    protected function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    protected function fromCents(int $amount): float
    {
        return round($amount / 100, 2);
    }
}
