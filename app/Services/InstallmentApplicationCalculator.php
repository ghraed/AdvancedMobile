<?php

namespace App\Services;

use InvalidArgumentException;

class InstallmentApplicationCalculator
{
    public function calculate(string $price, int $months): array
    {
        $rules = config('installments.durations', []);
        if (! isset($rules[$months])) {
            throw new InvalidArgumentException('Unsupported installment duration.');
        } $cents = $this->toCents($price);
        $total = intdiv($cents * (10000 + (int) $rules[$months]['fee_basis_points']) + 5000, 10000);

        return ['product_price' => $this->format($cents), 'total_payable' => $this->format($total), 'monthly_payment' => $this->format(intdiv($total + $months - 1, $months)), 'currency' => config('installments.currency', 'USD')];
    }

    private function toCents(string $amount): int
    {
        if (! preg_match('/^(\\d+)(?:\\.(\\d{1,2}))?$/', $amount, $m)) {
            throw new InvalidArgumentException('Invalid product price.');
        }

return ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function format(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
