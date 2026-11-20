<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;
use App\UserRole;

class TeacherEmploymentPolicy
{
    public function manage(User $user, School $school): bool
    {
        return $user->isAdmin() || ($user->role === UserRole::SCHOOL_ADMIN
            && app(OrganizationScope::class)->schools($user)->whereKey($school->id)->exists());
    }

    public function viewOwn(User $user): bool
    {
        return $user->role === UserRole::TEACHER;
    }
}
