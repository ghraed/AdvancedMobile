<div class="admin-grid admin-grid-2">
    <x-admin.form-field name="name" label="Name" :value="$category->name" required />

    <x-admin.form-field
        name="slug"
        label="Slug"
        :value="$category->slug"
        help="Generated automatically from the name until you override it."
    />

    <x-admin.select-dropdown
        name="parent_id"
        label="Parent Category"
        :options="$parentCategories"
        :selected="$category->parent_id"
        placeholder="Top-level category"
        help="Only valid parent categories are shown. Indentation reflects hierarchy."
    />

    <x-admin.form-field
        name="sort_order"
        label="Sort Order"
        type="number"
        min="0"
        :value="$category->sort_order ?? 0"
    />

    <x-admin.form-field
        name="description"
        label="Description"
        type="textarea"
        :value="$category->description"
        style="grid-column: 1 / -1;"
    />

    <div class="admin-field">
        <label class="admin-label" for="icon">Icon</label>
        <input id="icon" name="icon" type="file" class="admin-file-input" accept="image/*">
        @if ($category->icon)
            <div class="admin-help">Current file: {{ $category->icon }}</div>
        @endif
        @error('icon')
            <div class="admin-error-text">{{ $message }}</div>
        @enderror
    </div>

    <div class="admin-field">
        <label class="admin-label" for="image">Image</label>
        <input id="image" name="image" type="file" class="admin-file-input" accept="image/*">
        @if ($category->image)
            <div class="admin-help">Current file: {{ $category->image }}</div>
        @endif
        @error('image')
            <div class="admin-error-text">{{ $message }}</div>
        @enderror
    </div>

    <div class="admin-field" style="grid-column: 1 / -1;">
        <input type="hidden" name="is_active" value="0">
        <label style="display:flex; gap:10px; align-items:center;">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))>
            <span>Active category</span>
        </label>
        <div class="admin-help">Menu visibility is calculated automatically from category activity and available products.</div>
    </div>
</div>
