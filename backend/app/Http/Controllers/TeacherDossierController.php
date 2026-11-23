<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveDossierRecordRequest;
use App\Models\AuditEvent;
use App\Models\TeacherAchievement;
use App\Models\TeacherCourse;
use App\Models\User;
use App\Policies\TeacherDossierPolicy;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TeacherDossierController extends Controller
{
    public function show(Request $request, User $teacher, TeacherDossierPolicy $policy): Response
    {
        abort_unless($policy->view($request->user(), $teacher), 403);

        $manage = $policy->manage($request->user(), $teacher);
        $profile = $teacher->teacherProfile()->first(['education_level', 'total_experience_months', 'teaching_experience_months']);

        return Inertia::render('dossier/Show', [
            'teacher' => $teacher->only(['id', 'name']),
            'canManage' => $manage,
            'isOwn' => $request->user()->is($teacher),
            'showCatalogEmployment' => $request->user()->is($teacher) && config('pedbase.education_catalog_enabled'),
            'profile' => $profile,
            'diplomas' => $manage ? $teacher->diplomas()->orderByDesc('graduation_year')->orderByDesc('id')->paginate(15, ['*'], 'diplomas')->withQueryString() : null,
            'courses' => $teacher->courses()->orderByDesc('ended_on')->orderByDesc('id')->paginate(15, ['*'], 'courses')
                ->withQueryString()->through(fn (TeacherCourse $course) => $course->only([
                    'id', 'title', 'provider', 'hours', 'started_on', 'ended_on',
                ]) + ($manage ? $course->only(['certificate_number', 'certificate_issued_on']) + ['has_certificate' => $course->certificate_path !== null] : [])),
            'attestations' => $teacher->attestations()->orderByDesc('awarded_on')->paginate(15, ['*'], 'attestations')->withQueryString(),
            'achievements' => config('pedbase.education_catalog_enabled')
                ? $teacher->achievements()->orderByDesc('awarded_on')->orderByDesc('id')->paginate(15, ['*'], 'achievements')
                    ->withQueryString()->through(fn (TeacherAchievement $entry) => $entry->only(['id', 'title', 'issuer', 'awarded_on', 'level'])
                        + ($manage ? ['description' => $entry->description, 'has_certificate' => $entry->document_path !== null] : []))
                : null,
        ]);
    }

    public function store(SaveDossierRecordRequest $request, User $teacher, string $kind): RedirectResponse
    {
        return $this->save($request, $teacher, $kind, null);
    }

    public function update(SaveDossierRecordRequest $request, User $teacher, string $kind, int $record): RedirectResponse
    {
        return $this->save($request, $teacher, $kind, $record);
    }

    public function destroy(Request $request, User $teacher, string $kind, int $record, TeacherDossierPolicy $policy): RedirectResponse
    {
        if ($kind === 'achievements') {
            abort_unless(config('pedbase.education_catalog_enabled'), 404);
        }
        abort_unless($policy->manage($request->user(), $teacher), 403);

        $path = DB::transaction(function () use ($teacher, $kind, $record) {
            $entry = $teacher->{$kind}()->lockForUpdate()->findOrFail($record);
            $column = $this->documentColumn($kind);
            $path = $column === null ? null : $entry->{$column};
            $entry->delete();
            AuditEvent::record('dossier.remove', $kind, $record);

            return $path;
        });

        $this->removeFile($path);

        return back()->with('success', 'Жазба алынып тасталды.');
    }

    public function certificate(Request $request, User $teacher, int $course, TeacherDossierPolicy $policy): StreamedResponse
    {
        abort_unless($policy->manage($request->user(), $teacher), 403);

        $path = $teacher->courses()->findOrFail($course)->certificate_path;

        return $this->downloadDocument($path, 'certificate.pdf', 'courses', $course);
    }

    public function achievementDocument(Request $request, User $teacher, int $achievement, TeacherDossierPolicy $policy): StreamedResponse
    {
        abort_unless(config('pedbase.education_catalog_enabled'), 404);
        abort_unless($policy->manage($request->user(), $teacher), 403);
        $path = $teacher->achievements()->findOrFail($achievement)->document_path;

        return $this->downloadDocument($path, 'achievement.pdf', 'achievements', $achievement);
    }

    private function downloadDocument(?string $path, string $filename, string $kind, int $record): StreamedResponse
    {
        $disk = Storage::disk('teacher-documents');
        abort_unless($path && $disk->exists($path), 404);
        AuditEvent::record($kind === 'courses' ? 'certificate.download' : 'achievement.document.download', $kind, $record);

        return $disk->download($path, $filename, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function save(SaveDossierRecordRequest $request, User $teacher, string $kind, ?int $record): RedirectResponse
    {
        $values = Arr::except($request->validated(), ['certificate']);
        $upload = $request->validated('certificate');
        $path = $upload?->store($kind === 'achievements' ? 'achievements' : 'certificates', 'teacher-documents');
        if ($path === false) {
            throw ValidationException::withMessages(['certificate' => 'Құжатты сақтау мүмкін болмады.']);
        }

        try {
            $oldPath = DB::transaction(function () use ($teacher, $kind, $record, $values, $path) {
                User::query()->whereKey($teacher->id)->lockForUpdate()->firstOrFail();
                $relation = $teacher->{$kind}();
                $entry = $record === null ? $relation->make() : $relation->lockForUpdate()->findOrFail($record);
                $column = $this->documentColumn($kind);
                $oldPath = $path && $column !== null ? $entry->{$column} : null;
                $entry->fill($values);
                if ($path && $column !== null) {
                    $entry->{$column} = $path;
                }
                $entry->save();
                AuditEvent::record($record === null ? 'dossier.create' : 'dossier.update', $kind, $entry->id);

                return $oldPath;
            });
        } catch (Throwable $exception) {
            $this->removeFile($path ?: null);
            if ($exception instanceof UniqueConstraintViolationException && $kind === 'attestations') {
                throw ValidationException::withMessages(['awarded_on' => 'Бұл күнге аттестаттау жазбасы бұрыннан тіркелген.']);
            }
            throw $exception;
        }

        $this->removeFile($oldPath);

        return back()->with('success', 'Жазба сақталды.');
    }

    private function documentColumn(string $kind): ?string
    {
        return match ($kind) {
            'courses' => 'certificate_path',
            'achievements' => 'document_path',
            default => null,
        };
    }

    private function removeFile(?string $path): void
    {
        if ($path) {
            Storage::disk('teacher-documents')->delete($path);
        }
    }
}
