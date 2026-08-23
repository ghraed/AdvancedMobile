<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class PosOrderPolicy
{
    public function viewPos(User $user, Order $order): bool
    {
        return $user->canAccessPos() && $order->isPosSale();
    }

    public function refundPos(User $user, Order $order): bool
    {
        return $user->canRefundPosSales() && $order->isPosSale();
    }
}
