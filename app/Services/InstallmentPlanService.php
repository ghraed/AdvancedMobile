<?php

namespace App\Services;

use App\Models\InstallmentPlan;
use App\Models\Product;
use App\Models\ProductVariant;
use Carbon\CarbonImmutable;
use DomainException;

class InstallmentPlanService
{
    public function __construct(
        protected InstallmentCalculatorService $calculator,
    ) {}

    public function calculatePlan(
        float $cashPrice,
        int $numberOfPayments,
        float $downPayment = 0,
        float $financingFee = 0,
        string $intervalType = InstallmentCalculatorService::INTERVAL_MONTHLY,
        ?CarbonImmutable $startingAt = null,
    ): array {
        $calculated = $this->calculator->calculate(
            $cashPrice,
            $numberOfPayments,
            $downPayment,
            $financingFee,
            $intervalType,
            $startingAt,
        );

        return [
            'price' => $calculated['price'],
            'down_payment' => $calculated['down_payment'],
            'financing_fee' => $calculated['financing_fee'],
            'amount_due_now' => $calculated['amount_due_now'],
            'installment_amount' => $calculated['installment_amount'],
            'final_installment_amount' => $calculated['final_installment_amount'],
            'total_amount' => $calculated['total_amount'],
            'total_financed_amount' => $calculated['total_financed_amount'],
            'interval_type' => $calculated['interval_type'],
            'future_payment_count' => $calculated['future_payment_count'],
            'schedule' => array_map(
                fn (array $installment) => $installment['amount'],
                $calculated['future_installments'],
            ),
            'future_installments' => $calculated['future_installments'],
        ];
    }

    public function resolvePlanPayload(
        float $price,
        int $numberOfPayments,
        float $downPayment = 0,
        float $financingFee = 0,
        string $intervalType = InstallmentCalculatorService::INTERVAL_MONTHLY,
        ?CarbonImmutable $startingAt = null,
    ): array {
        return $this->calculatePlan(
            $price,
            $numberOfPayments,
            $downPayment,
            $financingFee,
            $intervalType,
            $startingAt,
        );
    }

    public function previewFromPayload(array $plan, float $price, ?CarbonImmutable $startingAt = null): array
    {
        return $this->resolvePlanPayload(
            (float) ($plan['total_amount'] ?? $price),
            (int) ($plan['number_of_payments'] ?? 0),
            0,
            0,
            (string) ($plan['interval_type'] ?? InstallmentCalculatorService::INTERVAL_MONTHLY),
            $startingAt,
        );
    }

    public function resolvePlanForProduct(Product $product, ?ProductVariant $variant, int $numberOfPayments, string $intervalType = InstallmentCalculatorService::INTERVAL_MONTHLY): ?InstallmentPlan
    {
        if ($variant !== null) {
            $variantPlan = $variant->installmentPlans()
                ->active()
                ->where('number_of_payments', $numberOfPayments)
                ->where('interval_type', $intervalType)
                ->first();

            if ($variantPlan !== null) {
                return $variantPlan;
            }
        }

        return $product->installmentPlans()
            ->active()
            ->whereNull('product_variant_id')
            ->where('number_of_payments', $numberOfPayments)
            ->where('interval_type', $intervalType)
            ->first();
    }

    /** The visitor can only select active plans applicable to their exact variant. */
    public function availablePlansForVariant(Product $product, ProductVariant $variant)
    {
        $variantPlans = $variant->relationLoaded('installmentPlans')
            ? $variant->installmentPlans->where('is_active', true)
            : $variant->installmentPlans()->active()->get();

        $plans = $variantPlans->whereIn('number_of_payments', [3, 6, 9])
            ->where('interval_type', InstallmentCalculatorService::INTERVAL_MONTHLY)
            ->sortBy('number_of_payments')->values();

        return $plans->count() === 3 && $plans->pluck('number_of_payments')->sort()->values()->all() === [3, 6, 9]
            ? $plans
            : collect();
    }

    public function resolvePriceForPlan(Product $product, InstallmentPlan $plan, ?ProductVariant $selectedVariant = null): float
    {
        if ($plan->product_variant_id !== null) {
            $variant = $plan->relationLoaded('variant') ? $plan->variant : $plan->variant()->first();

            if (! $variant) {
                throw new DomainException('The installment plan variant could not be resolved.');
            }

            return (float) $variant->price;
        }

        if ($selectedVariant !== null) {
            return (float) $selectedVariant->price;
        }

        $fallbackVariant = $product->relationLoaded('variants')
            ? $product->variants->sortBy('price')->first()
            : $product->variants()->orderBy('price')->first();

        if (! $fallbackVariant) {
            throw new DomainException('A variant price is required to calculate a product-level installment plan.');
        }

        return (float) $fallbackVariant->price;
    }
}
