<?php

namespace App\Services;

use App\Enums\DeviceUnitStatus;
use App\Models\DeviceUnit;
use App\Models\ProductVariant;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeviceInventoryService
{
    public function save(DeviceUnit $unit, array $payload, ?User $actor = null): DeviceUnit
    {
        try {
            return DB::transaction(function () use ($unit, $payload, $actor): DeviceUnit {
                $before = $unit->exists ? $unit->getAttributes() : [];
                $oldVariantId = $unit->product_variant_id;
                $unit->fill($this->normalizePayload($payload));
                $unit->save();
                $unit->variant()->update(['is_unit_managed' => true]);
                $this->syncImages($unit, $payload);
                $this->syncVariantStock($unit->variant);
                if ($oldVariantId && $oldVariantId !== $unit->product_variant_id) {
                    $this->syncVariantStock(ProductVariant::find($oldVariantId));
                }
                $unit->events()->create([
                    'actor_id' => $actor?->id,
                    'event_type' => $before === [] ? 'device_created' : 'device_updated',
                    'changes' => $this->safeChanges($before, $unit->getAttributes()),
                ]);
                if ($before === [] && $unit->status === DeviceUnitStatus::Available) $this->event($unit, 'marked_available', $actor);
                if ($before !== []) {
                    $events = [
                        'imei_hash' => 'imei_changed', 'condition_type' => 'condition_changed',
                        'condition_grade' => 'condition_changed', 'selling_price_override_cents' => 'price_changed',
                        'status' => match ($unit->status) {
                            DeviceUnitStatus::Available => 'marked_available', DeviceUnitStatus::Reserved => 'device_reserved',
                            DeviceUnitStatus::Sold => 'device_sold', DeviceUnitStatus::Returned => 'device_returned',
                            DeviceUnitStatus::Retired => 'device_retired', default => 'status_changed',
                        },
                    ];
                    foreach ($events as $attribute => $eventType) {
                        if (($before[$attribute] ?? null) !== ($unit->getRawOriginal($attribute) ?? null)) $this->event($unit, $eventType, $actor);
                    }
                }
                return $unit->fresh(['variant.product', 'variant.optionValues.productOption', 'images', 'events.actor']);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new DomainException('A device with this IMEI already exists.', previous: $exception);
        }
    }

    public function reserve(DeviceUnit $unit, string $token, ?\DateTimeInterface $until = null, ?User $actor = null): DeviceUnit
    {
        return DB::transaction(function () use ($unit, $token, $until, $actor) {
            $locked = DeviceUnit::query()->lockForUpdate()->findOrFail($unit->id);
            if ($locked->status !== DeviceUnitStatus::Available) throw new DomainException('This exact device is no longer available.');
            $locked->update([
                'status' => DeviceUnitStatus::Reserved,
                'reservation_token_hash' => hash('sha256', $token),
                'reserved_until' => $until ?? now()->addMinutes(30),
            ]);
            $this->event($locked, 'device_reserved', $actor);
            $this->syncVariantStock($locked->variant);
            return $locked;
        });
    }

    public function releaseExpiredReservations(): int
    {
        $count = 0;
        DeviceUnit::query()->where('status', DeviceUnitStatus::Reserved)->where('reserved_until', '<=', now())
            ->select('id')->chunkById(100, function ($units) use (&$count) {
                foreach ($units as $unit) {
                    DB::transaction(function () use ($unit, &$count) {
                        $locked = DeviceUnit::query()->lockForUpdate()->find($unit->id);
                        if (! $locked || $locked->status !== DeviceUnitStatus::Reserved || $locked->reserved_until?->isFuture()) return;
                        $locked->update(['status' => DeviceUnitStatus::Available, 'reservation_token_hash' => null, 'reserved_until' => null]);
                        $this->event($locked, 'reservation_expired');
                        $this->syncVariantStock($locked->variant);
                        $count++;
                    });
                }
            });
        return $count;
    }

    public function markSold(DeviceUnit $unit, ?User $actor = null): void
    {
        if ($unit->status !== DeviceUnitStatus::Available && $unit->status !== DeviceUnitStatus::Reserved) {
            throw new DomainException('This exact device is no longer available.');
        }
        $unit->update(['status' => DeviceUnitStatus::Sold, 'reservation_token_hash' => null, 'reserved_until' => null]);
        $this->event($unit, 'device_sold', $actor);
        $this->syncVariantStock($unit->variant);
    }

    public function syncVariantStock(?ProductVariant $variant): void
    {
        if (! $variant || ! $variant->is_unit_managed) return;
        $variant->forceFill(['stock_quantity' => $variant->availableDeviceUnits()->count()])->save();
    }

    protected function normalizePayload(array $payload): array
    {
        $payload['acquisition_cost_cents'] = $this->decimalToCents($payload['acquisition_cost'] ?? null);
        $payload['selling_price_override_cents'] = $this->decimalToCents($payload['selling_price_override'] ?? null);
        unset($payload['acquisition_cost'], $payload['selling_price_override'], $payload['images'], $payload['image_view_types'], $payload['remove_image_ids']);
        return $payload;
    }

    protected function syncImages(DeviceUnit $unit, array $payload): void
    {
        $removeIds = collect($payload['remove_image_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $removing = $unit->images()->whereKey($removeIds)->get();
        Storage::disk('public')->delete($removing->pluck('image_path')->all());
        $unit->images()->whereKey($removeIds)->delete();
        foreach ($payload['images'] ?? [] as $index => $upload) {
            if (! $upload instanceof UploadedFile) continue;
            $unit->images()->create([
                'image_path' => $upload->store('device-units/'.$unit->id, 'public'),
                'view_type' => $payload['image_view_types'][$index] ?? 'other',
                'alt_text' => $unit->variant->product->name.' exact device '.($payload['image_view_types'][$index] ?? 'photo'),
                'sort_order' => $unit->images()->count(),
                'is_primary' => ! $unit->images()->exists(),
            ]);
        }
    }

    protected function event(DeviceUnit $unit, string $type, ?User $actor = null): void
    {
        $unit->events()->create(['actor_id' => $actor?->id, 'event_type' => $type]);
    }

    protected function safeChanges(array $before, array $after): array
    {
        foreach (['imei_encrypted', 'imei_hash', 'serial_number_encrypted', 'serial_number_hash', 'reservation_token_hash'] as $key) {
            unset($before[$key], $after[$key]);
        }
        return ['before' => $before, 'after' => $after];
    }

    protected function decimalToCents(mixed $amount): ?int
    {
        if ($amount === null || $amount === '') return null;
        return (int) round(((float) $amount) * 100);
    }
}
