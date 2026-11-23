<?php

namespace App\Policies;

use App\Models\EducationOrganization;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class EducationOrganizationScope
{
    public function containsTeacher(User $actor, User $teacher): bool
    {
        return $this->visibleTo($actor)->whereExists(fn (QueryBuilder $employment) => $employment
            ->selectRaw('1')->from('education_employments')
            ->where('education_employments.user_id', $teacher->id)->where('education_employments.status', 'active')
            ->whereColumn('education_employments.organization_id', 'education_organizations.id'))->exists();
    }

    public function visibleTo(User $user): Builder
    {
        $query = EducationOrganization::query();

        return match ($user->role) {
            UserRole::ADMIN => $query,
            UserRole::SCHOOL_ADMIN => $query->whereExists(fn (QueryBuilder $grant) => $grant
                ->selectRaw('1')->from('education_organization_admins as oa')
                ->where('oa.user_id', $user->id)->whereColumn('oa.organization_id', 'education_organizations.id')),
            UserRole::METHODIST => $query->whereExists(fn (QueryBuilder $grant) => $grant
                ->selectRaw('1')->from('education_methodist_grants as mg')->where('mg.user_id', $user->id)
                ->whereColumn('mg.area_id', 'education_organizations.area_id')
                ->whereColumn('mg.type_id', 'education_organizations.type_id')),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
