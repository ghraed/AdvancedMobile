<?php

namespace App\Http\Requests;

use App\Enums\DeviceCheckStatus;
use App\Enums\DeviceConditionGrade;
use App\Enums\DeviceConditionType;
use App\Enums\DeviceUnitStatus;
use App\Models\DeviceUnit;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceUnitRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdmin() ?? false; }

    public function rules(): array
    {
        $unit = $this->route('device_unit');
        return [
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'condition_type' => ['required', Rule::enum(DeviceConditionType::class), Rule::notIn([DeviceConditionType::New->value])],
            'condition_grade' => ['required', Rule::enum(DeviceConditionGrade::class)],
            'imei' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($unit) {
                if (! DeviceUnit::isValidImei((string) $value)) $fail('The IMEI must be a valid 15-digit IMEI.');
                $duplicate = DeviceUnit::query()->where('imei_hash', DeviceUnit::imeiHash((string) $value))
                    ->when($unit, fn ($query) => $query->whereKeyNot($unit->id))->exists();
                if ($duplicate) $fail('A device with this IMEI already exists.');
            }],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'battery_health_percent' => ['nullable', 'integer', 'between:0,100'],
            'cosmetic_condition' => ['nullable', 'string', 'max:3000'],
            'customer_visible_condition_notes' => ['nullable', 'string', 'max:5000'],
            'known_defects' => ['nullable', 'array'],
            'known_defects.*' => ['required', 'string', 'max:500'],
            'condition_checklist' => ['nullable', 'array:'.implode(',', DeviceUnit::CHECKLIST_KEYS)],
            'condition_checklist.*' => [Rule::enum(DeviceCheckStatus::class)],
            'included_accessories' => ['nullable', 'array'],
            'included_accessories.*' => ['required', Rule::in(DeviceUnit::ACCESSORY_KEYS)],
            'refurbished_at' => ['nullable', 'date'],
            'refurbished_by' => ['nullable', 'string', 'max:255'],
            'parts_replaced' => ['nullable', 'array'],
            'parts_replaced.*' => ['required', 'string', 'max:255'],
            'customer_visible_refurbishment_details' => ['nullable', 'string', 'max:5000'],
            'refurbishment_notes' => ['nullable', 'string', 'max:10000'],
            'warranty_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'warranty_until' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'decimal:0,2', 'min:0'],
            'selling_price_override' => ['nullable', 'decimal:0,2', 'min:0'],
            'installments_enabled' => ['nullable', 'boolean'],
            'status' => ['required', Rule::enum(DeviceUnitStatus::class)],
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['image', 'max:8192'],
            'image_view_types' => ['nullable', 'array'],
            'image_view_types.*' => ['nullable', 'in:front,back,left_side,right_side,screen,defect,other'],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer', 'exists:device_unit_images,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();
        $payload['installments_enabled'] = $this->boolean('installments_enabled');
        foreach (['known_defects', 'parts_replaced'] as $field) {
            if (is_string($payload[$field] ?? null)) {
                $payload[$field] = collect(preg_split('/\r\n|\r|\n/', $payload[$field]))->map(fn ($v) => trim($v))->filter()->values()->all();
            }
        }
        $payload['included_accessories'] = collect($payload['included_accessories'] ?? [])
            ->map(fn ($value, $key) => is_int($key) ? $value : ($value ? $key : null))->filter()->values()->all();
        $this->replace($payload);
    }
}
