<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\ProductVariant;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosRefundService
{
    public function refund(Order $sale, User $user, string $reason): OrderRefund
    {
        return DB::transaction(function () use ($sale, $user, $reason): OrderRefund {
            $order = Order::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if (! $order->isPosSale()) {
                throw new DomainException('Only POS sales can be refunded here.');
            }
            if ($order->status !== 'completed' || $order->payment_status !== 'paid' || $order->refunded_at !== null) {
                throw new DomainException('This sale is not eligible for a refund.');
            }
            if ($order->refunds()->exists()) {
                throw new DomainException('This sale has already been refunded.');
            }

            $items = $order->items()->orderBy('product_variant_id')->get();
            $variantIds = $items->pluck('product_variant_id')->filter()->unique()->sort()->values();
            $variants = ProductVariant::query()->whereKey($variantIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            if ($variants->count() !== $variantIds->count()) {
                throw new DomainException('Stock cannot be restored because a sold variant no longer exists.');
            }

            $restored = $items->map(fn ($item) => [
                'product_variant_id' => $item->product_variant_id,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
            ])->all();

            $refund = $order->refunds()->create([
                'reference' => 'REF-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'amount_cents' => $order->total_cents,
                'reason' => trim($reason),
                'restored_items' => $restored,
                'refunded_by' => $user->id,
                'refunded_by_name' => $user->name,
            ]);

            foreach ($items as $item) {
                $variants->get($item->product_variant_id)->increment('stock_quantity', $item->quantity);
            }

            $order->payments()->update(['status' => 'refunded']);
            $order->forceFill([
                'status' => 'refunded',
                'payment_status' => 'refunded',
                'refunded_at' => now(),
            ])->save();

            return $refund;
        }, 3);
    }
}
