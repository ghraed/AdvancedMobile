<?php

namespace App\Policies;

use App\Models\InstallmentApplication;
use App\Models\User;

class InstallmentApplicationPolicy
{
    public function view(User $user, InstallmentApplication $application): bool
    {
        return $user->isAdmin() || $application->user_id === $user->id;
    }

    public function viewDocument(User $user, InstallmentApplication $application): bool
    {
        return $this->view($user, $application);
    }
}
