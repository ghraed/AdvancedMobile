@extends('admin.layouts.app')

@section('title', 'Create Product')
@section('heading', 'Create Product')
@section('page_description', 'Create a product with variants, option values, and installment plan data.')
@section('breadcrumbs')
    <li><a href="{{ route('admin.dashboard') }}">Admin</a></li>
    <li><a href="{{ route('admin.products.index') }}">Products</a></li>
    <li><span>Create</span></li>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.products.store') }}" class="admin-grid" enctype="multipart/form-data" data-loading-form>
        @csrf
        @php($submitLabel = 'Create Product')
        @include('admin.products._form')
    </form>
@endsection
