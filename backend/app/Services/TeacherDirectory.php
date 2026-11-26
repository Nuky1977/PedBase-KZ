<?php

namespace App\Services;

use App\Models\User;
use App\Policies\EducationOrganizationScope;
use App\Policies\OrganizationScope;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as Query;
use Illuminate\Support\Facades\DB;

class TeacherDirectory
{
    public function __construct(
        private OrganizationScope $schools,
        private EducationOrganizationScope $catalog,
    ) {}

    public function search(User $actor, array $filters, ?int $schoolId = null): Builder
    {
        $teachers = User::query()->where('users.role', UserRole::TEACHER->value);
        $workplaceFilters = array_intersect_key($filters, array_flip(['organization', 'position', 'subject']));

        if ($schoolId !== null || ! $actor->isAdmin() || $workplaceFilters !== []) {
            $schools = $this->schoolPlacements($actor, $schoolId)
                ->join('schools', 'schools.id', '=', 'school_user.school_id')
                ->leftJoin('teacher_employments as job', 'job.school_user_id', '=', 'school_user.id');
            $this->filterPlacement($schools, $workplaceFilters, 'schools.name', 'job.position_title');
            if (isset($filters['subject'])) {
                $schools->whereExists(fn (Query $subject) => $subject->selectRaw('1')->from('teaching_assignments as assignment')
                    ->join('subjects', 'subjects.id', '=', 'assignment.subject_id')
                    ->whereColumn('assignment.teacher_employment_id', 'job.id')
                    ->where(fn (Query $names) => $names
                        ->whereLike('subjects.name_kk', '%'.$filters['subject'].'%')
                        ->orWhereLike('subjects.code', '%'.$filters['subject'].'%')));
            }

            $teachers->where(function (Builder $query) use ($schools, $actor, $schoolId, $workplaceFilters, $filters): void {
                $query->whereIn('users.id', $schools->select('school_user.user_id'));
                if ($schoolId === null && config('pedbase.education_catalog_enabled')) {
                    $catalog = $this->catalogPlacements($actor)
                        ->join('education_organizations as organization', 'organization.id', '=', 'employment.organization_id');
                    $this->filterPlacement($catalog, $workplaceFilters, 'organization.name', 'employment.position_title');
                    if (isset($filters['subject'])) {
                        $catalog->whereExists(fn (Query $subject) => $subject->selectRaw('1')->from('education_workloads as workload')
                            ->whereColumn('workload.employment_id', 'employment.id')
                            ->whereLike('workload.details->subject', '%'.$filters['subject'].'%'));
                    }
                    $query->orWhereIn('users.id', $catalog->select('employment.user_id'));
                }
            });
        }

        if (isset($filters['name'])) {
            $teachers->whereLike('users.name', '%'.$filters['name'].'%');
        }
        if (isset($filters['category'])) {
            $teachers->whereHas('attestations', fn (Builder $query) => $query
                ->whereLike('category', '%'.$filters['category'].'%'));
        }

        return $teachers->orderBy('users.name')->orderBy('users.id');
    }

    public function statistics(User $actor): array
    {
        $catalogEnabled = config('pedbase.education_catalog_enabled');
        $placements = $catalogEnabled
            ? $this->catalogPlacements($actor)->leftJoin('education_primary_workplaces as primary_workplace', fn ($join) => $join
                ->on('primary_workplace.user_id', '=', 'employment.user_id')
                ->on('primary_workplace.organization_id', '=', 'employment.organization_id'))
            : $this->schoolPlacements($actor);
        $userColumn = $catalogEnabled ? 'employment.user_id' : 'school_user.user_id';
        $isPrimary = $catalogEnabled ? 'primary_workplace.user_id IS NOT NULL' : 'school_user.is_primary = 1';
        $counts = $placements->join('users', 'users.id', '=', $userColumn)
            ->where('users.role', UserRole::TEACHER->value)
            ->selectRaw("COUNT(DISTINCT $userColumn) AS teachers, COUNT(*) AS placements, COUNT(CASE WHEN $isPrimary THEN 1 END) AS primary_count")
            ->first();

        return [
            'catalog' => (bool) $catalogEnabled,
            'organizations' => $catalogEnabled ? $this->catalog->visibleTo($actor)->count() : $this->schools->schools($actor)->count(),
            'teachers' => (int) $counts->teachers,
            'primary' => (int) $counts->primary_count,
            'secondary' => (int) $counts->placements - (int) $counts->primary_count,
        ];
    }

    public function personalPlacements(User $teacher): array
    {
        $schools = DB::table('school_user')->where('user_id', $teacher->id);
        $catalog = config('pedbase.education_catalog_enabled');
        $placements = $catalog
            ? DB::table('education_employments')->where('user_id', $teacher->id)->where('status', 'active')
            : $schools;
        $primary = $catalog
            ? (clone $placements)->whereIn('organization_id', DB::table('education_primary_workplaces')
                ->where('user_id', $teacher->id)->select('organization_id'))->count()
            : (clone $schools)->where('is_primary', true)->count();

        return ['catalog' => (bool) $catalog, 'primary' => $primary, 'secondary' => $placements->count() - $primary];
    }

    private function schoolPlacements(User $actor, ?int $schoolId = null): Query
    {
        return DB::table('school_user')
            ->whereIn('school_user.school_id', $this->schools->schools($actor)->select('schools.id')->toBase())
            ->when($schoolId !== null, fn (Query $query) => $query->where('school_user.school_id', $schoolId));
    }

    private function catalogPlacements(User $actor): Query
    {
        return DB::table('education_employments as employment')
            ->where('employment.status', 'active')
            ->whereIn('employment.organization_id', $this->catalog->visibleTo($actor)->select('education_organizations.id')->toBase());
    }

    private function filterPlacement(Query $query, array $filters, string $organization, string $position): void
    {
        foreach (['organization' => $organization, 'position' => $position] as $key => $column) {
            if (isset($filters[$key])) {
                $query->whereLike($column, '%'.$filters[$key].'%');
            }
        }
    }
}
