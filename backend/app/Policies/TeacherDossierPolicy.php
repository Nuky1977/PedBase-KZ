<?php

namespace App\Policies;

use App\Models\User;
use App\UserRole;

class TeacherDossierPolicy
{
    public function view(User $actor, User $teacher): bool
    {
        return $teacher->role === UserRole::TEACHER && (
            $actor->isAdmin() || $actor->is($teacher)
            || app(OrganizationScope::class)->containsTeacher($actor, $teacher)
        );
    }

    public function manage(User $actor, User $teacher): bool
    {
        return $teacher->role === UserRole::TEACHER && ($actor->isAdmin() || $actor->is($teacher));
    }
}
