<?php

namespace App\Services;

use App\Models\PendingPurchaseSession;
use App\Models\Product;
use App\Models\DeviceUnit;
use App\Enums\DeviceUnitStatus;
use App\Enums\ProductStatus;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Str;

class PendingPurchaseService
{
    public const SESSION_KEY = 'pending_purchase_token';

    public function __construct(protected InstallmentPlanService $plans, protected DeviceInventoryService $inventory) {}

    /** Persists only IDs and values calculated from the database. */
    public function create(Product $product, array $preview, ?string $returnUrl = null): string
    {
        $token = Str::random(64);
        DB::transaction(function () use ($token, $product, $preview, $returnUrl): void {
            $pending = PendingPurchaseSession::create([
                'token_hash' => hash('sha256', $token), 'product_id' => $product->id,
                'product_variant_id' => $preview['variant_id'], 'device_unit_id' => $preview['device_unit_id'] ?? null,
                'option_value_ids' => $preview['option_value_ids'], 'installment_plan_id' => $preview['plan_id'],
                'amount_due_today' => $preview['amount_due_now'], 'total_amount' => $preview['total_amount'],
                'scheduled_at' => CarbonImmutable::now(),
                'return_url' => $this->safeReturnUrl($returnUrl), 'expires_at' => now()->addMinutes(30),
            ]);
            if ($pending->device_unit_id) {
                $this->inventory->reserve(DeviceUnit::findOrFail($pending->device_unit_id), $token, $pending->expires_at);
            }
        });

        return $token;
    }

    /** Reload and validate every purchasable attribute before checkout. */
    public function reopen(string $token): array
    {
        $pending = PendingPurchaseSession::query()->where('token_hash', hash('sha256', $token))->first();
        if (! $pending || $pending->isExpired()) throw new DomainException('Your saved purchase session has expired. Please select your installment plan again.');

        $product = Product::query()->where('status', ProductStatus::Active)->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->with(['images', 'variants.images', 'variants.optionValues.productOption', 'variants.deviceUnits', 'installmentPlans'])->find($pending->product_id)
            ?? throw new DomainException('This product is no longer available. Please return to the product page.');
        $variant = $product->variants->firstWhere('id', $pending->product_variant_id);
        if (! $variant || ! $variant->is_active) throw new DomainException('The selected variant is no longer available.');
        $deviceUnit = null;
        if ($pending->device_unit_id) {
            $deviceUnit = $variant->deviceUnits->firstWhere('id', $pending->device_unit_id);
            if (! $deviceUnit || $deviceUnit->status !== DeviceUnitStatus::Reserved || ! hash_equals((string) $deviceUnit->reservation_token_hash, hash('sha256', $token))) {
                throw new DomainException('This exact device is no longer reserved for your session.');
            }
        } elseif (! $variant->is_available) throw new DomainException('Stock changed: this variant is now out of stock.');
        $actualOptionIds = $variant->optionValues->pluck('id')->sort()->values()->all();
        $savedOptionIds = collect($pending->option_value_ids)->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($actualOptionIds !== $savedOptionIds || $variant->optionValues->contains(fn ($value) => ! $value->is_active || ! $value->productOption?->is_active)) {
            throw new DomainException('Your selected storage or color is no longer available.');
        }
        $plan = $this->plans->availablePlansForVariant($product, $variant, $deviceUnit)->firstWhere('id', $pending->installment_plan_id);
        if (! $plan) throw new DomainException('Plan availability changed. Please select an available installment plan.');

        $price = $deviceUnit?->selling_price ?? (float) $variant->price;
        $preview = $this->plans->previewFromPayload($plan->toArray(), $price, $pending->scheduled_at ?? CarbonImmutable::now(), $deviceUnit !== null);
        $changed = (float) $pending->amount_due_today !== (float) $preview['amount_due_now'] || (float) $pending->total_amount !== (float) $preview['total_amount'];
        $pending->forceFill(['amount_due_today' => $preview['amount_due_now'], 'total_amount' => $preview['total_amount']])->save();

        return compact('pending', 'product', 'variant', 'deviceUnit', 'plan', 'preview', 'changed');
    }

    private function safeReturnUrl(?string $url): ?string
    {
        return $url && str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : null;
    }
}
