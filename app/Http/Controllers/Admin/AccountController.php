<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(): View
    {
        Gate::authorize('access-admin');

        return view('admin.account.edit');
    }

    public function update(AdminAccountRequest $request): RedirectResponse
    {
        Gate::authorize('access-admin');

        $user = $request->user();
        $payload = $request->safe()->except('password');

        if ($request->filled('password')) {
            $payload['password'] = $request->string('password')->toString();
        }

        $user->update($payload);

        return redirect()->route('admin.account.edit')->with('status', 'Account updated.');
    }
}
