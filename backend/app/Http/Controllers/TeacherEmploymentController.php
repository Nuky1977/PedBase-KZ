<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTeacherEmploymentRequest;
use App\Http\Requests\SaveTeachingAssignmentRequest;
use App\Http\Requests\StoreEmploymentSubjectRequest;
use App\Models\AuditEvent;
use App\Models\School;
use App\Models\Subject;
use App\Models\TeacherEmployment;
use App\Models\User;
use App\UserRole;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeacherEmploymentController extends Controller
{
    public function school(School $school): Response
    {
        Gate::authorize('manage', [TeacherEmployment::class, $school]);

        $teachers = $school->users()->withPivot('id')
            ->where('role', UserRole::TEACHER->value)
            ->orderBy('users.name')->orderBy('users.id')->paginate(20, ['users.id', 'users.name']);
        $employments = $this->employments($teachers->getCollection()->pluck('pivot.id'));

        return Inertia::render('employment/Index', [
            'school' => $school->only(['id', 'name']),
            'records' => $teachers->getCollection()->map(fn (User $teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'is_primary' => (bool) $teacher->pivot->is_primary,
                'employment' => $this->details($employments->get($teacher->pivot->id)),
            ]),
            'pagination' => ['previous' => $teachers->previousPageUrl(), 'next' => $teachers->nextPageUrl()],
        ]);
    }

    public function mine(Request $request): Response
    {
        Gate::authorize('viewOwn', TeacherEmployment::class);

        $schools = $request->user()->schools()->withPivot('id')
            ->orderBy('schools.name')->get(['schools.id', 'schools.name']);
        $employments = $this->employments($schools->pluck('pivot.id'));

        return Inertia::render('employment/Index', [
            'school' => null,
            'records' => $schools->map(fn (School $school) => [
                'id' => $school->id,
                'name' => $school->name,
                'is_primary' => (bool) $school->pivot->is_primary,
                'employment' => $this->details($employments->get($school->pivot->id)),
            ]),
        ]);
    }

    public function show(School $school, User $teacher): Response
    {
        Gate::authorize('manage', [TeacherEmployment::class, $school]);
        $placement = $this->placement($school, $teacher);
        $employment = $this->employments(collect([$placement->id]))->get($placement->id);

        return Inertia::render('employment/Edit', [
            'school' => $school->only(['id', 'name']),
            'teacher' => $teacher->only(['id', 'name']),
            'isPrimary' => (bool) $placement->is_primary,
            'employment' => $this->details($employment),
            'subjects' => Subject::query()->orderBy('name_kk')->get(['id', 'code', 'name_kk']),
            'academicYear' => now()->month >= 9 ? now()->year : now()->year - 1,
            'canCreateSubject' => request()->user()->isAdmin(),
        ]);
    }

    public function save(SaveTeacherEmploymentRequest $request, School $school, User $teacher): RedirectResponse
    {
        DB::transaction(function () use ($request, $school, $teacher) {
            $placement = $this->placement($school, $teacher, lock: true);
            $employment = TeacherEmployment::updateOrCreate(
                ['school_user_id' => $placement->id],
                $request->validated() + ['user_id' => $teacher->id, 'school_id' => $school->id],
            );
            AuditEvent::record('employment.save', 'teacher_employment', $employment->id);
        });

        return back()->with('success', 'Еңбек деректері сақталды.');
    }

    public function addSubject(StoreEmploymentSubjectRequest $request, School $school, User $teacher): RedirectResponse
    {
        $this->placement($school, $teacher);

        try {
            Subject::create($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Бұл пән коды бұрыннан бар.']);
        }

        return back()->with('success', 'Пән анықтамалыққа қосылды.');
    }

    public function addAssignment(SaveTeachingAssignmentRequest $request, School $school, User $teacher): RedirectResponse
    {
        $this->saveAssignment($school, $teacher, $request->validated());

        return back()->with('success', 'Пән жүктемесі қосылды.');
    }

    public function updateAssignment(SaveTeachingAssignmentRequest $request, School $school, User $teacher, int $assignment): RedirectResponse
    {
        $this->saveAssignment($school, $teacher, $request->validated(), $assignment);

        return back()->with('success', 'Пән жүктемесі жаңартылды.');
    }

    public function removeAssignment(School $school, User $teacher, int $assignment): RedirectResponse
    {
        Gate::authorize('manage', [TeacherEmployment::class, $school]);
        $this->withEmployment($school, $teacher, function (TeacherEmployment $employment) use ($assignment) {
            $employment->assignments()->findOrFail($assignment)->delete();
            AuditEvent::record('assignment.remove', 'teaching_assignment', $assignment);
        });

        return back()->with('success', 'Пән жүктемесі алынып тасталды.');
    }

    public function archive(School $school, User $teacher): RedirectResponse
    {
        Gate::authorize('manage', [TeacherEmployment::class, $school]);
        $this->withEmployment($school, $teacher, function (TeacherEmployment $employment) {
            $employment->update(['school_user_id' => null]);
            AuditEvent::record('employment.archive', 'teacher_employment', $employment->id);
        });

        return back()->with('success', 'Еңбек жазбасы мұрағатталды.');
    }

    private function saveAssignment(School $school, User $teacher, array $data, ?int $assignment = null): void
    {
        try {
            $this->withEmployment($school, $teacher, function (TeacherEmployment $employment) use ($data, $assignment) {
                if ($assignment === null) {
                    $record = $employment->assignments()->create($data);
                    AuditEvent::record('assignment.create', 'teaching_assignment', $record->id);

                    return;
                }

                $employment->assignments()->findOrFail($assignment)->update($data);
                AuditEvent::record('assignment.update', 'teaching_assignment', $assignment);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['subject_id' => 'Бұл пән осы оқу жылына бұрыннан бекітілген.']);
        }
    }

    private function withEmployment(School $school, User $teacher, Closure $operation): void
    {
        DB::transaction(function () use ($school, $teacher, $operation) {
            $placement = $this->placement($school, $teacher, lock: true);
            $employment = TeacherEmployment::where('school_user_id', $placement->id)->firstOrFail();
            $operation($employment);
        });
    }

    private function placement(School $school, User $teacher, bool $lock = false): object
    {
        abort_unless($teacher->role === UserRole::TEACHER, 404);

        $query = DB::table('school_user')->where('school_id', $school->id)->where('user_id', $teacher->id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function employments(Collection $placementIds): Collection
    {
        return TeacherEmployment::query()->whereIn('school_user_id', $placementIds)
            ->with(['assignments' => fn ($query) => $query->orderByDesc('academic_year')->orderBy('id'), 'assignments.subject:id,name_kk'])
            ->get()->keyBy('school_user_id');
    }

    private function details(?TeacherEmployment $employment): ?array
    {
        if ($employment === null) {
            return null;
        }

        $totals = $employment->assignments->groupBy('academic_year')->map(function (Collection $assignments, int $year) {
            $hundredths = $assignments->sum(function ($assignment) {
                [$whole, $fraction] = explode('.', $assignment->weekly_hours);

                return (int) $whole * 100 + (int) $fraction;
            });

            return [
                'academic_year' => $year,
                'weekly_hours' => sprintf('%d.%02d', intdiv($hundredths, 100), $hundredths % 100),
            ];
        })->values();

        return [
            'position_title' => $employment->position_title,
            'status' => $employment->status,
            'totals' => $totals,
            'assignments' => $employment->assignments->map(fn ($assignment) => [
                'id' => $assignment->id,
                'subject_id' => $assignment->subject_id,
                'subject_name' => $assignment->subject->name_kk,
                'academic_year' => $assignment->academic_year,
                'weekly_hours' => $assignment->weekly_hours,
            ]),
        ];
    }
}
