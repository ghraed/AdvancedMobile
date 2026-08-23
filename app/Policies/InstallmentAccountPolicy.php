<?php

namespace App\Policies;

use App\Models\InstallmentAccount;
use App\Models\User;

class InstallmentAccountPolicy
{
    public function view(User $user, InstallmentAccount $account): bool
    {
        return $user->isAdmin() || $account->user_id === $user->id;
    }

    public function manage(User $user, InstallmentAccount $account): bool
    {
        return $user->isAdmin();
    }
}
