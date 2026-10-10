<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Shared behaviour for master-data policies: everyone may read, only managers
 * may write.
 */
abstract class ManagerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isManager($user);
    }

    protected function isManager(User $user): bool
    {
        return $user->hasRole(Role::SuperAdmin, Role::VoucherAdmin);
    }
}
