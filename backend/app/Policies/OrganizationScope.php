<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrganizationScope
{
    public function schools(User $user): Builder
    {
        $schools = School::query();

        return match ($user->role) {
            UserRole::ADMIN => $schools,
            UserRole::SCHOOL_ADMIN => $schools->whereIn('schools.id', DB::table('school_administrators')
                ->select('school_id')->where('user_id', $user->id)),
            UserRole::METHODIST => $schools->whereIn('schools.district_id', DB::table('district_methodists')
                ->select('district_id')->where('user_id', $user->id)),
            default => $schools->whereRaw('1 = 0'),
        };
    }

    public function containsTeacher(User $actor, User $teacher): bool
    {
        return $teacher->schools()->whereIn('schools.id', $this->schools($actor)->select('schools.id'))->exists();
    }
}
