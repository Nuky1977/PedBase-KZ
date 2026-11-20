<?php

namespace App\Http\Controllers;

use App\Http\Requests\EducationReportRequest;
use App\Policies\OrganizationScope;
use App\Services\EducationReports;
use App\Services\SpreadsheetExport;
use Generator;
use Illuminate\Database\Query\Builder;
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
        $rows = $this->rows($query, $labels[$report]['columns']);
        $filename = "pedbase-{$report}.{$format}";

        if ($format === 'xlsx') {
            return response()->download($spreadsheet->xlsx($rows), $filename, [
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

        return [$report, $school, $year, trans('reports', locale: 'kk'), $reports->query($request->user(), $report, $school, $year)];
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
