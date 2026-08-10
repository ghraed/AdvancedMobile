<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function login(): View { return view('auth.customer-login'); }
    public function register(): View { return view('auth.customer-register'); }

    public function authenticate(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($data, true)) return back()->withErrors(['email' => 'The provided credentials are invalid.'])->onlyInput('email');
        $request->session()->regenerate();
        return redirect()->route('checkout.show');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::defaults()]]);
        $user = User::create($data + ['role' => User::ROLE_CUSTOMER]);
        Auth::login($user, true);
        $request->session()->regenerate();
        return redirect()->route('checkout.show');
    }
}
