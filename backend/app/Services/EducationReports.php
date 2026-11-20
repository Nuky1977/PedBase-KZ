<?php

namespace App\Services;

use App\Models\User;
use App\Policies\OrganizationScope;
use App\UserRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class EducationReports
{
    public function __construct(private OrganizationScope $scope) {}

    public function query(User $user, string $report, ?int $school, ?int $year): Builder
    {
        $schools = $this->scope->schools($user);
        if ($school !== null) {
            $schools->whereKey($school);
            abort_unless((clone $schools)->exists(), 403);
        }

        $schoolIds = $schools->select('schools.id')->toBase();
        $placements = DB::table('school_user as p')->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('u.role', UserRole::TEACHER->value)->whereIn('p.school_id', clone $schoolIds);
        $teacherIds = (clone $placements)->select('p.user_id')->distinct();
        $profiles = DB::table('teacher_profiles as t')->whereIn('t.user_id', clone $teacherIds);
        $attestations = DB::table('teacher_attestations as a')->whereIn('a.user_id', clone $teacherIds)
            ->whereNotExists(fn (Builder $newer) => $newer->selectRaw('1')->from('teacher_attestations as newer')
                ->whereColumn('newer.user_id', 'a.user_id')->whereColumn('newer.awarded_on', '>', 'a.awarded_on'));

        return match ($report) {
            'schools' => DB::table('schools as s')->whereIn('s.id', $schoolIds)
                ->leftJoinSub((clone $placements)->select('p.school_id')->selectRaw('COUNT(*) AS total')->groupBy('p.school_id'), 'counts', 'counts.school_id', '=', 's.id')
                ->select('s.name')->selectRaw('COALESCE(counts.total, 0) AS teachers')->orderBy('s.name')->orderBy('s.id'),
            'placements' => $placements->join('schools as s', 's.id', '=', 'p.school_id')->select('s.name')
                ->selectRaw('COUNT(CASE WHEN p.is_primary = 1 THEN 1 END) AS primary_count, COUNT(CASE WHEN p.is_primary = 0 THEN 1 END) AS secondary_count')
                ->groupBy('s.id', 's.name')->orderBy('s.name')->orderBy('s.id'),
            'education' => $profiles->selectRaw("COALESCE(t.education_level, '—') AS label, COUNT(*) AS teachers")
                ->groupBy('label')->orderBy('label'),
            'experience' => $profiles->selectRaw('COUNT(*) AS teachers, COUNT(t.total_experience_months) AS general_known, COUNT(t.teaching_experience_months) AS teaching_known, COALESCE(SUM(t.total_experience_months), 0) AS total_months, COALESCE(SUM(t.teaching_experience_months), 0) AS teaching_months'),
            'categories' => $attestations->select('a.category')->selectRaw('COUNT(*) AS teachers')
                ->groupBy('a.category')->orderBy('a.category'),
            'attestations' => $attestations->join('users as u', 'u.id', '=', 'a.user_id')
                ->whereBetween('a.valid_until', [now()->toDateString(), now()->addDays(config('pedbase.attestation_warning_days'))->toDateString()])
                ->select('u.name', 'a.category', 'a.valid_until')->orderBy('a.valid_until')->orderBy('a.id'),
            'courses' => DB::table('teacher_courses as c')->whereIn('c.user_id', $teacherIds)
                ->when($year !== null, fn (Builder $query) => $query->where('c.ended_on', '>=', sprintf('%04d-09-01', $year))
                    ->where('c.ended_on', '<', sprintf('%04d-09-01', $year + 1)))
                ->select('c.provider')->selectRaw('COUNT(*) AS courses, COUNT(DISTINCT c.user_id) AS teachers, SUM(c.hours) AS hours')
                ->groupBy('c.provider')->orderBy('c.provider'),
            default => $this->workload($schoolIds, $report, $year),
        };
    }

    private function workload(Builder $schoolIds, string $report, ?int $year): Builder
    {
        $query = DB::table('teaching_assignments as a')
            ->join('teacher_employments as e', 'e.id', '=', 'a.teacher_employment_id')
            ->whereIn('e.school_id', $schoolIds)
            ->whereNotNull('e.school_user_id')
            ->when($year !== null, fn (Builder $q) => $q->where('a.academic_year', $year))
            ->selectRaw('COUNT(DISTINCT e.user_id) AS teachers, ROUND(SUM(a.weekly_hours), 2) AS hours');

        return match ($report) {
            'subjects' => $query->join('subjects as s', 's.id', '=', 'a.subject_id')->addSelect('s.name_kk')
                ->groupBy('s.id', 's.name_kk')->orderBy('s.name_kk')->orderBy('s.id'),
            'workload' => $query->join('schools as s', 's.id', '=', 'e.school_id')->addSelect('s.name', 'a.academic_year')
                ->groupBy('s.id', 's.name', 'a.academic_year')->orderBy('s.name')->orderBy('s.id')->orderByDesc('a.academic_year'),
            'years' => $query->addSelect('a.academic_year')->groupBy('a.academic_year')->orderByDesc('a.academic_year'),
        };
    }
}
