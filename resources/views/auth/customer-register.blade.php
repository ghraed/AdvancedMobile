@extends('layouts.elite-mobile-marketplace')

@section('title', 'Create account')

@section('content')
<x-public.public-header />
<x-public.search-overlay />
<main class="mx-auto max-w-lg px-4 py-12 sm:py-16"><section class="pm-card rounded-[26px] p-6 sm:p-8"><a href="{{ url('/') }}" class="pm-brand mb-7"><span class="pm-brand-mark">T</span><span>Taqqsit</span></a><p class="text-xs font-extrabold uppercase tracking-[.14em] text-[var(--pm-primary)]">Get started</p><h1 class="mt-2 text-3xl font-black tracking-[-.04em]">Create your account</h1><p class="mt-2 text-sm leading-6 text-[var(--pm-text-muted)]">Your selected product and payment calendar will be waiting for you.</p>
<form method="POST" action="{{ route('customer.register.store') }}" class="mt-6 space-y-4">@csrf
<div><label class="text-sm font-bold">Name</label><input name="name" value="{{ old('name') }}" required class="pm-form-control mt-2">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
<div><label class="text-sm font-bold">Email</label><input name="email" type="email" value="{{ old('email') }}" required class="pm-form-control mt-2">@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
<div><label class="text-sm font-bold">Password</label><input name="password" type="password" required class="pm-form-control mt-2">@error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
<div><label class="text-sm font-bold">Confirm password</label><input name="password_confirmation" type="password" required class="pm-form-control mt-2"></div>
<button class="pm-button pm-button--accent w-full justify-center" type="submit">Create account</button></form><p class="mt-5 text-sm">Already registered? <a class="font-bold text-[var(--pm-secondary)]" href="{{ route('customer.login') }}">Sign in</a></p>
</section></main>
@endsection
