@extends('admin.layouts.app')

@section('title', 'Admin Login')
@section('heading', 'Admin Login')
@section('page_description', 'Sign in with an existing administrator account.')

@section('content')
    <div class="admin-auth-shell admin-card">
        <div class="admin-card__header">
            <div>
                <h2 class="admin-card__title">Sign in</h2>
                <p class="admin-card__copy">Use the existing authentication system with an account that has the admin role.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.login.store') }}" class="admin-form-grid" data-loading-form>
            @csrf
            <x-admin.form-field name="email" label="Email" type="email" :value="old('email', 'admin@example.com')" required />
            <x-admin.form-field name="password" label="Password" type="password" required />
            <button type="submit" class="admin-button" data-loading-label="Signing in...">Sign in</button>
        </form>
    </div>
@endsection
