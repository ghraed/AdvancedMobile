<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfitAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'range' => ['nullable', Rule::in(['today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month', 'this_year', 'custom'])],
            'date_from' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'brand' => ['nullable', 'string', 'max:255'],
            'sales_channel' => ['nullable', Rule::in(['online', 'pos'])],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'cashier_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'sort' => ['nullable', Rule::in(['highest_revenue', 'highest_profit', 'highest_margin', 'lowest_margin', 'most_units', 'lowest_profit'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'range' => $this->input('range', config('analytics.default_range', 'last_30_days')),
            'sort' => $this->input('sort', 'highest_profit'),
        ]);
    }
}
