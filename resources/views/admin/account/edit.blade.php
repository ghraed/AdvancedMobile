@extends('admin.layouts.app')

@section('title', 'Profile')
@section('heading', 'Profile')
@section('page_description', 'Update the authenticated administrator account details and password.')
@section('breadcrumbs')
    <li><a href="{{ route('admin.dashboard') }}">Admin</a></li>
    <li><span>Profile</span></li>
@endsection

@section('content')
    <div class="admin-card">
        <div class="admin-card__header">
            <div>
                <h2 class="admin-card__title">Account settings</h2>
                <p class="admin-card__copy">Keep your admin profile information current and rotate the password when needed.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.account.update') }}" class="admin-form-grid" data-loading-form>
            @csrf
            @method('PUT')

            <div class="admin-form-grid admin-form-grid-2">
                <x-admin.form-field name="name" label="Name" :value="auth()->user()->name" required />
                <x-admin.form-field name="email" label="Email" type="email" :value="auth()->user()->email" required />
                <x-admin.form-field name="password" label="New Password" type="password" help="Leave blank to keep the current password." />
                <x-admin.form-field name="password_confirmation" label="Confirm Password" type="password" />
            </div>

            <div class="admin-actions">
                <button type="submit" class="admin-button" data-loading-label="Saving...">Save Changes</button>
            </div>
        </form>
    </div>
@endsection
