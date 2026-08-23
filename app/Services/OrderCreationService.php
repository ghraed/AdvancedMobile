<?php

namespace App\Services;

use App\Models\InstallmentPlan;
use App\Models\Order;
use App\Models\PendingPurchaseSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\DeviceUnit;
use App\Enums\DeviceUnitStatus;
use App\Enums\ProductStatus;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderCreationService
{
    public function __construct(protected InstallmentPlanService $plans, protected DeviceInventoryService $inventory) {}

    /**
     * Creates one immutable order from a pending selection. All pricing and stock
     * checks happen after locks are acquired; request input is intentionally absent.
     */
    public function create(User $user, string $token): Order
    {
        return DB::transaction(function () use ($user, $token) {
            $pending = PendingPurchaseSession::query()
                ->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $pending || $pending->isExpired()) {
                throw new DomainException('Your saved purchase session has expired. Please select your installment plan again.');
            }
            if ($pending->user_id !== null && $pending->user_id !== $user->id) {
                throw new DomainException('This saved purchase belongs to another customer.');
            }

            // A unique pending_purchase_session_id also protects this path if two requests race.
            if ($existing = Order::query()->where('pending_purchase_session_id', $pending->id)->first()) {
                return $existing;
            }
            if ($pending->completed_at !== null) {
                throw new DomainException('This saved purchase has already been completed.');
            }

            $product = Product::query()->where('status', ProductStatus::Active)->whereHas('category', fn ($q) => $q->where('is_active', true))->with('category')->whereKey($pending->product_id)->lockForUpdate()->first()
                ?? throw new DomainException('This product is no longer available.');
            $variant = ProductVariant::query()->whereKey($pending->product_variant_id)->where('product_id', $product->id)->lockForUpdate()->first();
            if (! $variant || ! $variant->is_active) {
                throw new DomainException('The selected variant is no longer available.');
            }
            $deviceUnit = null;
            if ($pending->device_unit_id) {
                $deviceUnit = DeviceUnit::query()->whereKey($pending->device_unit_id)->where('product_variant_id', $variant->id)->lockForUpdate()->first();
                if (! $deviceUnit || $deviceUnit->status !== DeviceUnitStatus::Reserved || ! hash_equals((string) $deviceUnit->reservation_token_hash, hash('sha256', $token)) || $deviceUnit->reserved_until?->isPast()) {
                    throw new DomainException('This exact device is no longer reserved for your session.');
                }
            } elseif ($variant->stock_quantity < 1) {
                throw new DomainException('Stock changed: this variant is now out of stock.');
            }

            $variant->load('optionValues.productOption');
            $actualOptionIds = $variant->optionValues->pluck('id')->sort()->values()->all();
            $savedOptionIds = collect($pending->option_value_ids)->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($actualOptionIds !== $savedOptionIds || $variant->optionValues->contains(fn ($value) => ! $value->is_active || ! $value->productOption?->is_active)) {
                throw new DomainException('Your selected storage or color is no longer available.');
            }

            // Revalidate the exact selected plan under a lock. The catalog UI may
            // prefer complete 3/6/9 sets, but an already confirmed valid plan must
            // not become unusable merely because other terms are not configured.
            $plan = InstallmentPlan::query()
                ->where('product_id', $product->id)
                ->whereKey($pending->installment_plan_id)
                ->where('is_active', true)
                ->where(fn ($query) => $query
                    ->whereNull('product_variant_id')
                    ->orWhere('product_variant_id', $variant->id))
                ->lockForUpdate()
                ->first();
            if (! $plan) {
                throw new DomainException('Plan availability changed. Please select an available installment plan.');
            }
            if ($deviceUnit && ! $deviceUnit->installments_enabled) throw new DomainException('Installments are not available for this device.');

            // Monetary values are recalculated under locks, while the confirmed
            // payment dates remain anchored to the original selection.
            $scheduledAt = $pending->scheduled_at ?? CarbonImmutable::now();
            $price = $deviceUnit?->selling_price ?? (float) $variant->price;
            $preview = $this->plans->previewFromPayload($plan->toArray(), $price, $scheduledAt, $deviceUnit !== null);
            $options = $variant->optionValues->mapWithKeys(fn ($value) => [$value->productOption?->slug => $value->display_name ?: $value->name]);
            $order = Order::create([
                'reference' => $this->reference(), 'sales_channel' => 'online', 'user_id' => $user->id, 'pending_purchase_session_id' => $pending->id,
                'product_id' => $product->id, 'product_variant_id' => $variant->id, 'installment_plan_id' => $plan->id,
                'quantity' => 1, 'status' => 'pending', 'product_name' => $product->name, 'sku' => $variant->sku,
                'storage' => $options->get('storage'), 'color' => $options->get('color'),
                'variant_price' => $price, 'financing_fee' => $preview['financing_fee'],
                'amount_due_today' => $preview['amount_due_now'], 'total_financed_amount' => $preview['total_financed_amount'],
                'total_amount' => $preview['total_amount'], 'future_payment_count' => $preview['future_payment_count'],
                'interval_type' => $preview['interval_type'], 'customer_snapshot' => ['name' => $user->name, 'email' => $user->email],
            ]);
            $unitPriceCents = $deviceUnit?->selling_price_cents ?? $this->decimalToCents((string) $variant->price);
            $deviceSnapshot = $deviceUnit?->publicSnapshot();
            if ($deviceSnapshot && $deviceUnit->warranty_days) $deviceSnapshot['warranty_sale_until'] = $scheduledAt->addDays($deviceUnit->warranty_days)->toDateString();
            $order->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'device_unit_id' => $deviceUnit?->id,
                'category_id' => $product->category_id,
                'product_name' => $product->name,
                'brand' => $product->brand,
                'category_name' => $product->category?->name,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'variant_options' => $options->all(),
                'device_snapshot' => $deviceSnapshot,
                'unit_price_cents' => $unitPriceCents,
                'unit_cost_cents' => $deviceUnit?->acquisition_cost_cents ?? $variant->cost_price_cents,
                'quantity' => 1,
                'subtotal_cents' => $unitPriceCents,
                'discount_cents' => 0,
                'total_cents' => $unitPriceCents,
            ]);
            $order->installments()->create([
                'sequence' => 1, 'amount' => $preview['amount_due_now'], 'due_date' => $scheduledAt->toDateString(), 'status' => 'due',
            ]);
            foreach ($preview['future_installments'] as $payment) {
                $order->installments()->create([
                    'sequence' => $payment['sequence'] + 1, 'amount' => $payment['amount'], 'due_date' => $payment['due_date'], 'status' => 'pending',
                ]);
            }

            // This storefront's order policy is immediate stock deduction, guarded by the variant lock.
            if ($deviceUnit) $this->inventory->markSold($deviceUnit, $user);
            else $variant->decrement('stock_quantity');
            $pending->forceFill(['user_id' => $user->id, 'completed_at' => now()])->save();

            return $order->load('installments');
        }, 3);
    }

    private function reference(): string
    {
        return 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }

    private function decimalToCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) substr(str_pad($fraction, 2, '0'), 0, 2);
    }
}
