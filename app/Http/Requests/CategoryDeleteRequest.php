<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CategoryDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category
            ? $this->user()?->can('delete', $category) ?? false
            : false;
    }

    public function rules(): array
    {
        return [
            'reassign_products_to' => ['nullable', 'integer', Rule::exists('categories', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Category|null $category */
            $category = $this->route('category');

            if (! $category || ! $this->filled('reassign_products_to')) {
                return;
            }

            $targetId = (int) $this->input('reassign_products_to');

            if ($targetId === $category->id) {
                $validator->errors()->add('reassign_products_to', 'Products must be moved to a different category.');

                return;
            }

            if (in_array($targetId, $category->descendantIds(), true)) {
                $validator->errors()->add('reassign_products_to', 'Products cannot be moved into a descendant of the category being deleted.');
            }
        });
    }
}
