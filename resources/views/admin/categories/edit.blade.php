@extends('admin.layouts.app')

@section('title', 'Edit Category')
@section('heading', 'Edit Category')
@section('page_description', 'Update the selected category details and visibility.')
@section('breadcrumbs')
    <li><a href="{{ route('admin.dashboard') }}">Admin</a></li>
    <li><a href="{{ route('admin.categories.index') }}">Categories</a></li>
    <li><span>Edit</span></li>
@endsection

@section('content')
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="admin-grid" enctype="multipart/form-data" data-loading-form>
            @csrf
            @method('PUT')
            @include('admin.categories._form')
            <div class="admin-actions">
                <button type="submit" class="admin-button" data-loading-label="Saving...">Save Category</button>
                <a href="{{ route('admin.categories.show', $category) }}" class="admin-link">Back</a>
            </div>
        </form>
    </div>
@endsection
