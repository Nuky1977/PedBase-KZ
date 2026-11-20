<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpreadsheetImportRequest;
use App\Services\SpreadsheetExport;
use App\Services\SpreadsheetImport;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SpreadsheetImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Imports', [
            'columns' => array_map('array_values', SpreadsheetImport::COLUMNS),
        ]);
    }

    public function template(string $kind, SpreadsheetExport $export): BinaryFileResponse
    {
        $columns = array_values(SpreadsheetImport::COLUMNS[$kind]);
        $example = $kind === 'schools'
            ? ['Үлгі мектебі', 'ҮМ', '000000000001', 'Мектеп', 'Үлгі елді мекені', 'Иә']
            : ['Үлгі Педагог', 'ulgi_pedagog', 'teacher@example.invalid', '000000000001', 'MAT', 'Мұғалім', '1,50', 2026];
        $instructions = [
            ['Нұсқаулық'],
            ['Бірінші парақты толтырыңыз. Бірінші жолдағы баған атауларын сақтаңыз.'],
            ['Мысал тек осы парақта. Үлгі деректерін жүктемеңіз.'],
            ['БСН: дәл 12 цифр. Алдыңғы нөл сақталуы үшін Excel-де мәтін пішімін қолданыңыз.'],
            ['Ең көбі 100 дерек жолы және 2 МБ. Формула, макрос, сыртқы байланыс, ЖСН немесе құпиясөз қоспаңыз.'],
            ...($kind === 'schools' ? [
                ['Атауы, БСН және белсенділік міндетті. Белсенділікке «Иә» немесе «Жоқ» енгізіңіз.'],
            ] : [
                ['Мектеп БСН-і белсенді мектепке, пән коды бар пәнге сәйкес келуі керек.'],
                ['Әр пәнді бөлек жолға жазыңыз. Бір педагогтің логині, аты-жөні және email-ы бірдей болсын.'],
                ['Педагогтің бірінші мектебі негізгі жұмыс орны болады. 2026 — 2026–2027 оқу жылы.'],
            ]),
            $columns,
            $example,
        ];
        $path = $export->xlsx([$columns], $instructions);

        return response()->download($path, "pedbase-{$kind}-template.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    public function preview(SpreadsheetImportRequest $request, string $kind, SpreadsheetImport $import): JsonResponse
    {
        $path = $request->file('file')->getPathname();
        try {
            $result = $import->inspect($kind, $path);
        } catch (QueryException) {
            throw ValidationException::withMessages(['file' => 'Тексеру аяқталмады. Файлды кейін қайта тексеріңіз.']);
        }
        $token = $result['errors'] === [] ? Crypt::encrypt([
            'actor' => $request->user()->id,
            'kind' => $kind,
            'digest' => hash_file('sha256', $path),
            'expires' => now()->addMinutes(15)->timestamp,
        ]) : null;

        return response()->json($result['preview'] + ['token' => $token])->header('Cache-Control', 'private, no-store');
    }

    public function confirm(SpreadsheetImportRequest $request, string $kind, SpreadsheetImport $import): JsonResponse
    {
        $path = $request->file('file')->getPathname();
        try {
            $token = Crypt::decrypt($request->validated('token'));
        } catch (DecryptException) {
            $token = null;
        }
        if (! is_array($token) || ($token['actor'] ?? null) !== $request->user()->id || ($token['kind'] ?? null) !== $kind
            || ($token['expires'] ?? 0) <= now()->timestamp || ($token['digest'] ?? null) !== hash_file('sha256', $path)) {
            throw ValidationException::withMessages(['file' => 'Алдын ала тексерілген файл сәйкес келмейді немесе растау мерзімі аяқталған.']);
        }
        try {
            $created = $import->save($kind, $path);
        } catch (QueryException) {
            // SQL exceptions can contain original import values.
            throw ValidationException::withMessages(['file' => 'Импорт сақталмады. Қайталанған жазбалар мен анықтамалықтарды қайта тексеріңіз.']);
        }

        return response()->json(['created' => $created])->header('Cache-Control', 'private, no-store');
    }
}
