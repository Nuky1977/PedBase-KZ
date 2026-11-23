<?php

namespace App\Services;

use App\Models\User;
use App\Policies\EducationOrganizationScope;
use App\UserRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class EducationCatalogReports
{
    public const REPORTS = [
        'workloads' => 'Жүктеме',
        'workload_totals' => 'Жүктеме жиынтығы',
        'placements' => 'Еңбек жазбалары',
        'categories' => 'Санаттар және аттестаттау',
        'courses' => 'Кәсіби даму курстары',
    ];

    public const COLUMNS = [
        'organization' => 'Ұйым', 'type' => 'Ұйым түрі', 'region' => 'Облыс немесе қала',
        'area' => 'Аудан немесе қала', 'teacher' => 'Педагог', 'archived_at' => 'Архив уақыты',
        'position_title' => 'Лауазым', 'was_primary' => 'Негізгі жұмыс орны', 'academic_year' => 'Оқу жылы',
        'amount' => 'Жүктеме', 'unit' => 'Өлшем бірлігі', 'period' => 'Есептеу кезеңі', 'details' => 'Бағдарлама және бағыт',
        'category' => 'Санат', 'awarded_on' => 'Аттестатталған күні', 'valid_until' => 'Жарамдылық мерзімі',
        'provider' => 'Курс провайдері', 'ended_on' => 'Аяқталған күні', 'hours' => 'Курс сағаты',
    ];

    public function __construct(private EducationOrganizationScope $scope) {}

    public function query(User $user, array $filters): Builder
    {
        $mine = $user->role === UserRole::TEACHER;
        $visible = $mine
            ? DB::table('education_employments')->where('user_id', $user->id)->select('organization_id')
            : $this->scope->visibleTo($user)->select('education_organizations.id')->toBase();
        if (isset($filters['organization_id'])) {
            abort_unless(DB::table('education_organizations')->whereIn('id', clone $visible)
                ->where('id', $filters['organization_id'])->exists(), 403);
        }
        $query = DB::table('education_employments as e')
            ->join('education_organizations as o', 'o.id', '=', 'e.organization_id')
            ->join('education_organization_types as t', 't.id', '=', 'o.type_id')
            ->join('education_areas as a', 'a.id', '=', 'o.area_id')
            ->join('education_regions as r', 'r.id', '=', 'a.region_id')
            ->join('users as u', 'u.id', '=', 'e.user_id')
            ->whereIn('o.id', $visible)
            ->when($mine, fn (Builder $q) => $q->where('e.user_id', $user->id))
            ->select('o.name as organization', 't.code as type', 'r.name as region', 'a.name as area', 'u.name as teacher', 'o.archived_at')
            ->orderBy('o.name')->orderBy('o.id')->orderBy('u.name')->orderBy('e.id');
        foreach (['organization_id' => 'o.id', 'type' => 't.code', 'area' => 'a.code', 'teacher' => 'u.name'] as $key => $column) {
            if (isset($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }

        if (in_array($filters['report'], ['workloads', 'workload_totals'], true)) {
            $query->join('education_workloads as w', 'w.employment_id', '=', 'e.id')
                ->when(isset($filters['year']), fn (Builder $q) => $q->where('w.academic_year', $filters['year']));
            foreach (['subject' => 'w.details->subject', 'module' => 'w.details->module', 'unit' => 'w.unit', 'period' => 'w.period'] as $key => $column) {
                if (isset($filters[$key])) {
                    $query->where($column, $filters[$key]);
                }
            }
        }

        return match ($filters['report']) {
            'workloads' => $query
                ->addSelect('w.position_title', 'w.was_primary', 'w.academic_year', 'w.amount', 'w.unit', 'w.period', 'w.details')
                ->orderByDesc('w.academic_year')->orderBy('w.id'),
            'workload_totals' => $query->reorder()
                ->select('o.name as organization', 't.code as type', 'r.name as region', 'a.name as area', 'o.archived_at',
                    'w.academic_year', 'w.unit', 'w.period')->selectRaw('SUM(w.amount) AS amount')
                ->groupBy('o.id', 'o.name', 't.code', 'r.name', 'a.name', 'o.archived_at', 'w.academic_year', 'w.unit', 'w.period')
                ->orderBy('o.name')->orderBy('o.id')->orderByDesc('w.academic_year')->orderBy('w.unit')->orderBy('w.period'),
            'categories' => $query->join('teacher_attestations as c', 'c.user_id', '=', 'e.user_id')
                ->when(isset($filters['category']), fn (Builder $q) => $q->where('c.category', $filters['category']))
                ->when(isset($filters['awarded_from']), fn (Builder $q) => $q->where('c.awarded_on', '>=', $filters['awarded_from']))
                ->when(isset($filters['awarded_until']), fn (Builder $q) => $q->where('c.awarded_on', '<=', $filters['awarded_until']))
                ->addSelect('c.category', 'c.awarded_on', 'c.valid_until')->orderByDesc('c.awarded_on')->orderBy('c.id'),
            'courses' => $query->join('teacher_courses as c', 'c.user_id', '=', 'e.user_id')
                ->when(isset($filters['provider']), fn (Builder $q) => $q->where('c.provider', $filters['provider']))
                ->addSelect('c.provider', 'c.ended_on', 'c.hours')->orderByDesc('c.ended_on')->orderBy('c.id'),
            'placements' => $query->leftJoin('education_primary_workplaces as p', fn ($join) => $join
                ->on('p.user_id', '=', 'e.user_id')->on('p.organization_id', '=', 'e.organization_id'))
                ->addSelect('e.position_title')->selectRaw('CASE WHEN p.user_id IS NULL THEN 0 ELSE 1 END AS was_primary'),
        };
    }
}
