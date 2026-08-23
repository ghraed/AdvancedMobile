<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosCheckoutService
{
    public function checkout(User $cashier, array $payload): Order
    {
        $key = trim((string) $payload['idempotency_key']);

        if ($existing = $this->existingSale($key)) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($cashier, $payload, $key): Order {
                if ($existing = $this->existingSale($key, true)) {
                    return $existing;
                }

                $quantities = collect($payload['items'])
                    ->groupBy(fn (array $item) => (int) $item['variant_id'])
                    ->map(fn (Collection $rows) => $rows->sum(fn (array $row) => (int) $row['quantity']))
                    ->sortKeys();

                $variants = ProductVariant::query()
                    ->whereKey($quantities->keys())
                    ->with(['product.category', 'product.images', 'optionValues.productOption'])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($variants->count() !== $quantities->count()) {
                    throw new DomainException('One or more selected variants no longer exist.');
                }

                $lines = $quantities->map(function (int $quantity, int $variantId) use ($variants): array {
                    /** @var ProductVariant $variant */
                    $variant = $variants->get($variantId);
                    $this->assertSellable($variant, $quantity);
                    $unitPrice = $this->decimalToCents((string) $variant->price);
                    $options = $variant->optionValues
                        ->sortBy(fn ($value) => $value->productOption?->sort_order ?? 0)
                        ->mapWithKeys(fn ($value) => [
                            $value->productOption?->name ?? 'Option' => $value->display_name ?: $value->name,
                        ])->all();

                    return [
                        'variant' => $variant,
                        'quantity' => $quantity,
                        'unit_price_cents' => $unitPrice,
                        'subtotal_cents' => $unitPrice * $quantity,
                        'options' => $options,
                    ];
                })->values();

                $subtotal = (int) $lines->sum('subtotal_cents');
                [$discountType, $discountValue, $discountCents] = $this->discount($payload['discount'] ?? [], $subtotal);
                $lines = $this->allocateDiscount($lines, $discountCents, $subtotal);
                $total = $subtotal - $discountCents;
                $payments = $this->payments($payload['payments'], $total);
                $first = $lines->first();
                /** @var ProductVariant $firstVariant */
                $firstVariant = $first['variant'];

                $order = Order::create([
                    'reference' => $this->reference('POS'),
                    'sales_channel' => 'pos',
                    'user_id' => $cashier->id,
                    'cashier_id' => $cashier->id,
                    'cashier_name' => $cashier->name,
                    'idempotency_key' => $key,
                    'product_id' => $firstVariant->product_id,
                    'product_variant_id' => $firstVariant->id,
                    'quantity' => $lines->sum('quantity'),
                    'status' => 'completed',
                    'product_name' => $lines->count() === 1 ? $firstVariant->product->name : 'POS sale ('.$lines->count().' items)',
                    'sku' => $lines->count() === 1 ? $firstVariant->sku : 'MULTI',
                    'storage' => $first['options']['Storage'] ?? $first['options']['storage'] ?? null,
                    'color' => $first['options']['Color'] ?? $first['options']['color'] ?? null,
                    'variant_price' => $first['unit_price_cents'] / 100,
                    'financing_fee' => 0,
                    'amount_due_today' => $total / 100,
                    'total_financed_amount' => 0,
                    'total_amount' => $total / 100,
                    'future_payment_count' => 0,
                    'interval_type' => 'pos',
                    'customer_snapshot' => ['sale_type' => 'walk-in', 'cashier' => $cashier->name],
                    'subtotal_cents' => $subtotal,
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'discount_cents' => $discountCents,
                    'total_cents' => $total,
                    'payment_status' => 'paid',
                ]);

                foreach ($lines as $line) {
                    /** @var ProductVariant $variant */
                    $variant = $line['variant'];
                    $order->items()->create([
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'category_id' => $variant->product->category_id,
                        'product_name' => $variant->product->name,
                        'brand' => $variant->product->brand,
                        'category_name' => $variant->product->category?->name,
                        'sku' => $variant->sku,
                        'barcode' => $variant->barcode,
                        'variant_options' => $line['options'],
                        'unit_price_cents' => $line['unit_price_cents'],
                        'unit_cost_cents' => $variant->cost_price_cents,
                        'quantity' => $line['quantity'],
                        'subtotal_cents' => $line['subtotal_cents'],
                        'discount_cents' => $line['discount_cents'],
                        'total_cents' => $line['total_cents'],
                    ]);
                }

                foreach ($payments as $payment) {
                    $order->payments()->create($payment + [
                        'status' => 'completed',
                        'created_by' => $cashier->id,
                    ]);
                }

                foreach ($lines as $line) {
                    $line['variant']->decrement('stock_quantity', $line['quantity']);
                }

                return $order->load(['items', 'payments', 'cashier']);
            }, 3);
        } catch (QueryException $exception) {
            // Concurrent retries meet at the unique idempotency constraint. The loser
            // rolls back completely and returns the committed winner.
            if ($this->isDuplicateKey($exception) && ($existing = $this->existingSale($key))) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function existingSale(string $key, bool $lock = false): ?Order
    {
        $query = Order::query()->where('sales_channel', 'pos')->where('idempotency_key', $key);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->with(['items', 'payments', 'cashier'])->first();
    }

    private function assertSellable(ProductVariant $variant, int $quantity): void
    {
        if (! $variant->is_active) {
            throw new DomainException("{$variant->sku} is inactive and cannot be sold.");
        }
        if (! $variant->product || $variant->product->status !== ProductStatus::Active) {
            throw new DomainException("{$variant->sku} belongs to an inactive product.");
        }
        if (! $variant->product->category?->is_active) {
            throw new DomainException("{$variant->sku} belongs to an inactive category.");
        }
        if ($variant->optionValues->contains(fn ($value) => ! $value->is_active || ! $value->productOption?->is_active)) {
            throw new DomainException("{$variant->sku} has an inactive option and cannot be sold.");
        }
        if ($quantity < 1) {
            throw new DomainException('Sale quantities must be positive.');
        }
        if ($variant->stock_quantity < $quantity) {
            throw new DomainException("Insufficient stock for {$variant->sku}. Only {$variant->stock_quantity} available.");
        }
    }

    private function discount(array $discount, int $subtotal): array
    {
        $type = $discount['type'] ?? null;
        if ($type === null || (float) ($discount['value'] ?? 0) === 0.0) {
            return [null, null, 0];
        }

        $value = $discount['value'];
        $cents = $type === 'fixed'
            ? (int) $value
            : (int) round($subtotal * ((float) $value / 100), 0, PHP_ROUND_HALF_UP);

        if ($cents < 0 || $cents > $subtotal) {
            throw new DomainException('The discount cannot exceed the sale subtotal.');
        }

        return [$type, $type === 'fixed' ? (string) (int) $value : (string) round((float) $value, 2), $cents];
    }

    private function payments(array $rows, int $total): array
    {
        $payments = collect($rows)->map(function (array $row) use ($total): array {
            $amount = (int) $row['amount_cents'];
            if ($amount < 0 || ($total > 0 && $amount === 0)) {
                throw new DomainException('Payment amounts must be greater than zero.');
            }

            $received = $row['method'] === 'cash'
                ? (int) ($row['cash_received_cents'] ?? $amount)
                : null;
            if ($received !== null && $received < $amount) {
                throw new DomainException('Cash received cannot be less than the cash payment amount.');
            }

            return [
                'payment_method' => $row['method'],
                'amount_cents' => $amount,
                'reference' => filled($row['reference'] ?? null) ? trim((string) $row['reference']) : null,
                'cash_received_cents' => $received,
                'change_due_cents' => $received === null ? 0 : $received - $amount,
            ];
        })->values();

        if ((int) $payments->sum('amount_cents') !== $total) {
            throw new DomainException('Payments must equal the final sale total exactly.');
        }

        return $payments->all();
    }

    /** Allocate an order discount in cents without rounding drift. */
    private function allocateDiscount(Collection $lines, int $discountCents, int $subtotal): Collection
    {
        if ($discountCents === 0 || $subtotal === 0) {
            return $lines->map(fn (array $line) => $line + [
                'discount_cents' => 0,
                'total_cents' => $line['subtotal_cents'],
            ]);
        }

        $allocated = 0;
        $lines = $lines->values()->map(function (array $line) use ($discountCents, $subtotal, &$allocated): array {
            $share = intdiv($discountCents * $line['subtotal_cents'], $subtotal);
            $allocated += $share;

            return $line + ['discount_cents' => $share];
        });

        $remainder = $discountCents - $allocated;
        for ($index = 0; $index < $remainder; $index++) {
            $lineIndex = $index % $lines->count();
            $line = $lines->get($lineIndex);
            $line['discount_cents']++;
            $lines->put($lineIndex, $line);
        }

        return $lines->map(function (array $line): array {
            $line['total_cents'] = $line['subtotal_cents'] - $line['discount_cents'];

            return $line;
        });
    }

    private function decimalToCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function reference(string $prefix): string
    {
        do {
            $reference = $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (Order::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23000' || ($exception->errorInfo[1] ?? null) === 1062;
    }
}
