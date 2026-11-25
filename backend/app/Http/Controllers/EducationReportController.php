<?php

namespace App\Http\Controllers;

use App\Http\Requests\EducationReportRequest;
use App\Policies\OrganizationScope;
use App\Services\EducationCatalogReports;
use App\Services\EducationReports;
use App\Services\SpreadsheetExport;
use App\Services\SpreadsheetLabels;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EducationReportController extends Controller
{
    public function index(EducationReportRequest $request, EducationReports $reports, OrganizationScope $scope): Response
    {
        [$report, $school, $year, $labels, $query] = $this->report($request, $reports);

        return Inertia::render('reports/Index', [
            'filters' => ['report' => $report, 'school_id' => $school, 'academic_year' => $year],
            'definitions' => $labels,
            'schools' => $scope->schools($request->user())->orderBy('schools.name')->get(['schools.id', 'schools.name']),
            'rows' => $query->simplePaginate(25)->withQueryString(),
            'warningDays' => config('pedbase.attestation_warning_days'),
        ]);
    }

    public function export(EducationReportRequest $request, string $format, EducationReports $reports, SpreadsheetExport $spreadsheet): BinaryFileResponse|StreamedResponse
    {
        [$report, , , $labels, $query] = $this->report($request, $reports);

        return $this->download($format, "pedbase-{$report}.{$format}", $query, $labels[$report]['columns'], $spreadsheet);
    }

    public function catalog(Request $request, EducationCatalogReports $reports): Response
    {
        $filters = $this->catalogFilters($request);

        return Inertia::render('reports/Catalog', [
            'filters' => $filters,
            'definitions' => SpreadsheetLabels::translate(EducationCatalogReports::REPORTS),
            'columns' => SpreadsheetLabels::translate(EducationCatalogReports::COLUMNS),
            'rows' => $reports->query($request->user(), $filters)->simplePaginate(25)->withQueryString(),
        ]);
    }

    public function exportCatalog(Request $request, string $format, EducationCatalogReports $reports, SpreadsheetExport $spreadsheet): BinaryFileResponse|StreamedResponse
    {
        $filters = $this->catalogFilters($request);
        $query = $reports->query($request->user(), $filters);
        $columns = array_intersect_key(EducationCatalogReports::COLUMNS, array_flip(match ($filters['report']) {
            'workloads' => ['organization', 'type', 'region', 'area', 'teacher', 'archived_at', 'position_title', 'was_primary', 'academic_year', 'amount', 'unit', 'period', 'details'],
            'workload_totals' => ['organization', 'type', 'region', 'area', 'archived_at', 'academic_year', 'amount', 'unit', 'period'],
            'placements' => ['organization', 'type', 'region', 'area', 'teacher', 'archived_at', 'position_title', 'was_primary'],
            'categories' => ['organization', 'type', 'region', 'area', 'teacher', 'archived_at', 'category', 'awarded_on', 'valid_until'],
            'courses' => ['organization', 'type', 'region', 'area', 'teacher', 'archived_at', 'course_title', 'provider', 'ended_on', 'hours', 'certificate_issued_on'],
            'workforce' => ['type', 'region', 'area', 'teachers'],
            'profiles' => ['organization', 'type', 'region', 'area', 'teacher', 'archived_at', 'education_level', 'total_experience_months', 'teaching_experience_months'],
            'achievements' => ['organization', 'type', 'region', 'area', 'teacher', 'archived_at', 'award_title', 'issuer', 'achievement_on', 'level'],
        }));

        return $this->download($format, "pedbase-catalog-{$filters['report']}.{$format}", $query, SpreadsheetLabels::translate($columns), $spreadsheet);
    }

    private function catalogFilters(Request $request): array
    {
        abort_unless(config('pedbase.education_catalog_enabled'), 404);

        $filters = $request->validate([
            'report' => ['nullable', Rule::in(array_keys(EducationCatalogReports::REPORTS))],
            'organization_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', 'alpha_dash:ascii', 'max:50'],
            'region' => ['nullable', 'alpha_dash:ascii', 'max:32'],
            'area' => ['nullable', 'alpha_dash:ascii', 'max:32'],
            'year' => ['nullable', 'integer', 'between:1900,9998'],
            'subject' => ['nullable', 'string', 'max:150'],
            'module' => ['nullable', 'string', 'max:150'],
            'unit' => ['nullable', Rule::in(['academic_hour', 'clock_hour', 'fte'])],
            'period' => ['nullable', Rule::in(['week', 'academic_year'])],
            'teacher' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:150'],
            'awarded_from' => ['nullable', 'date_format:Y-m-d'],
            'awarded_until' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('awarded_from'), ['after_or_equal:awarded_from'])],
            'provider' => ['nullable', 'string', 'max:255'],
        ]);
        $filters['report'] ??= 'workloads';

        return $filters;
    }

    private function download(string $format, string $filename, Builder $query, array $columns, SpreadsheetExport $spreadsheet): BinaryFileResponse|StreamedResponse
    {
        $rows = $this->rows($query, $columns);
        if ($format === 'xlsx') {
            return response()->download($spreadsheet->xlsx($rows, keys: array_keys($columns)), $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
            ])->deleteFileAfterSend(true);
        }

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                $safe = array_map(fn ($value) => is_string($value) && preg_match('/^(?:[\t\r\n]|\s*[=+@-])/u', $value) ? "'".$value : $value, $row);
                fputcsv($output, $safe, ',', '"', '');
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function report(EducationReportRequest $request, EducationReports $reports): array
    {
        $report = $request->validated('report', 'schools');
        $school = $request->filled('school_id') ? $request->integer('school_id') : null;
        $year = $request->filled('academic_year') ? $request->integer('academic_year') : null;

        $labels = array_map(fn (array $definition): array => [
            'title' => __($definition['title']),
            'columns' => SpreadsheetLabels::translate($definition['columns']),
        ], trans('reports', locale: 'kk'));

        return [$report, $school, $year, $labels, $reports->query($request->user(), $report, $school, $year)];
    }

    private function rows(Builder $query, array $columns): Generator
    {
        yield array_values($columns);
        foreach ($query->lazy(500) as $row) {
            yield array_map(fn (string $key) => match ($key) {
                'hours' => (float) $row->{$key},
                'teachers', 'general_known', 'teaching_known', 'courses', 'primary_count', 'secondary_count',
                'total_months', 'teaching_months', 'academic_year' => (int) $row->{$key},
                default => $row->{$key},
            }, array_keys($columns));
        }
    }
}
