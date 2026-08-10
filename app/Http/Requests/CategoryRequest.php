<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category
            ? $this->user()?->can('update', $category) ?? false
            : $this->user()?->can('create', Category::class) ?? false;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($categoryId)],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'file', 'image', 'max:2048'],
            'image' => ['nullable', 'file', 'image', 'max:4096'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = trim((string) $this->input('slug'));

        $this->merge([
            'slug' => $slug !== '' ? Str::slug($slug) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Category|null $category */
            $category = $this->route('category');
            $parentId = $this->filled('parent_id') ? (int) $this->input('parent_id') : null;

            if ($category && $parentId === $category->id) {
                $validator->errors()->add('parent_id', 'A category cannot be its own parent.');
            }

            if ($category && $parentId !== null && in_array($parentId, $category->descendantIds(), true)) {
                $validator->errors()->add('parent_id', 'A category cannot be assigned to one of its descendants.');
            }
        });
    }
}
