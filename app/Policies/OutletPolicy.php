<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Outlet;
use App\Models\User;

class OutletPolicy extends ManagerPolicy
{
    public function view(User $user, Outlet $outlet): bool
    {
        if ($user->hasRole(Role::OutletStaff)) {
            return $user->outlets()->whereKey($outlet->id)->exists();
        }

        return true;
    }

    public function update(User $user, Outlet $outlet): bool
    {
        return $this->isManager($user);
    }

    public function delete(User $user, Outlet $outlet): bool
    {
        return $this->isManager($user);
    }
}
