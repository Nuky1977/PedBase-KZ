<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpreadsheetImportRequest;
use App\Models\EducationOrganization;
use App\Services\EducationOrganizationRegistration;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EducationRegistrationController extends Controller
{
    public function create(EducationOrganizationRegistration $registration): Response
    {
        $this->ensureEnabled();

        return Inertia::render('education-organizations/Form', $registration->formData() + ['organization' => null]);
    }

    public function edit(int $organization, EducationOrganizationRegistration $registration): Response
    {
        $this->ensureEnabled();
        $entry = EducationOrganization::query()->findOrFail($organization);
        $area = DB::table('education_areas')->find($entry->area_id);

        return Inertia::render('education-organizations/Form', $registration->formData() + [
            'organization' => [
                ...$entry->only(['id', 'name', 'short_name', 'bin', 'locality']),
                'status' => $entry->status(),
                'type_code' => DB::table('education_organization_types')->where('id', $entry->type_id)->value('code'),
                'region_code' => DB::table('education_regions')->where('id', $area->region_id)->value('code'),
                'area_code' => $area->code,
            ],
        ]);
    }

    public function store(Request $request, EducationOrganizationRegistration $registration): RedirectResponse
    {
        $this->ensureEnabled();
        $entry = $registration->save($request->all());

        return to_route('education-organizations.show', $entry);
    }

    public function update(Request $request, int $organization, EducationOrganizationRegistration $registration): RedirectResponse
    {
        $this->ensureEnabled();
        $entry = $registration->save($request->all(), $organization);

        return to_route('education-organizations.show', $entry);
    }

    public function imports(EducationOrganizationRegistration $registration): Response
    {
        $this->ensureEnabled();

        $references = $registration->formData();

        return Inertia::render('education-organizations/Import', [
            'types' => $references['types']->pluck('name', 'code')->all() + EducationOrganizationRegistration::TYPES,
            'regions' => $references['regions'],
            'areas' => $references['areas'],
        ]);
    }

    public function preview(SpreadsheetImportRequest $request, EducationOrganizationRegistration $registration): JsonResponse
    {
        $this->ensureEnabled();
        $path = $request->file('file')->getPathname();
        try {
            $result = $registration->inspect($path);
        } catch (QueryException) {
            throw ValidationException::withMessages(['file' => 'Анықтамалықтар тексерілмеді. Сұрауды кейін қайталаңыз.']);
        }
        $token = $result['has_errors'] ? null : Crypt::encrypt($this->fileIdentity($request, $path) + [
            'expires' => now()->addMinutes(15)->timestamp,
        ]);

        return response()->json(['rows' => $result['rows'], 'token' => $token])->header('Cache-Control', 'private, no-store');
    }

    public function confirm(SpreadsheetImportRequest $request, EducationOrganizationRegistration $registration): JsonResponse
    {
        $this->ensureEnabled();
        $path = $request->file('file')->getPathname();
        try {
            $token = Crypt::decrypt($request->validated('token'));
        } catch (DecryptException) {
            $token = null;
        }
        $identity = $this->fileIdentity($request, $path);
        if (! is_array($token) || array_intersect_key($token, $identity) !== $identity || ($token['expires'] ?? 0) <= now()->timestamp) {
            throw ValidationException::withMessages(['file' => 'Файл немесе әкімші алдын ала қарауға сәйкес келмейді не растау мерзімі аяқталған.']);
        }
        try {
            $created = $registration->import($path);
        } catch (QueryException) {
            throw ValidationException::withMessages(['file' => 'Импорт сақталмады. Файл мен анықтамалықтарды қайта тексеріңіз.']);
        }

        return response()->json(['created' => $created])->header('Cache-Control', 'private, no-store');
    }

    private function fileIdentity(SpreadsheetImportRequest $request, string $path): array
    {
        return [
            'actor' => $request->user()->id,
            'kind' => 'education_organizations',
            'digest' => hash_file('sha256', $path),
        ];
    }

    private function ensureEnabled(): void
    {
        abort_unless(config('pedbase.education_catalog_enabled'), 404);
    }
}
