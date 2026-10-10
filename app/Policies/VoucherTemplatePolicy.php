<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VoucherTemplate;

class VoucherTemplatePolicy extends ManagerPolicy
{
    public function view(User $user, VoucherTemplate $template): bool
    {
        return true;
    }

    public function update(User $user, VoucherTemplate $template): bool
    {
        return $this->isManager($user) && ! $template->isLocked();
    }

    public function delete(User $user, VoucherTemplate $template): bool
    {
        return $this->isManager($user) && ! $template->isLocked();
    }

    /**
     * Superseding a locked template with a new version is always allowed.
     */
    public function createVersion(User $user, VoucherTemplate $template): bool
    {
        return $this->isManager($user);
    }
}
