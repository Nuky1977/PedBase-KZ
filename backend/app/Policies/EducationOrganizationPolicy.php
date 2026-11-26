<?php

namespace App\Policies;

use App\Models\EducationOrganization;
use App\Models\User;

class EducationOrganizationPolicy
{
    public function updateStatus(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, EducationOrganization $organization): bool
    {
        return false;
    }
}
