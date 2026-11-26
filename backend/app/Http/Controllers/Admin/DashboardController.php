<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EducationOrganization;
use App\Models\School;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\TeacherDirectory;
use App\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TeacherDirectory $directory): Response
    {
        $schools = School::query()->toBase()
            ->selectRaw('COUNT(*) AS total, COUNT(CASE WHEN is_active = ? THEN 1 END) AS active', [true])
            ->first();

        $users = User::query()->toBase()
            ->selectRaw('COUNT(*) AS total, COUNT(CASE WHEN role = ? THEN 1 END) AS teachers', [UserRole::TEACHER->value])
            ->first();

        $assignments = User::query()->toBase()
            ->join('school_user', 'school_user.user_id', '=', 'users.id')
            ->where('users.role', UserRole::TEACHER->value)
            ->selectRaw('COUNT(DISTINCT users.id) AS teachers')
            ->selectRaw('COUNT(CASE WHEN school_user.is_primary = ? THEN 1 END) AS primary_count', [true])
            ->selectRaw('COUNT(CASE WHEN school_user.is_primary = ? THEN 1 END) AS secondary_count', [false])
            ->first();

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'schools' => (int) $schools->total,
                'activeSchools' => (int) $schools->active,
                'teachers' => (int) $users->teachers,
                'users' => (int) $users->total,
                'assignedTeachers' => (int) $assignments->teachers,
                'primaryAssignments' => (int) $assignments->primary_count,
                'secondaryAssignments' => (int) $assignments->secondary_count,
            ],
            'analytics' => [
                'placements' => $directory->statistics($request->user()),
                'catalogOrganizations' => config('pedbase.education_catalog_enabled')
                    ? EducationOrganization::query()->count()
                    : null,
                'workloadByYear' => TeachingAssignment::query()->toBase()
                    ->join('teacher_employments', 'teacher_employments.id', '=', 'teaching_assignments.teacher_employment_id')
                    ->where('teacher_employments.status', 'active')
                    ->selectRaw('academic_year AS year, SUM(weekly_hours) AS hours')
                    ->groupBy('academic_year')->orderByDesc('academic_year')->limit(6)->get()
                    ->reverse()->values()
                    ->map(fn (object $row): array => ['year' => (int) $row->year, 'hours' => round((float) $row->hours, 2)]),
                'recentActivities' => DB::table('audit_events')
                    ->whereIn('action', [
                        'organization.assign', 'employment.save', 'assignment.create',
                        'assignment.update', 'spreadsheet.import.schools', 'spreadsheet.import.teachers',
                    ])
                    ->orderByDesc('created_at')->orderByDesc('id')->limit(5)
                    ->get(['action', 'created_at']),
            ],
        ]);
    }
}
