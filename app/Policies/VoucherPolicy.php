<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\Voucher;

class VoucherPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Voucher $voucher): bool
    {
        if ($user->hasRole(Role::SuperAdmin, Role::VoucherAdmin)) {
            return true;
        }

        return $voucher->isVisibleTo($user);
    }

    public function download(User $user, Voucher $voucher): bool
    {
        return $this->view($user, $voucher);
    }

    public function void(User $user, Voucher $voucher): bool
    {
        return $user->hasRole(Role::SuperAdmin, Role::VoucherAdmin);
    }
}
