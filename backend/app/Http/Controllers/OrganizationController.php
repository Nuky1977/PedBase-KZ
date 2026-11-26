<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveOrganizationAccessRequest;
use App\Models\AuditEvent;
use App\Models\District;
use App\Models\School;
use App\Models\User;
use App\Policies\OrganizationScope;
use App\Services\TeacherDirectory;
use App\UserRole;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request, OrganizationScope $scope, TeacherDirectory $directory): Response
    {
        abort_unless(in_array($request->user()->role, [UserRole::ADMIN, UserRole::SCHOOL_ADMIN, UserRole::METHODIST], true), 403);

        return Inertia::render('organization/Index', [
            'schools' => $scope->schools($request->user())->orderBy('schools.name')->orderBy('schools.id')
                ->paginate(20, ['schools.id', 'schools.name']),
            'canManageAccess' => $request->user()->isAdmin(),
            'canManageEmployment' => $request->user()->role !== UserRole::METHODIST,
            'role' => $request->user()->role->value,
            'statistics' => $directory->statistics($request->user()),
        ]);
    }

    public function teachers(Request $request, TeacherDirectory $directory): Response
    {
        abort_unless(in_array($request->user()->role, [UserRole::ADMIN, UserRole::SCHOOL_ADMIN, UserRole::METHODIST], true), 403);

        return $this->teacherResults($request, $directory);
    }

    public function school(Request $request, School $school, OrganizationScope $scope, TeacherDirectory $directory): Response
    {
        abort_unless($scope->schools($request->user())->whereKey($school->id)->exists(), 403);

        return $this->teacherResults($request, $directory, $school);
    }

    private function teacherResults(Request $request, TeacherDirectory $directory, ?School $school = null): Response
    {
        $filters = array_filter($request->validate(array_fill_keys(
            ['name', 'organization', 'subject', 'position', 'category'],
            ['nullable', 'string', 'max:150'],
        )), fn (?string $value): bool => $value !== null && $value !== '');

        return Inertia::render('organization/Teachers', [
            'school' => $school?->only(['id', 'name']),
            'filters' => $filters,
            'teachers' => $directory->search($request->user(), $filters, $school?->id)
                ->paginate(20, ['users.id', 'users.name'])->appends($filters),
        ]);
    }

    public function access(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('organization/Access', [
            'districts' => District::orderBy('name')->get(['id', 'name']),
            'schoolOptions' => School::orderBy('name')->get(['id', 'name']),
            'schools' => School::orderBy('name')->orderBy('id')->paginate(20, ['id', 'name', 'district_id'], 'schools')->withQueryString(),
            'staff' => User::whereIn('role', [UserRole::SCHOOL_ADMIN->value, UserRole::METHODIST->value])
                ->leftJoin('school_administrators', 'school_administrators.user_id', '=', 'users.id')
                ->leftJoin('district_methodists', 'district_methodists.user_id', '=', 'users.id')
                ->orderBy('users.name')->orderBy('users.id')
                ->paginate(20, ['users.id', 'users.name', 'users.role', 'school_administrators.school_id', 'district_methodists.district_id'], 'staff')->withQueryString(),
        ]);
    }

    public function saveAccess(SaveOrganizationAccessRequest $request, string $operation): RedirectResponse
    {
        $data = $request->validated();
        try {
            DB::transaction(function () use ($operation, $data) {
                if ($operation === 'district') {
                    $district = District::create($data);
                    AuditEvent::record('organization.create', 'district', $district->id);

                    return;
                }

                if ($operation === 'school') {
                    School::whereKey($data['school_id'])->update(['district_id' => $data['district_id'] ?? null]);
                    AuditEvent::record('organization.assign', 'school', (int) $data['school_id']);

                    return;
                }

                $schoolAdmin = $operation === 'school-admin';
                $user = User::query()->lockForUpdate()->findOrFail($data['user_id']);
                abort_unless($user->role === ($schoolAdmin ? UserRole::SCHOOL_ADMIN : UserRole::METHODIST), 422);
                $table = DB::table($schoolAdmin ? 'school_administrators' : 'district_methodists');
                $column = $schoolAdmin ? 'school_id' : 'district_id';
                if (empty($data[$column])) {
                    $table->where('user_id', $user->id)->delete();
                } else {
                    $table->updateOrInsert(['user_id' => $user->id], [$column => $data[$column]]);
                }
                AuditEvent::record('organization.assign', 'user', $user->id);
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($operation !== 'district') {
                throw $exception;
            }
            throw ValidationException::withMessages(['name' => __('Бұл аудан атауы бұрыннан тіркелген.')]);
        }

        return back()->with('success', __('Өкілеттік деректері сақталды.'));
    }
}
