<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials, true)) {
            return back()->withErrors([
                'email' => 'The provided credentials are invalid.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        if (! $request->user()?->isAdmin()) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'This account does not have admin access.',
            ]);
        }

        return redirect()->route('admin.dashboard');
    }

    public function destroy(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
