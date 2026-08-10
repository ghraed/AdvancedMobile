@extends('admin.layouts.app')

@section('title', 'Admin Dashboard')
@section('heading', 'Dashboard')
@section('page_description', 'A high-level view of catalog health, inventory risk, and recent product activity.')
@section('breadcrumbs')
    <li><span>Admin</span></li>
    <li><span>Dashboard</span></li>
@endsection

@section('content')
    <div class="admin-grid admin-grid-4" style="margin-bottom:24px;">
        <div class="admin-card admin-kpi">
            <span class="admin-kpi__label">Total Categories</span>
            <span class="admin-kpi__value">{{ $stats['categories'] }}</span>
            <span class="admin-kpi__meta">{{ $stats['active_categories'] }} active</span>
        </div>
        <div class="admin-card admin-kpi">
            <span class="admin-kpi__label">Total Products</span>
            <span class="admin-kpi__value">{{ $stats['products'] }}</span>
            <span class="admin-kpi__meta">{{ $stats['active_products'] }} active</span>
        </div>
        <div class="admin-card admin-kpi">
            <span class="admin-kpi__label">Total Variants</span>
            <span class="admin-kpi__value">{{ $stats['variants'] }}</span>
            <span class="admin-kpi__meta">{{ $stats['low_stock_variants'] }} low stock</span>
        </div>
        <div class="admin-card admin-kpi">
            <span class="admin-kpi__label">Installment Plans</span>
            <span class="admin-kpi__value">{{ $installmentPlanCount }}</span>
            <span class="admin-kpi__meta">{{ $stats['products_without_installment_plans'] }} products without plans</span>
        </div>
        <div class="admin-card admin-kpi">
            <span class="admin-kpi__label">Applications</span>
            <span class="admin-kpi__value">{{ $installmentApplicationCount }}</span>
            <span class="admin-kpi__meta">{{ $submittedInstallmentApplicationCount }} awaiting review</span>
        </div>
    </div>

    <div class="admin-grid admin-grid-2">
        <div class="admin-card">
            <div class="admin-card__header">
                <div>
                    <h2 class="admin-card__title">Catalog shortcuts</h2>
                    <p class="admin-card__copy">Jump into the most common admin tasks.</p>
                </div>
            </div>

            <div class="admin-actions">
                <a class="admin-button" href="{{ route('admin.categories.create') }}">New Category</a>
                <a class="admin-button admin-button--secondary" href="{{ route('admin.products.create') }}">New Product</a>
                <a class="admin-link-button" href="{{ route('admin.installment-plans.index') }}">Review Installment Plans</a>
                <a class="admin-link-button" href="{{ route('admin.installment-applications.index') }}">Review Applications</a>
            </div>

            <div class="admin-grid admin-grid-2" style="margin-top:20px;">
                <div class="admin-card admin-card--tight admin-kpi">
                    <span class="admin-kpi__label">Low-stock variants</span>
                    <span class="admin-kpi__value">{{ $stats['low_stock_variants'] }}</span>
                    <x-admin.status-badge :status="$stats['low_stock_variants'] > 0 ? 'warning' : 'success'" :label="$stats['low_stock_variants'] > 0 ? 'Needs attention' : 'Healthy'" />
                </div>
                <div class="admin-card admin-card--tight admin-kpi">
                    <span class="admin-kpi__label">Out-of-stock variants</span>
                    <span class="admin-kpi__value">{{ $stats['out_of_stock_variants'] }}</span>
                    <x-admin.status-badge :status="$stats['out_of_stock_variants'] > 0 ? 'danger' : 'success'" :label="$stats['out_of_stock_variants'] > 0 ? 'Action required' : 'Clear'" />
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card__header">
                <div>
                    <h2 class="admin-card__title">Recently created products</h2>
                    <p class="admin-card__copy">Latest catalog entries added by administrators.</p>
                </div>
            </div>

            @if ($latestProducts->isEmpty())
                <x-admin.empty-state title="No products yet" message="Create the first product to populate the dashboard activity list." />
            @else
                <x-admin.data-table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($latestProducts as $product)
                            <tr>
                                <td><a href="{{ route('admin.products.edit', $product) }}" style="font-weight:700; text-decoration:none;">{{ $product->name }}</a></td>
                                <td>{{ $product->category?->name ?? 'Unassigned' }}</td>
                                <td>
                                    <x-admin.status-badge
                                        :status="$product->status->value === 'active' ? 'success' : ($product->status->value === 'archived' ? 'warning' : 'neutral')"
                                        :label="str($product->status->value)->headline()"
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-admin.data-table>
            @endif
        </div>
    </div>
@endsection
