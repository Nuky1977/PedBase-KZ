<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\EducationOrganization;
use App\Policies\EducationOrganizationScope;
use App\Services\EducationOrganizationRegistration;
use App\Services\EducationWorkloadImport;
use App\Services\SpreadsheetExport;
use App\Services\SpreadsheetImport;
use App\Services\SpreadsheetLabels;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EducationOrganizationController extends Controller
{
    public function index(Request $request, EducationOrganizationScope $scope): Response
    {
        $catalog = $this->catalog($request, $scope);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:50', 'alpha_dash:ascii'],
            'region' => ['nullable', 'string', 'max:32', 'alpha_dash:ascii'],
            'area' => ['nullable', 'string', 'max:32', 'alpha_dash:ascii'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
        ]);
        $type = $filters['type'] ?? null;
        $visibleTypes = (clone $catalog)->select('education_organizations.type_id')->distinct()->toBase();
        $visibleAreas = (clone $catalog)->select('education_organizations.area_id')->distinct()->toBase();
        $areas = DB::table('education_areas')->whereIn('education_areas.id', $visibleAreas);
        $visibleRegions = (clone $areas)->select('region_id')->distinct();
        $organizations = $this->withDirectoryNames($catalog);
        if (isset($filters['search'])) {
            $organizations->whereLike('education_organizations.name', '%'.$filters['search'].'%');
        }
        foreach (['type' => 'types.code', 'region' => 'regions.code', 'area' => 'areas.code'] as $key => $column) {
            if (isset($filters[$key])) {
                $organizations->where($column, $filters[$key]);
            }
        }
        match ($filters['status'] ?? null) {
            'active' => $organizations->whereNull('education_organizations.archived_at')->where('education_organizations.is_active', true),
            'inactive' => $organizations->whereNull('education_organizations.archived_at')->where('education_organizations.is_active', false),
            'archived' => $organizations->whereNotNull('education_organizations.archived_at'),
            default => null,
        };

        return Inertia::render('education-organizations/Index', [
            'showTemplates' => $request->user()->isAdmin(),
            'types' => DB::table('education_organization_types')->whereIn('id', $visibleTypes)->orderBy('name')->get(['code', 'name']),
            'type' => $type,
            'filters' => $filters,
            'regions' => DB::table('education_regions')->whereIn('id', $visibleRegions)->orderBy('name')->orderBy('code')->get(['code', 'name']),
            'areas' => $areas->join('education_regions as r', 'r.id', '=', 'education_areas.region_id')
                ->orderBy('education_areas.name')->orderBy('education_areas.code')
                ->get(['education_areas.code', 'education_areas.name', 'r.code as region_code']),
            'organizations' => $organizations
                ->orderBy('education_organizations.name')->orderBy('education_organizations.id')
                ->paginate(20)->withQueryString()->through(fn (EducationOrganization $organization) => [
                    ...$organization->toArray(),
                    'status' => $organization->status(),
                ]),
        ]);
    }

    public function show(Request $request, int $organization, EducationOrganizationScope $scope): Response
    {
        $catalog = $this->catalog($request, $scope);
        $entry = $this->withDirectoryNames($catalog)->whereKey($organization)
            ->addSelect('education_organizations.bin')->first();
        abort_if($entry === null, 403);

        return Inertia::render('education-organizations/Show', [
            'organization' => [...$entry->toArray(), 'status' => $entry->status()],
            'canUpdateStatus' => Gate::allows('updateStatus', EducationOrganization::class),
            'canEdit' => $request->user()->isAdmin()
                || ($request->user()->role === UserRole::SCHOOL_ADMIN && $entry->archived_at === null),
        ]);
    }

    public function updateStatus(Request $request, int $organization): RedirectResponse
    {
        abort_unless(config('pedbase.education_catalog_enabled'), 404);
        Gate::authorize('updateStatus', EducationOrganization::class);
        $status = $request->validate(['status' => ['required', Rule::in(['active', 'inactive', 'archived'])]])['status'];

        DB::transaction(function () use ($organization, $status) {
            $entry = EducationOrganization::query()->lockForUpdate()->findOrFail($organization);
            if ($entry->status() === $status) {
                return;
            }
            $entry->forceFill([
                'is_active' => $status === 'active',
                'archived_at' => $status === 'archived' ? now() : null,
            ])->save();
            AuditEvent::record('organization.status.'.$status, 'education_organization', $entry->id);
        }, 3);

        return to_route('education-organizations.show', $organization);
    }

    public function employmentTemplate(Request $request, string $type, SpreadsheetExport $export, EducationOrganizationRegistration $registration, EducationWorkloadImport $workloads): BinaryFileResponse
    {
        abort_unless(config('pedbase.education_catalog_enabled'), 404);
        abort_unless($request->user()->isAdmin(), 403);
        if ($type === 'teacher-accounts') {
            $path = $export->xlsx([array_values(SpreadsheetLabels::translate(SpreadsheetImport::CATALOG_TEACHER_COLUMNS))], [
                [__('Файлға құпиясөз, ЖСН немесе жеке құжат деректерін қоспаңыз.')],
                [__('Әр жұмыс орнын бөлек жолға жазыңыз. Логин, email және аты-жөн барлық жолда бірдей болуы керек.')],
                [__('Негізгі жұмыс орны үшін primary, қосымша жұмыс орны үшін secondary қолданыңыз.')],
                [__('Ұйым түрінің кодтары: :codes', ['codes' => implode(', ', array_keys(EducationOrganizationRegistration::TYPES))])],
                [__('Педагог алғашқы құпиясөзін тіркелген email арқылы «Құпиясөзді ұмыттыңыз ба?» бөлімінде орнатады.')],
            ]);

            return response()->download($path, 'pedbase-education-teacher-accounts.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
            ])->deleteFileAfterSend(true);
        }
        if ($request->boolean('workload')) {
            abort_unless(in_array($type, EducationWorkloadImport::TYPES, true), 404);

            return response()->download($workloads->template($type, $export), "pedbase-education-{$type}-workload.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
            ])->deleteFileAfterSend(true);
        }
        if ($request->boolean('registration')) {
            abort_unless(array_key_exists($type, EducationOrganizationRegistration::TYPES)
                || DB::table('education_organization_types')->where('code', $type)->exists(), 404);

            return response()->download($registration->template($type, $export), "pedbase-education-{$type}-registration.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
            ])->deleteFileAfterSend(true);
        }
        $additionalColumns = match ($type) {
            'school' => ['Пән коды', 'Оқу жылы', 'Апталық жүктеме'],
            'college' => ['Мамандық коды', 'Біліктілік коды', 'Кәсіптік модуль коды', 'Оқу жылы', 'Жүктеме', 'Өлшем бірлігі'],
            'preschool' => ['Педагогикалық қызмет бағыты'],
            'supplementary' => ['Үйірме немесе секция', 'Бағдарлама коды'],
            'special' => ['Қызмет бағыты', 'Бағдарлама коды'],
            default => abort(404),
        };
        $columns = ['Аты-жөні', 'Логин', 'Email', 'Ұйым БСН', 'Лауазымы', ...$additionalColumns];
        $path = $export->xlsx([SpreadsheetLabels::translate($columns)], [
            [__('Жобалық үлгі')],
            [__('Баған құрылымын келісуге арналған. Осы үлгі арқылы импорт кейін іске қосылады.')],
            [__('Нақты жеке деректерді енгізбеңіз.')],
        ]);

        return response()->download($path, "pedbase-education-{$type}-draft.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    public function employment(Request $request): Response
    {
        abort_unless(config('pedbase.education_catalog_enabled'), 404);
        abort_unless($request->user()->role === UserRole::TEACHER, 403);
        $employments = DB::table('education_employments as e')
            ->join('education_organizations as o', 'o.id', '=', 'e.organization_id')
            ->leftJoin('education_primary_workplaces as p', fn ($join) => $join
                ->on('p.user_id', '=', 'e.user_id')->on('p.organization_id', '=', 'e.organization_id'))
            ->where('e.user_id', $request->user()->id)
            ->select('e.id', 'o.name', 'e.position_title', 'e.status')
            ->selectRaw('CASE WHEN o.archived_at IS NULL THEN 0 ELSE 1 END AS organization_archived')
            ->selectRaw('CASE WHEN p.user_id IS NULL THEN 0 ELSE 1 END AS is_primary')
            ->orderByDesc('is_primary')->orderBy('o.name')->orderBy('e.id')
            ->paginate(20)->through(function (object $employment): object {
                $employment->is_primary = (bool) $employment->is_primary;
                $employment->organization_archived = (bool) $employment->organization_archived;

                return $employment;
            });

        return Inertia::render('education-organizations/Employment', ['employments' => $employments]);
    }

    private function catalog(Request $request, EducationOrganizationScope $scope): Builder
    {
        abort_unless(config('pedbase.education_catalog_enabled'), 404);
        abort_unless(in_array($request->user()->role, [UserRole::ADMIN, UserRole::SCHOOL_ADMIN, UserRole::METHODIST], true), 403);

        return $scope->visibleTo($request->user());
    }

    private function withDirectoryNames(Builder $query): Builder
    {
        return $query->join('education_organization_types as types', 'types.id', '=', 'education_organizations.type_id')
            ->join('education_areas as areas', 'areas.id', '=', 'education_organizations.area_id')
            ->join('education_regions as regions', 'regions.id', '=', 'areas.region_id')
            ->select('education_organizations.id', 'education_organizations.name', 'education_organizations.short_name',
                'education_organizations.locality', 'education_organizations.is_active', 'education_organizations.archived_at', 'types.name as type_name',
                'areas.name as area_name', 'regions.name as region_name');
    }
}
