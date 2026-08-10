@extends('admin.layouts.app')

@section('title', 'Installment Plans')
@section('heading', 'Installment Plans')
@section('page_description', 'Identify products that are missing plan coverage and review existing financing options.')
@section('breadcrumbs')
    <li><a href="{{ route('admin.dashboard') }}">Admin</a></li>
    <li><span>Installment Plans</span></li>
@endsection

@section('content')
    <div class="admin-card">
        <div class="admin-card__header">
            <div>
                <h2 class="admin-card__title">Products and plan coverage</h2>
                <p class="admin-card__copy">Use this view to find products that still need installment plan configuration.</p>
            </div>
            <a href="{{ route('admin.products.index') }}" class="admin-link-button">Manage Products</a>
        </div>

        <form method="GET" action="{{ route('admin.installment-plans.index') }}" class="admin-card admin-card--tight" style="margin-bottom:18px;">
            <x-admin.filter-controls>
                <x-admin.search-field name="search" label="Search products" :value="$filters['search']" placeholder="Search by name, brand, or slug" />
                <x-admin.select-dropdown
                    name="status"
                    label="Coverage"
                    :options="['all' => 'All', 'with-plans' => 'With plans', 'without-plans' => 'Without plans']"
                    :selected="$filters['status']"
                    placeholder="Choose coverage"
                />
                <div class="admin-actions">
                    <button type="submit" class="admin-button admin-button--secondary">Apply Filters</button>
                    <a href="{{ route('admin.installment-plans.index') }}" class="admin-link-button">Reset</a>
                </div>
            </x-admin.filter-controls>
        </form>

        @if ($products->isEmpty())
            <x-admin.empty-state title="No matching products" message="No products match the current installment plan filters." />
        @else
            <x-admin.data-table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Plans</th>
                        <th>Summary</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr>
                            <td>
                                <div style="font-weight:700;">{{ $product->name }}</div>
                                <div class="admin-help">{{ $product->brand ?: 'No brand' }}</div>
                            </td>
                            <td>{{ $product->category?->name ?? 'Unassigned' }}</td>
                            <td>{{ $product->installment_plans_count }}</td>
                            <td>
                                @if ($product->installmentPlans->isNotEmpty())
                                    <div class="admin-inline">
                                        @foreach ($product->installmentPlans->take(3) as $plan)
                                            <x-admin.status-badge status="neutral" :label="$plan->months.' months'" />
                                        @endforeach
                                    </div>
                                @else
                                    <x-admin.status-badge status="warning" label="No plans configured" />
                                @endif
                            </td>
                            <td><a href="{{ route('admin.products.edit', $product) }}" class="admin-link-button">Open Product</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-admin.data-table>

            <x-admin.pagination :paginator="$products" />
        @endif
    </div>
@endsection
