@extends('admin.layouts.app')

@section('title', 'Categories')
@section('heading', 'Categories')
@section('page_description', 'Manage hierarchy, visibility, and ordering for catalog categories.')
@section('breadcrumbs')
    <li><a href="{{ route('admin.dashboard') }}">Admin</a></li>
    <li><span>Categories</span></li>
@endsection

@section('content')
    <div class="admin-grid">
        <div class="admin-card">
            <div class="admin-card__header">
                <div>
                    <h2 class="admin-card__title">Manage categories</h2>
                    <p class="admin-card__copy">Search, filter, reorder, and audit menu visibility across the full hierarchy.</p>
                </div>
                <a href="{{ route('admin.categories.create') }}" class="admin-button">New Category</a>
            </div>

            <form method="GET" action="{{ route('admin.categories.index') }}" class="admin-card admin-card--tight" style="margin-bottom:18px;">
                <x-admin.filter-controls>
                    <x-admin.search-field name="search" label="Search categories" :value="$filters['search']" placeholder="Search by category name" />
                    <x-admin.select-dropdown
                        name="status"
                        label="Active status"
                        :options="['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive']"
                        :selected="$filters['status']"
                        placeholder="Any status"
                    />
                    <x-admin.select-dropdown
                        name="structure"
                        label="Structure"
                        :options="['all' => 'All', 'parent' => 'Parent categories', 'subcategory' => 'Subcategories']"
                        :selected="$filters['structure']"
                        placeholder="Any structure"
                    />
                    <x-admin.select-dropdown
                        name="sort"
                        label="Sort by"
                        :options="$sortOptions"
                        :selected="$filters['sort']"
                        placeholder="Choose sort"
                    />
                    <x-admin.select-dropdown
                        name="direction"
                        label="Direction"
                        :options="['asc' => 'Ascending', 'desc' => 'Descending']"
                        :selected="$filters['direction']"
                        placeholder="Choose direction"
                    />
                    <div class="admin-actions">
                        <button type="submit" class="admin-button admin-button--secondary">Apply Filters</button>
                        <a href="{{ route('admin.categories.index') }}" class="admin-link-button">Reset</a>
                    </div>
                </x-admin.filter-controls>
            </form>

            @if ($categories->isEmpty())
                <x-admin.empty-state title="No categories found" message="Adjust your filters or create a category to start organizing the catalog.">
                    <a href="{{ route('admin.categories.create') }}" class="admin-button">Create Category</a>
                </x-admin.empty-state>
            @else
                <form method="POST" action="{{ route('admin.categories.reorder', request()->query()) }}" data-loading-form>
                    @csrf
                    <div class="admin-actions" style="justify-content:flex-end; margin-bottom:12px;">
                        <button type="submit" class="admin-button admin-button--secondary" data-loading-label="Saving order...">Save Order</button>
                    </div>

                    <x-admin.data-table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Parent</th>
                                <th>Products</th>
                                <th>Active Products</th>
                                <th>Menu Visibility</th>
                                <th>Status</th>
                                <th>Sort Order</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                @php
                                    $indent = max(0, (int) ($category->depth ?? 0)) * 22;
                                    $isChild = (int) ($category->depth ?? 0) > 0;
                                    $visibility = $category->menu_visibility_status;
                                    $visibilityStyle = match ($visibility) {
                                        'visible' => 'success',
                                        'inactive' => 'neutral',
                                        default => 'warning',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <div style="padding-left: {{ $indent }}px;">
                                            <div style="font-weight:700; display:flex; align-items:center; gap:8px;">
                                                @if ($isChild)
                                                    <span aria-hidden="true" style="color:#98a2b3;">↳</span>
                                                @endif
                                                <a href="{{ route('admin.categories.show', $category) }}" style="text-decoration:none;">{{ $category->name }}</a>
                                            </div>
                                            <div class="admin-help">{{ $category->slug }}</div>
                                        </div>
                                    </td>
                                    <td>{{ $category->parent?->name ?? 'Top level' }}</td>
                                    <td>{{ $category->products_count }}</td>
                                    <td>{{ $category->active_products_count }}</td>
                                    <td>
                                        <x-admin.status-badge :status="$visibilityStyle" :label="$visibilityLabels[$visibility] ?? 'Unknown'" />
                                    </td>
                                    <td>
                                        <x-admin.status-badge :status="$category->is_active ? 'success' : 'neutral'" :label="$category->is_active ? 'Active' : 'Inactive'" />
                                    </td>
                                    <td style="width: 120px;">
                                        <input
                                            type="number"
                                            min="0"
                                            name="sort_orders[{{ $category->id }}]"
                                            value="{{ old('sort_orders.'.$category->id, $category->sort_order) }}"
                                            class="admin-input"
                                        >
                                    </td>
                                    <td>
                                        <div class="admin-actions" style="justify-content:flex-end;">
                                            <a href="{{ route('admin.categories.show', $category) }}" class="admin-link-button">View</a>
                                            <a href="{{ route('admin.categories.edit', $category) }}" class="admin-link-button">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-admin.data-table>
                </form>

                <x-admin.pagination :paginator="$categories" />
            @endif
        </div>
    </div>
@endsection
