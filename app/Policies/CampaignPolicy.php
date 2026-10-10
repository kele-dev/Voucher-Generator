<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy extends ManagerPolicy
{
    public function view(User $user, Campaign $campaign): bool
    {
        return $campaign->isVisibleTo($user);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $this->isManager($user);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->isManager($user);
    }

    public function changeStatus(User $user, Campaign $campaign): bool
    {
        return $this->isManager($user);
    }

    public function generate(User $user, Campaign $campaign): bool
    {
        return $this->isManager($user);
    }
}
