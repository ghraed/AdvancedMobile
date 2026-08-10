<?php

namespace App\Http\Controllers;

use App\Services\PendingPurchaseService;
use App\Services\OrderCreationService;
use App\Models\Order;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(protected PendingPurchaseService $pendingPurchases, protected OrderCreationService $orders) {}

    /** Checkout boundary: payment providers will receive this revalidated server state, never browser prices. */
    public function show(Request $request): View|RedirectResponse
    {
        $token = $request->session()->get(PendingPurchaseService::SESSION_KEY);
        if (! $token) return redirect()->route('catalog.index')->with('status', 'There is no saved purchase to continue.');
        try {
            $checkout = $this->pendingPurchases->reopen($token);
            if ($checkout['pending']->user_id !== null && $checkout['pending']->user_id !== $request->user()->id) {
                throw new DomainException('This saved purchase belongs to another customer.');
            }
            $checkout['pending']->update(['user_id' => $request->user()->id]);
            return view('checkout.show', $checkout);
        } catch (DomainException $exception) {
            $request->session()->forget(PendingPurchaseService::SESSION_KEY);
            return redirect()->route('catalog.index')->with('error', $exception->getMessage());
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $token = $request->session()->get(PendingPurchaseService::SESSION_KEY);
        if (! $token) return redirect()->route('catalog.index')->with('status', 'There is no saved purchase to complete.');

        try {
            $order = $this->orders->create($request->user(), $token);
            // Only remove the browser continuation token after the committed order exists.
            $request->session()->forget(PendingPurchaseService::SESSION_KEY);
            return redirect()->route('orders.confirmation', $order);
        } catch (DomainException $exception) {
            return redirect()->route('checkout.show')->with('error', $exception->getMessage());
        }
    }

    public function confirmation(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        return view('checkout.confirmation', ['order' => $order->load('installments')]);
    }
}
