<?php

namespace App\Services;

use App\Models\PendingPurchaseSession;
use App\Models\Product;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Str;

class PendingPurchaseService
{
    public const SESSION_KEY = 'pending_purchase_token';

    public function __construct(protected InstallmentPlanService $plans) {}

    /** Persists only IDs and values calculated from the database. */
    public function create(Product $product, array $preview, ?string $returnUrl = null): string
    {
        $token = Str::random(64);
        PendingPurchaseSession::create([
            'token_hash' => hash('sha256', $token), 'product_id' => $product->id,
            'product_variant_id' => $preview['variant_id'],
            'option_value_ids' => $preview['option_value_ids'], 'installment_plan_id' => $preview['plan_id'],
            'amount_due_today' => $preview['amount_due_now'], 'total_amount' => $preview['total_amount'],
            // Keep the customer-facing calendar stable across sign-in and checkout.
            'scheduled_at' => CarbonImmutable::now(),
            'return_url' => $this->safeReturnUrl($returnUrl), 'expires_at' => now()->addMinutes(30),
        ]);

        return $token;
    }

    /** Reload and validate every purchasable attribute before checkout. */
    public function reopen(string $token): array
    {
        $pending = PendingPurchaseSession::query()->where('token_hash', hash('sha256', $token))->first();
        if (! $pending || $pending->isExpired()) throw new DomainException('Your saved purchase session has expired. Please select your installment plan again.');

        $product = Product::query()->publiclyAvailable()->with(['images', 'variants.images', 'variants.optionValues.productOption', 'installmentPlans'])->find($pending->product_id)
            ?? throw new DomainException('This product is no longer available. Please return to the product page.');
        $variant = $product->variants->firstWhere('id', $pending->product_variant_id);
        if (! $variant || ! $variant->is_active) throw new DomainException('The selected variant is no longer available.');
        if (! $variant->is_available) throw new DomainException('Stock changed: this variant is now out of stock.');
        $actualOptionIds = $variant->optionValues->pluck('id')->sort()->values()->all();
        $savedOptionIds = collect($pending->option_value_ids)->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($actualOptionIds !== $savedOptionIds || $variant->optionValues->contains(fn ($value) => ! $value->is_active || ! $value->productOption?->is_active)) {
            throw new DomainException('Your selected storage or color is no longer available.');
        }
        $plan = $this->plans->availablePlansForVariant($product, $variant)->firstWhere('id', $pending->installment_plan_id);
        if (! $plan) throw new DomainException('Plan availability changed. Please select an available installment plan.');

        $preview = $this->plans->previewFromPayload($plan->toArray(), (float) $variant->price, $pending->scheduled_at ?? CarbonImmutable::now());
        $changed = (float) $pending->amount_due_today !== (float) $preview['amount_due_now'] || (float) $pending->total_amount !== (float) $preview['total_amount'];
        $pending->forceFill(['amount_due_today' => $preview['amount_due_now'], 'total_amount' => $preview['total_amount']])->save();

        return compact('pending', 'product', 'variant', 'plan', 'preview', 'changed');
    }

    private function safeReturnUrl(?string $url): ?string
    {
        return $url && str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : null;
    }
}
