<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy extends ManagerPolicy
{
    public function view(User $user, Brand $brand): bool
    {
        return true;
    }

    public function update(User $user, Brand $brand): bool
    {
        return $this->isManager($user);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $this->isManager($user);
    }
}
