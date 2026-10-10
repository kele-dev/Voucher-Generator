<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\GenerationBatch;
use App\Models\User;

class GenerationBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GenerationBatch $batch): bool
    {
        return true;
    }

    public function export(User $user, GenerationBatch $batch): bool
    {
        return $user->hasRole(Role::SuperAdmin, Role::VoucherAdmin);
    }

    public function download(User $user, GenerationBatch $batch): bool
    {
        return $user->hasRole(Role::SuperAdmin, Role::VoucherAdmin);
    }
}
