<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\EducationOrganization;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EducationOrganizationRegistration
{
    public const TYPES = [
        'school' => 'Жалпы орта білім беру',
        'college' => 'Техникалық және кәсіптік білім беру',
        'preschool' => 'Мектепке дейінгі тәрбие мен оқыту',
        'supplementary' => 'Қосымша білім беру',
        'special' => 'Арнайы білім беру',
    ];

    private const COLUMNS = [
        'name' => 'Атауы',
        'short_name' => 'Қысқаша атауы',
        'bin' => 'БСН',
        'type_code' => 'Ұйым түрінің коды',
        'region_code' => 'Облыс коды',
        'area_code' => 'Аудан немесе қала коды',
        'locality' => 'Елді мекен',
        'status' => 'Мәртебе',
    ];

    public function __construct(private SpreadsheetImportReader $reader) {}

    public function formData(): array
    {
        return [
            'types' => DB::table('education_organization_types')->orderBy('name')->get(['code', 'name']),
            'regions' => DB::table('education_regions')->orderBy('name')->get(['id', 'code', 'name']),
            'areas' => DB::table('education_areas')->orderBy('name')->get(['code', 'region_id', 'name']),
        ];
    }

    public function template(string $type, SpreadsheetExport $export): string
    {
        return $export->template(self::COLUMNS, [
            [__('Ұйымдарды тіркеу')],
            [__('Ұйым түрінің коды: :code', ['code' => $type])],
            [__('БСН-ді және ресми аумақ кодтарын Excel-де мәтін ретінде енгізіңіз. БСН 12 цифрдан тұрады.')],
            [__('Мәртебе: active, inactive немесе archived. Қысқаша атау міндетті емес.')],
            [__('100 жолға дейін. Белгісіз аумақты әкімші анықтамалық бойынша қолмен сәйкестендіреді.')],
            [__('Құпиясөздер мен жеке деректерді қоспаңыз.')],
        ], array_diff(array_keys(self::COLUMNS), ['short_name']), [
            'type_code' => array_values(array_unique([$type, ...array_keys(self::TYPES)])),
            'status' => ['active', 'inactive', 'archived'],
        ]);
    }

    public function save(array $input, ?int $id = null): EducationOrganization
    {
        try {
            return DB::transaction(function () use ($input, $id) {
                $organization = $id === null
                    ? new EducationOrganization
                    : EducationOrganization::query()->lockForUpdate()->findOrFail($id);
                $data = $this->validate($input, $id);
                if ($organization->exists && (int) $organization->type_id !== (int) $data['type_id']
                    && DB::table('education_employments')->where('organization_id', $organization->id)->exists()) {
                    throw ValidationException::withMessages(['type_code' => __('Еңбек тарихы бар ұйымның түрін өзгертуге болмайды.')]);
                }
                $this->persist($organization, $data);
                AuditEvent::record($id === null ? 'organization.created' : 'organization.updated', 'education_organization', $organization->id);

                return $organization;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['bin' => __('Бұл БСН каталогта бар.')]);
        }
    }

    public function inspect(string $path): array
    {
        $worksheet = $this->reader->read($path);
        $header = $worksheet[1] ?? [];
        $columns = SpreadsheetLabels::matchHeader(self::COLUMNS, $header);
        if ($columns === null) {
            throw ValidationException::withMessages(['file' => __('Бірінші жолда ұйым тіркеу үлгісінің барлық бағаны бір рет берілуі керек.')]);
        }
        unset($worksheet[1]);
        if ($worksheet === []) {
            throw ValidationException::withMessages(['file' => __('Импорттайтын дерек жолдары жоқ.')]);
        }

        $keys = array_flip($columns);
        $rows = $valid = $seen = [];
        foreach ($worksheet as $number => $cells) {
            $input = [];
            foreach ($cells as $column => $value) {
                $input[$keys[$header[$column]]] = trim($value);
            }
            $rows[$number] = [
                'number' => $number,
                'name' => $input['name'] ?? '',
                'bin' => $input['bin'] ?? '',
                'type' => $input['type_code'] ?? '',
                'area' => $input['area_code'] ?? '',
                'errors' => [],
            ];
            try {
                $valid[$number] = $this->validate($input);
            } catch (ValidationException $exception) {
                $rows[$number]['errors'] = array_merge(...array_values($exception->errors()));
            }
            if (preg_match('/^[0-9]{12}$/D', $rows[$number]['bin'])) {
                $seen[$rows[$number]['bin']][] = $number;
            }
        }
        foreach ($seen as $numbers) {
            if (count($numbers) > 1) {
                foreach ($numbers as $number) {
                    $rows[$number]['errors'][] = __('БСН файлда қайталанған.');
                }
            }
        }

        return ['rows' => array_values($rows), 'valid' => $valid, 'has_errors' => collect($rows)->contains(fn (array $row) => $row['errors'] !== [])];
    }

    public function import(string $path): int
    {
        return DB::transaction(function () use ($path) {
            $result = $this->inspect($path);
            if ($result['has_errors']) {
                $errors = [];
                foreach ($result['rows'] as $row) {
                    if ($row['errors'] !== []) {
                        $errors[] = $row['number'].'-жол: '.implode(' ', $row['errors']);
                    }
                }
                throw ValidationException::withMessages(['file' => $errors]);
            }
            foreach ($result['valid'] as $number => $data) {
                try {
                    $this->persist(new EducationOrganization, $data);
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages(['file' => __(__(':row-жол: бұл БСН каталогта бар.'), ['row' => $number])]);
                }
            }
            AuditEvent::record('spreadsheet.import.education_organizations', 'spreadsheet_import', 0);

            return count($result['valid']);
        }, 3);
    }

    private function validate(array $input, ?int $id = null): array
    {
        $bin = Rule::unique('education_organizations', 'bin');
        if ($id !== null) {
            $bin->ignore($id);
        }
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'bin' => ['required', 'regex:/^[0-9]{12}$/D', $bin],
            'type_code' => ['required', 'alpha_dash:ascii', 'max:50'],
            'region_code' => ['required', 'alpha_dash:ascii', 'max:32'],
            'area_code' => ['required', 'alpha_dash:ascii', 'max:32'],
            'locality' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
        ], [
            'required' => __('«:attribute» өрісі міндетті.'),
            'string' => __('«:attribute» мәтін болуы керек.'),
            'max' => __('«:attribute» өрісі :max таңбадан аспауы керек.'),
            'alpha_dash' => __('«:attribute» кодының пішімі жарамсыз.'),
            'bin.regex' => __('БСН дәл 12 цифрдан тұруы керек.'),
            'bin.unique' => __('Бұл БСН каталогта бар.'),
            'status.in' => __('Мәртебе active, inactive немесе archived болуы керек.'),
        ], self::COLUMNS);
        $type = $area = null;
        $validator->after(function ($validator) use ($input, &$type, &$area) {
            if (! $validator->errors()->has('type_code')) {
                $type = DB::table('education_organization_types')->where('code', $input['type_code'])->value('id');
                if ($type === null) {
                    $validator->errors()->add('type_code', __('Ұйым түрінің коды анықтамалықта жоқ.'));
                }
            }
            if (! $validator->errors()->has('region_code') && ! $validator->errors()->has('area_code')) {
                $region = DB::table('education_regions')->where('code', $input['region_code'])->value('id');
                if ($region === null) {
                    $validator->errors()->add('region_code', __('Облыс коды анықтамалықта жоқ.'));

                    return;
                }
                $area = DB::table('education_areas')->where('region_id', $region)->where('code', $input['area_code'])->value('id');
                if ($area === null) {
                    $validator->errors()->add('area_code', __('Аудан немесе қала коды осы облысқа тиесілі емес. Сәйкестікті қолмен тексеріңіз.'));
                }
            }
        });
        $data = $validator->validate();

        return [
            'name' => $data['name'],
            'short_name' => ($data['short_name'] ?? '') === '' ? null : $data['short_name'],
            'bin' => $data['bin'],
            'locality' => $data['locality'],
            'type_id' => $type,
            'area_id' => $area,
            'status' => $data['status'],
        ];
    }

    private function persist(EducationOrganization $organization, array $data): void
    {
        $status = $data['status'];
        unset($data['status']);
        $organization->fill($data)->forceFill([
            'is_active' => $status === 'active',
            'archived_at' => $status === 'archived' ? ($organization->archived_at ?? now()) : null,
        ])->save();
    }
}
