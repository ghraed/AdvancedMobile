<?php

namespace App\Policies;

use App\Models\InstallmentPayment;
use App\Models\User;

class InstallmentPaymentPolicy
{
    public function view(User $user, InstallmentPayment $payment): bool
    {
        return $user->isAdmin() || $payment->account?->user_id === $user->id;
    }

    public function reverse(User $user, InstallmentPayment $payment): bool
    {
        return $user->isAdmin();
    }
}
