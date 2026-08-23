<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PosCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessPos() ?? false;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'min:16', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            // Browser prices may be sent for display compatibility, but the service never reads them.
            'items.*.unit_price_cents' => ['nullable', 'integer', 'min:0'],
            'discount.type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'discount.value' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1', 'max:2'],
            'payments.*.method' => ['required', Rule::in(['cash', 'card'])],
            'payments.*.amount_cents' => ['required', 'integer', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
            'payments.*.cash_received_cents' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $discount = $this->input('discount', []);
            $type = $discount['type'] ?? null;
            $value = $discount['value'] ?? null;

            if ($type !== null && $value === null) {
                $validator->errors()->add('discount.value', 'A discount value is required.');
            }

            if ($type === 'fixed' && $value !== null && filter_var($value, FILTER_VALIDATE_INT) === false) {
                $validator->errors()->add('discount.value', 'A fixed discount must be supplied in whole cents.');
            }

            if ($type === 'percentage' && $value !== null && (float) $value > 100) {
                $validator->errors()->add('discount.value', 'A percentage discount cannot exceed 100%.');
            }

            $methods = collect($this->input('payments', []))->pluck('method')->filter();
            if ($methods->duplicates()->isNotEmpty()) {
                $validator->errors()->add('payments', 'Use at most one cash and one card payment.');
            }

            foreach ($this->input('payments', []) as $index => $payment) {
                if (($payment['method'] ?? null) !== 'cash' && array_key_exists('cash_received_cents', $payment)) {
                    $validator->errors()->add("payments.{$index}.cash_received_cents", 'Cash received is only valid for cash payments.');
                }
            }
        });
    }
}
