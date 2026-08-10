<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class CategoryReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reorder', Category::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'sort_orders' => ['required', 'array'],
            'sort_orders.*' => ['required', 'integer', 'min:0'],
        ];
    }
}
