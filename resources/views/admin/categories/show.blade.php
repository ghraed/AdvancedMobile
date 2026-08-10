@extends('admin.layouts.app')

@section('title', $category->name)
@section('heading', $category->name)
@section('page_description', 'Review category hierarchy, menu visibility, and deletion constraints.')
@section('breadcrumbs')
    <li><a href="{{ route('admin.dashboard') }}">Admin</a></li>
    <li><a href="{{ route('admin.categories.index') }}">Categories</a></li>
    <li><span>{{ $category->name }}</span></li>
@endsection

@section('content')
    <div class="admin-grid">
        <div class="admin-card">
            <div class="admin-card__header">
                <div>
                    <h2 class="admin-card__title">{{ $category->name }}</h2>
                    <p class="admin-card__copy">{{ $category->description ?: 'No description added for this category.' }}</p>
                </div>
                <div class="admin-actions">
                    <a href="{{ route('admin.categories.edit', $category) }}" class="admin-link-button">Edit</a>
                    @if ($category->is_active)
                        <form method="POST" action="{{ route('admin.categories.deactivate', $category) }}" data-loading-form>
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="admin-button admin-button--secondary" data-loading-label="Deactivating...">Deactivate</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.categories.activate', $category) }}" data-loading-form>
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="admin-button" data-loading-label="Activating...">Activate</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="admin-grid admin-grid-4">
                <div class="admin-card admin-card--tight admin-kpi">
                    <span class="admin-kpi__label">Products</span>
                    <strong class="admin-kpi__value">{{ $category->products_count }}</strong>
                    <span class="admin-kpi__meta">Directly assigned products</span>
                </div>
                <div class="admin-card admin-card--tight admin-kpi">
                    <span class="admin-kpi__label">Active Products</span>
                    <strong class="admin-kpi__value">{{ $category->active_products_count }}</strong>
                    <span class="admin-kpi__meta">Active status only</span>
                </div>
                <div class="admin-card admin-card--tight admin-kpi">
                    <span class="admin-kpi__label">Children</span>
                    <strong class="admin-kpi__value">{{ $category->children_count }}</strong>
                    <span class="admin-kpi__meta">Direct subcategories</span>
                </div>
                <div class="admin-card admin-card--tight admin-kpi">
                    <span class="admin-kpi__label">Menu Visibility</span>
                    <strong class="admin-kpi__value" style="font-size: 18px;">{{ $visibilityLabels[$visibilityStatus] ?? 'Unknown' }}</strong>
                    <span class="admin-kpi__meta">Calculated automatically</span>
                </div>
            </div>
        </div>

        <div class="admin-grid admin-grid-2">
            <div class="admin-card">
                <h3 class="admin-card__title">Details</h3>
                <div class="admin-grid" style="margin-top:16px;">
                    <div><strong>Slug:</strong> {{ $category->slug }}</div>
                    <div><strong>Parent:</strong> {{ $category->parent?->name ?? 'Top level' }}</div>
                    <div><strong>Sort order:</strong> {{ $category->sort_order }}</div>
                    <div><strong>Status:</strong> {{ $category->is_active ? 'Active' : 'Inactive' }}</div>
                    <div><strong>Icon:</strong> {{ $category->icon ?: 'None uploaded' }}</div>
                    <div><strong>Image:</strong> {{ $category->image ?: 'None uploaded' }}</div>
                </div>
            </div>

            <div class="admin-card">
                <h3 class="admin-card__title">Child Categories</h3>
                @if ($category->children->isEmpty())
                    <p class="admin-card__copy" style="margin-top:16px;">No subcategories yet.</p>
                @else
                    <div class="admin-grid" style="margin-top:16px;">
                        @foreach ($category->children as $child)
                            @php
                                $childVisibility = $childVisibilityMap[$child->id] ?? 'empty';
                            @endphp
                            <div class="admin-row-card">
                                <div style="display:flex; justify-content:space-between; gap:12px;">
                                    <div>
                                        <div style="font-weight:700;">{{ $child->name }}</div>
                                        <div class="admin-help">{{ $child->products_count ?? 0 }} direct products</div>
                                    </div>
                                    <x-admin.status-badge
                                        :status="$childVisibility === 'visible' ? 'success' : ($childVisibility === 'inactive' ? 'neutral' : 'warning')"
                                        :label="$visibilityLabels[$childVisibility] ?? 'Unknown'"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card__header">
                <div>
                    <h3 class="admin-card__title">Safe Deletion</h3>
                    <p class="admin-card__copy">Categories with child categories cannot be deleted. Categories with products require reassignment first.</p>
                </div>
                <button type="button" class="admin-button admin-button--danger" data-confirm-trigger="delete-category-{{ $category->id }}">Delete Category</button>
            </div>
        </div>

        <x-admin.modal-dialog id="delete-category-{{ $category->id }}" title="Delete category?" message="This action is permanent. Products must be reassigned before deletion, and child categories must be removed or moved first.">
            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="admin-grid" data-loading-form>
                @csrf
                @method('DELETE')
                <x-admin.select-dropdown
                    name="reassign_products_to"
                    label="Move products to"
                    :options="$reassignmentOptions"
                    :selected="old('reassign_products_to')"
                    placeholder="Select a destination category"
                    help="Leave blank only when this category has no products."
                />
                <button type="submit" class="admin-button admin-button--danger" data-loading-label="Deleting...">Delete Category</button>
            </form>
        </x-admin.modal-dialog>
    </div>
@endsection
