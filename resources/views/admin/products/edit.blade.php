@extends('admin.layouts.app')

@section('title', 'Edit Product')
@section('heading', 'Edit Product')
@section('page_description', 'Update product details, stock variants, and associated installment plans.')
@section('breadcrumbs')
    <li><a href="{{ route('admin.dashboard') }}">Admin</a></li>
    <li><a href="{{ route('admin.products.index') }}">Products</a></li>
    <li><span>Edit</span></li>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.products.update', $product) }}" class="admin-grid" enctype="multipart/form-data" data-loading-form>
        @csrf
        @method('PUT')
        @php($submitLabel = 'Save Product')
        @include('admin.products._form')
    </form>
@endsection
