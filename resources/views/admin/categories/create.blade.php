@extends('admin.layouts.app')

@section('title', 'Create Category')
@section('heading', 'Create Category')
@section('page_description', 'Add a new category to organize products and navigation.')
@section('breadcrumbs')
    <li><a href="{{ route('admin.dashboard') }}">Admin</a></li>
    <li><a href="{{ route('admin.categories.index') }}">Categories</a></li>
    <li><span>Create</span></li>
@endsection

@section('content')
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.categories.store') }}" class="admin-grid" enctype="multipart/form-data" data-loading-form>
            @csrf
            @include('admin.categories._form')
            <div class="admin-actions">
                <button type="submit" class="admin-button" data-loading-label="Creating...">Create Category</button>
                <a href="{{ route('admin.categories.index') }}" class="admin-link">Back</a>
            </div>
        </form>
    </div>
@endsection
