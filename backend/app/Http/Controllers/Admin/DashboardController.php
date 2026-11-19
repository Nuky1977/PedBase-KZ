<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\UserRole;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
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
        ]);
    }
}
