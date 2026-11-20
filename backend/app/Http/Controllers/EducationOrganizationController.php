<?php

namespace App\Http\Controllers;

use App\Policies\EducationOrganizationScope;
use App\Services\SpreadsheetExport;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EducationOrganizationController extends Controller
{
    public function index(Request $request, EducationOrganizationScope $scope): Response
    {
        $catalog = $this->catalog($request, $scope);
        $filters = $request->validate(['type' => ['nullable', 'string', 'max:50', 'alpha_dash:ascii']]);
        $type = $filters['type'] ?? null;
        $visibleTypes = (clone $catalog)->select('education_organizations.type_id')->distinct()->toBase();

        return Inertia::render('education-organizations/Index', [
            'showTemplates' => $request->user()->isAdmin(),
            'types' => DB::table('education_organization_types')->whereIn('id', $visibleTypes)->orderBy('name')->get(['code', 'name']),
            'type' => $type,
            'organizations' => $this->withDirectoryNames($catalog)
                ->when($type !== null, fn (Builder $query) => $query->where('types.code', $type))
                ->orderBy('education_organizations.name')->orderBy('education_organizations.id')
                ->paginate(20)->withQueryString(),
        ]);
    }

    public function show(Request $request, int $organization, EducationOrganizationScope $scope): Response
    {
        $catalog = $this->catalog($request, $scope);
        $entry = $this->withDirectoryNames($catalog)->whereKey($organization)
            ->addSelect('education_organizations.bin')->first();
        abort_if($entry === null, 403);

        return Inertia::render('education-organizations/Show', ['organization' => $entry]);
    }

    public function employmentTemplate(Request $request, string $type, SpreadsheetExport $export): BinaryFileResponse
    {
        abort_unless(config('pedbase.education_catalog_enabled'), 404);
        abort_unless($request->user()->isAdmin(), 403);
        $additionalColumns = match ($type) {
            'school' => ['Пән коды', 'Оқу жылы', 'Апталық жүктеме'],
            'college' => ['Мамандық коды', 'Біліктілік коды', 'Кәсіптік модуль коды', 'Оқу жылы', 'Жүктеме', 'Өлшем бірлігі'],
            'preschool' => ['Педагогикалық қызмет бағыты'],
            'supplementary' => ['Үйірме немесе секция', 'Бағдарлама коды'],
            'special' => ['Қызмет бағыты', 'Бағдарлама коды'],
            default => abort(404),
        };
        $columns = ['Аты-жөні', 'Логин', 'Email', 'Ұйым БСН', 'Лауазымы', ...$additionalColumns];
        $path = $export->xlsx([$columns], [
            ['Жобалық үлгі'],
            ['Баған құрылымын келісуге арналған. Осы үлгі арқылы импорт кейін іске қосылады.'],
            ['Нақты жеке деректерді енгізбеңіз.'],
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
            ->selectRaw('CASE WHEN p.user_id IS NULL THEN 0 ELSE 1 END AS is_primary')
            ->orderByDesc('is_primary')->orderBy('o.name')->orderBy('e.id')
            ->paginate(20)->through(function (object $employment): object {
                $employment->is_primary = (bool) $employment->is_primary;

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
                'education_organizations.locality', 'education_organizations.is_active', 'types.name as type_name',
                'areas.name as area_name', 'regions.name as region_name');
    }
}
