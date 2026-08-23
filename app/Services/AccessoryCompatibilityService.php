<?php

namespace App\Services;

use App\Enums\CompatibilityRuleType;
use App\Enums\ProductType;
use App\Models\AccessoryCompatibilityRule;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AccessoryCompatibilityService
{
    /**
     * ProductVariant parameters intentionally form part of the contract so future
     * size/connector rules can become variant-specific without changing callers.
     */
    public function determine(
        Product $accessory,
        Product $device,
        ?ProductVariant $accessoryVariant = null,
        ?ProductVariant $deviceVariant = null,
    ): array {
        if ($accessory->product_type !== ProductType::Accessory || $device->product_type !== ProductType::Device) {
            return $this->result('unknown', null, 'Compatibility data is not available for this product combination.');
        }

        $accessory->loadMissing(['compatibilityExclusions:id', 'exactCompatibleDevices:id', 'compatibilityRules']);
        $device->loadMissing('deviceProfile');

        if ($accessory->compatibilityExclusions->contains('id', $device->id)) {
            return $this->result('incompatible', 'exclusion', 'This device is explicitly excluded by the accessory compatibility data.');
        }

        if ($accessory->exactCompatibleDevices->contains('id', $device->id)) {
            return $this->result('compatible', 'exact', 'Designed for '.$device->name.'.');
        }

        $profile = $device->deviceProfile;
        if (! $profile) {
            return $this->result('unknown', null, 'No compatibility profile is available for this device.');
        }

        $checks = [
            CompatibilityRuleType::ModelIdentifier->value => [$profile->model_identifier, 'model', 'Matches device model '.$profile->model_identifier.'.'],
            CompatibilityRuleType::ModelFamily->value => [$profile->model_family, 'family', 'Designed for the '.$profile->model_family.' family.'],
            CompatibilityRuleType::Connector->value => [$profile->connector_type, 'connector', 'Supports '.$profile->connector_type.'.'],
        ];

        foreach ($checks as $ruleType => [$value, $matchType, $reason]) {
            if (filled($value) && $this->rulesContain($accessory->compatibilityRules, $ruleType, (string) $value)) {
                return $this->result('compatible', $matchType, $reason);
            }
        }

        foreach ($profile->charging_standards ?? [] as $standard) {
            if ($this->rulesContain($accessory->compatibilityRules, CompatibilityRuleType::ChargingStandard->value, (string) $standard)) {
                return $this->result('compatible', 'charging_standard', 'Supports '.$standard.' charging.');
            }
        }

        foreach ($profile->features ?? [] as $feature) {
            if ($this->rulesContain($accessory->compatibilityRules, CompatibilityRuleType::Feature->value, (string) $feature)) {
                return $this->result('compatible', 'feature', 'Compatible through '.$feature.' support.');
            }
        }

        return $this->result('unknown', null, 'No matching compatibility rule is recorded.');
    }

    public function compatibleAccessoriesForDevice(Product $device, bool $publicOnly = true): Collection
    {
        $device->loadMissing('deviceProfile');

        $accessories = Product::query()
            ->where('product_type', ProductType::Accessory->value)
            ->when($publicOnly, fn ($query) => $query->publiclyAvailable())
            ->with([
                'compatibilityRules', 'exactCompatibleDevices:id', 'compatibilityExclusions:id',
                'category:id,name,slug', 'images',
                'variants' => fn ($query) => $publicOnly ? $query->available() : $query,
                'variants.optionValues.productOption', 'installmentPlans',
            ])
            ->get();

        return $accessories
            ->map(fn (Product $accessory) => ['product' => $accessory] + $this->determine($accessory, $device))
            ->where('status', 'compatible')
            ->sortBy(fn (array $match) => [$this->rank($match['match_type']), $match['product']->name])
            ->values();
    }

    public function compatibleDevicesForAccessory(Product $accessory, bool $publicOnly = true): Collection
    {
        $devices = Product::query()
            ->where('product_type', ProductType::Device->value)
            ->when($publicOnly, fn ($query) => $query->publiclyAvailable())
            ->with(['deviceProfile', 'category:id,name,slug', 'images', 'variants' => fn ($query) => $publicOnly ? $query->available() : $query])
            ->get();

        $accessory->loadMissing(['compatibilityRules', 'exactCompatibleDevices:id', 'compatibilityExclusions:id']);

        return $devices
            ->map(fn (Product $device) => ['product' => $device] + $this->determine($accessory, $device))
            ->where('status', 'compatible')
            ->sortBy(fn (array $match) => [$this->rank($match['match_type']), $match['product']->name])
            ->values();
    }

    protected function rulesContain(Collection $rules, string $type, string $value): bool
    {
        $normalized = $this->normalize($value);

        return $rules->contains(fn (AccessoryCompatibilityRule $rule) => $rule->rule_type->value === $type
            && $this->normalize($rule->match_value) === $normalized);
    }

    protected function normalize(string $value): string
    {
        return Str::lower(trim($value));
    }

    protected function result(string $status, ?string $matchType, string $reason): array
    {
        return [
            'status' => $status,
            'compatible' => $status === 'compatible',
            'match_type' => $matchType,
            'reason' => $reason,
        ];
    }

    protected function rank(?string $matchType): int
    {
        return match ($matchType) {
            'exact' => 1,
            'model', 'family' => 2,
            'connector', 'charging_standard', 'feature' => 3,
            default => 4,
        };
    }
}
