<?php

namespace App\Models;

use App\Enums\DeviceConditionGrade;
use App\Enums\DeviceConditionType;
use App\Enums\DeviceUnitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class DeviceUnit extends Model
{
    use HasFactory;

    public const CHECKLIST_KEYS = [
        'screen', 'frame_body', 'back_glass', 'cameras', 'microphone', 'speaker',
        'charging_port', 'wifi', 'bluetooth', 'cellular_network', 'biometrics',
        'buttons', 'vibration', 'battery', 'water_damage_indicators',
    ];

    public const ACCESSORY_KEYS = [
        'original_box', 'charger', 'cable', 'case', 'screen_protector', 'sim_tool', 'other',
    ];

    protected $fillable = [
        'product_variant_id', 'condition_type', 'condition_grade', 'imei', 'serial_number',
        'battery_health_percent', 'cosmetic_condition', 'customer_visible_condition_notes',
        'known_defects', 'condition_checklist', 'included_accessories', 'refurbished_at',
        'refurbished_by', 'parts_replaced', 'customer_visible_refurbishment_details',
        'refurbishment_notes', 'warranty_days', 'warranty_until', 'acquisition_cost_cents',
        'selling_price_override_cents', 'installments_enabled', 'status',
        'reservation_token_hash', 'reserved_until',
    ];

    protected $hidden = [
        'imei_encrypted', 'imei_hash', 'serial_number_encrypted', 'serial_number_hash',
        'acquisition_cost_cents', 'refurbishment_notes', 'reservation_token_hash',
    ];

    protected $casts = [
        'condition_type' => DeviceConditionType::class,
        'condition_grade' => DeviceConditionGrade::class,
        'status' => DeviceUnitStatus::class,
        'imei_encrypted' => 'encrypted',
        'serial_number_encrypted' => 'encrypted',
        'known_defects' => 'array',
        'condition_checklist' => 'array',
        'included_accessories' => 'array',
        'parts_replaced' => 'array',
        'refurbished_at' => 'date',
        'warranty_until' => 'date',
        'reserved_until' => 'datetime',
        'battery_health_percent' => 'integer',
        'acquisition_cost_cents' => 'integer',
        'selling_price_override_cents' => 'integer',
        'installments_enabled' => 'boolean',
    ];

    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function images(): HasMany { return $this->hasMany(DeviceUnitImage::class)->orderByDesc('is_primary')->orderBy('sort_order'); }
    public function events(): HasMany { return $this->hasMany(DeviceUnitEvent::class)->latest(); }
    public function orderItem() { return $this->hasOne(OrderItem::class); }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', DeviceUnitStatus::Available);
    }

    public function scopePubliclyPurchasable(Builder $query): Builder
    {
        return $query->available()
            ->whereHas('variant', fn (Builder $variant) => $variant->where('is_active', true)
                ->whereHas('product', fn (Builder $product) => $product->publiclyAvailable()));
    }

    public function setImeiAttribute(?string $value): void
    {
        $normalized = self::normalizeIdentifier($value);
        $this->attributes['imei_encrypted'] = Crypt::encryptString($normalized);
        $this->attributes['imei_hash'] = hash('sha256', $normalized);
    }

    public function setSerialNumberAttribute(?string $value): void
    {
        $normalized = self::normalizeIdentifier($value);
        $this->attributes['serial_number_encrypted'] = $normalized === '' ? null : Crypt::encryptString($normalized);
        $this->attributes['serial_number_hash'] = $normalized === '' ? null : hash('sha256', $normalized);
    }

    public function getImeiAttribute(): ?string { return $this->imei_encrypted; }
    public function getSerialNumberAttribute(): ?string { return $this->serial_number_encrypted; }
    public function getMaskedImeiAttribute(): string { return str_repeat('*', 11).substr((string) $this->imei_encrypted, -4); }
    public function getMaskedSerialNumberAttribute(): ?string
    {
        $serial = (string) $this->serial_number_encrypted;
        return $serial === '' ? null : str_repeat('*', max(4, strlen($serial) - 4)).substr($serial, -4);
    }
    public function getSellingPriceCentsAttribute(): int
    {
        return $this->selling_price_override_cents ?? (int) round(((float) $this->variant->price) * 100);
    }
    public function getSellingPriceAttribute(): float { return $this->selling_price_cents / 100; }
    public function getWarrantyLabelAttribute(): string
    {
        if ($this->warranty_until) return 'Warranty until '.$this->warranty_until->format('M j, Y');
        if (! $this->warranty_days) return 'No warranty';
        if ($this->warranty_days % 30 === 0) return ($this->warranty_days / 30).'-month shop warranty';
        return $this->warranty_days.'-day shop warranty';
    }

    public function publicSnapshot(): array
    {
        return [
            'unit_id' => $this->id,
            'condition' => $this->condition_type->value,
            'condition_label' => $this->condition_type->label(),
            'grade' => $this->condition_grade?->value,
            'grade_label' => $this->condition_grade?->label(),
            'battery_health_percent' => $this->battery_health_percent,
            'warranty' => $this->warranty_label,
            'known_defects' => $this->known_defects ?? [],
            'included_accessories' => $this->included_accessories ?? [],
            'condition_checklist' => $this->condition_checklist ?? [],
            'condition_notes' => $this->customer_visible_condition_notes,
            'refurbishment_details' => $this->customer_visible_refurbishment_details,
            'refurbished_by' => $this->refurbished_by,
            'refurbished_at' => $this->refurbished_at?->toDateString(),
            'parts_replaced' => $this->parts_replaced ?? [],
            'masked_imei' => $this->masked_imei,
        ];
    }

    public static function normalizeIdentifier(?string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string) $value)) ?? '');
    }

    public static function imeiHash(string $imei): string { return hash('sha256', self::normalizeIdentifier($imei)); }

    public static function isValidImei(string $imei): bool
    {
        $imei = self::normalizeIdentifier($imei);
        if (! preg_match('/^\d{15}$/', $imei)) return false;
        $sum = 0;
        foreach (str_split($imei) as $index => $digit) {
            $value = (int) $digit;
            if ($index % 2 === 1) { $value *= 2; if ($value > 9) $value -= 9; }
            $sum += $value;
        }
        return $sum % 10 === 0;
    }
}
