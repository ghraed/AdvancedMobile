@extends('admin.layouts.app')

@section('title', 'Products')
@section('heading', 'Products')
@section('page_description', 'Search, filter, and manage products with option-driven variants, stock, and publication controls.')
@section('breadcrumbs')
    <li><a href="{{ route('admin.dashboard') }}">Admin</a></li>
    <li><span>Products</span></li>
@endsection

@section('content')
    <div class="admin-card">
        <div class="admin-card__header">
            <div>
                <h2 class="admin-card__title">Manage products</h2>
                <p class="admin-card__copy">Review category placement, variant counts, stock coverage, price ranges, and public visibility from one table.</p>
            </div>
            <a href="{{ route('admin.products.create') }}" class="admin-button">New Product</a>
        </div>

        <form method="GET" action="{{ route('admin.products.index') }}" class="admin-card admin-card--tight" style="margin-bottom:18px;">
            <x-admin.filter-controls>
                <x-admin.search-field name="search" label="Search products" :value="$filters['search']" placeholder="Search by product name, SKU, or brand" />
                <x-admin.select-dropdown
                    name="category_id"
                    label="Category"
                    :options="collect($categories)->mapWithKeys(fn ($category) => [$category->id => $category->name.($category->is_active ? '' : ' [Inactive]')])->all()"
                    :selected="$filters['category_id']"
                    placeholder="All categories"
                />
                <x-admin.select-dropdown
                    name="status"
                    label="Status"
                    :options="['all' => 'All', 'active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived']"
                    :selected="$filters['status']"
                    placeholder="All statuses"
                />
                <x-admin.select-dropdown
                    name="stock"
                    label="Stock"
                    :options="['all' => 'All', 'in_stock' => 'In stock', 'low_stock' => 'Low stock', 'out_of_stock' => 'Out of stock']"
                    :selected="$filters['stock']"
                    placeholder="All stock"
                />
                <x-admin.select-dropdown
                    name="installments"
                    label="Installments"
                    :options="['all' => 'All', 'with' => 'With plans', 'without' => 'Without plans']"
                    :selected="$filters['installments']"
                    placeholder="Any plan state"
                />
                <x-admin.select-dropdown
                    name="sort"
                    label="Sort"
                    :options="$sortOptions"
                    :selected="$filters['sort']"
                    placeholder="Sort order"
                />
                <x-admin.select-dropdown
                    name="direction"
                    label="Direction"
                    :options="['desc' => 'Descending', 'asc' => 'Ascending']"
                    :selected="$filters['direction']"
                    placeholder="Direction"
                />
                <div class="admin-actions">
                    <button type="submit" class="admin-button admin-button--secondary">Apply Filters</button>
                    <a href="{{ route('admin.products.index') }}" class="admin-link-button">Reset</a>
                </div>
            </x-admin.filter-controls>
        </form>

        @if ($products->isEmpty())
            <x-admin.empty-state title="No products found" message="Adjust your filters or create a new product to start building the catalog.">
                <a href="{{ route('admin.products.create') }}" class="admin-button">Create Product</a>
            </x-admin.empty-state>
        @else
            <x-admin.data-table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Variants</th>
                        <th>Price Range</th>
                        <th>Total Stock</th>
                        <th>Status</th>
                        <th>Visibility</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        @php
                            $primaryImage = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
                            $isPublic = $product->status === \App\Enums\ProductStatus::Active
                                && $product->category?->is_active
                                && $product->has_available_variants;
                            $priceRange = $product->min_variant_price === null
                                ? 'No pricing'
                                : ($product->min_variant_price == $product->max_variant_price
                                    ? number_format((float) $product->min_variant_price, 2)
                                    : number_format((float) $product->min_variant_price, 2).' - '.number_format((float) $product->max_variant_price, 2));
                            $stock = (int) ($product->total_stock ?? 0);
                        @endphp
                        <tr>
                            <td>
                                <div class="admin-inline" style="align-items:flex-start;">
                                    @if ($primaryImage)
                                        <img src="{{ asset('storage/'.$primaryImage->image_path) }}" alt="{{ $product->name }}" style="width:56px; height:56px; border-radius:16px; object-fit:cover; border:1px solid var(--admin-border);">
                                    @else
                                        <div style="width:56px; height:56px; border-radius:16px; display:grid; place-items:center; border:1px dashed var(--admin-border-strong); background:var(--admin-surface-muted); color:var(--admin-muted); font-size:12px;">No image</div>
                                    @endif
                                    <div>
                                        <div style="font-weight:700;">{{ $product->name }}</div>
                                        <div class="admin-help">{{ $product->brand ?: 'No brand' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $product->category?->name ?? 'Unassigned' }}</td>
                            <td>
                                <div style="font-weight:700;">{{ $product->variants_count }}</div>
                                <div class="admin-help">{{ $product->installment_plans_count }} plan(s)</div>
                            </td>
                            <td>{{ $priceRange }}</td>
                            <td>
                                <div style="font-weight:700;">{{ $stock }}</div>
                                <div class="admin-help">
                                    @if ($stock === 0)
                                        Out of stock
                                    @elseif ($stock <= 5)
                                        Low stock
                                    @else
                                        In stock
                                    @endif
                                </div>
                            </td>
                            <td>
                                <x-admin.status-badge
                                    :status="$product->status->value === 'active' ? 'success' : ($product->status->value === 'archived' ? 'warning' : 'neutral')"
                                    :label="str($product->status->value)->headline()"
                                />
                            </td>
                            <td>
                                <div class="admin-inline">
                                    <x-admin.status-badge :status="$isPublic ? 'success' : 'warning'" :label="$isPublic ? 'Public' : 'Hidden'" />
                                    <x-admin.status-badge :status="$product->category?->is_active ? 'success' : 'neutral'" :label="$product->category?->is_active ? 'Menu eligible' : 'Category inactive'" />
                                </div>
                            </td>
                            <td>
                                <div class="admin-actions" style="justify-content:flex-end;">
                                    <a href="{{ $isPublic ? route('products.show', $product) : route('admin.products.preview', $product) }}" target="_blank" rel="noreferrer" class="admin-link-button">View</a>
                                    <a href="{{ route('admin.products.edit', $product) }}" class="admin-link-button">Edit</a>
                                    <form method="POST" action="{{ route('admin.products.duplicate', $product) }}" data-loading-form>
                                        @csrf
                                        <button type="submit" class="admin-link-button" data-loading-label="Duplicating...">Duplicate</button>
                                    </form>
                                    @if ($product->status === \App\Enums\ProductStatus::Active)
                                        <form method="POST" action="{{ route('admin.products.deactivate', $product) }}" data-loading-form>
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="admin-link-button" data-loading-label="Deactivating...">Deactivate</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.products.activate', $product) }}" data-loading-form>
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="admin-link-button" data-loading-label="Activating...">Activate</button>
                                        </form>
                                    @endif
                                    <button type="button" class="admin-button admin-button--danger" data-confirm-trigger="delete-product-{{ $product->id }}">Delete</button>
                                </div>
                                <x-admin.modal-dialog id="delete-product-{{ $product->id }}" title="Delete product?" message="Products with remaining stock cannot be deleted. Set stock to zero or retire stocked variants before trying again.">
                                    <form method="POST" action="{{ route('admin.products.destroy', $product) }}" data-loading-form>
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="admin-button admin-button--danger" data-loading-label="Deleting...">Delete Product</button>
                                    </form>
                                </x-admin.modal-dialog>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-admin.data-table>

            <x-admin.pagination :paginator="$products" />
        @endif
    </div>
@endsection
