<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\UserRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EducationWorkloadImport
{
    public const TYPES = ['college', 'preschool', 'supplementary', 'special'];

    private const COMMON = [
        'bin' => 'Ұйым БСН', 'area' => 'Аумақ коды', 'teacher' => 'Педагог логині',
        'position' => 'Лауазымы', 'placement' => 'Жұмыс орны', 'year' => 'Оқу жылы',
        'amount' => 'Жүктеме', 'unit' => 'Өлшем бірлігі', 'period' => 'Есептеу кезеңі',
    ];

    private const DETAILS = [
        'college' => [
            'specialty_code' => 'Мамандық коды', 'specialty_name' => 'Мамандық атауы',
            'qualification_code' => 'Біліктілік коды', 'qualification_name' => 'Біліктілік атауы',
            'program' => 'Білім беру бағдарламасы', 'module' => 'Кәсіптік модуль', 'subject' => 'Жалпы білім беретін пән',
        ],
        'preschool' => ['direction' => 'Тәрбие және оқыту бағыты', 'age_group' => 'Жас тобы', 'category' => 'Біліктілік санаты'],
        'supplementary' => ['club' => 'Үйірме', 'section' => 'Секция', 'program' => 'Білім беру бағдарламасы', 'direction' => 'Педагог бағыты'],
        'special' => ['program' => 'Білім беру бағдарламасы', 'direction' => 'Педагог бағыты'],
    ];

    public function __construct(private SpreadsheetImportReader $reader, private CollegeQualificationReference $qualifications) {}

    public function columns(string $type): array
    {
        return self::COMMON + self::DETAILS[$type];
    }

    public function template(string $type, SpreadsheetExport $export): string
    {
        return $export->xlsx([array_values(SpreadsheetLabels::translate($this->columns($type)))], [
            [__('Ұйым түрі: :type', ['type' => $type])],
            [__('Тіркелген педагог логинін пайдаланыңыз. Жұмыс орны: primary немесе secondary.')],
            [__('Өлшем: academic_hour, clock_hour немесе fte. Кезең: week немесе academic_year.')],
            [__('Қосымша білім беру жүктемесі үшін week қолданыңыз.')],
            [__('БСН мен аумақ кодын мәтін түрінде енгізіңіз. 100 жолға дейін.')],
            [__('Мамандық және біліктілік кодтарын бекітілген дереккөзден алыңыз. Құпия ақпаратты енгізбеңіз.')],
        ]);
    }

    public function inspect(string $type, string $path, bool $lock = false): array
    {
        $columns = $this->columns($type);
        $sheet = $this->reader->read($path, count($columns));
        $header = $sheet[1] ?? [];
        $columns = SpreadsheetLabels::matchHeader($columns, $header);
        if ($columns === null) {
            throw ValidationException::withMessages(['file' => __('Таңдалған ұйым түрінің XLSX үлгісіндегі бағандарды пайдаланыңыз.')]);
        }
        unset($sheet[1]);
        if ($sheet === []) {
            throw ValidationException::withMessages(['file' => __('Импорттайтын жолдар жоқ.')]);
        }
        $keys = array_flip($columns);
        $rows = $valid = $seen = $placements = $primary = [];
        foreach ($sheet as $number => $cells) {
            $input = [];
            foreach ($cells as $column => $value) {
                $input[$keys[$header[$column]]] = trim($value);
            }
            $rows[$number] = ['number' => $number, 'name' => $input['position'] ?? '', 'bin' => $input['bin'] ?? '',
                'type' => $type, 'area' => $input['area'] ?? '', 'errors' => []];
            try {
                $data = $this->validateRow($type, $input, $lock);
                $placementKey = $data['teacher_id'].':'.$data['organization_id'];
                $placement = [$data['position_title'], $data['was_primary']];
                if (isset($placements[$placementKey]) && $placements[$placementKey] !== $placement) {
                    $this->invalid('Жұмыс орны немесе лауазым файлдағы басқа жолмен қайшы.');
                }
                if ($data['was_primary'] && isset($primary[$data['teacher_id']]) && $primary[$data['teacher_id']] !== $data['organization_id']) {
                    $this->invalid('Бір педагогке екі негізгі жұмыс орнын белгілеуге болмайды.');
                }
                $placements[$placementKey] = $placement;
                if ($data['was_primary']) {
                    $primary[$data['teacher_id']] = $data['organization_id'];
                }
                if (isset($seen[$data['identity']])) {
                    $rows[$seen[$data['identity']]]['errors'][] = __('Жүктеме жолы файлда қайталанған.');
                    $this->invalid('Жүктеме жолы файлда қайталанған.');
                }
                $seen[$data['identity']] = $number;
                $valid[$number] = $data;
            } catch (ValidationException $exception) {
                $rows[$number]['errors'] = array_merge(...array_values($exception->errors()));
            }
        }

        return ['rows' => array_values($rows), 'valid' => $valid, 'has_errors' => collect($rows)->contains(fn (array $row) => $row['errors'] !== [])];
    }

    public function import(string $type, string $path): int
    {
        return DB::transaction(function () use ($type, $path) {
            $result = $this->inspect($type, $path, lock: true);
            if ($result['has_errors']) {
                $errors = [];
                foreach ($result['rows'] as $row) {
                    if ($row['errors'] !== []) {
                        $errors[] = $row['number'].'-жол: '.implode(' ', $row['errors']);
                    }
                }
                throw ValidationException::withMessages(['file' => $errors]);
            }
            foreach ($result['valid'] as $data) {
                $this->persist($data);
            }
            AuditEvent::record('spreadsheet.import.education_workloads', 'spreadsheet_import', 0);

            return count($result['valid']);
        }, 3);
    }

    private function validateRow(string $type, array $input, bool $lock): array
    {
        if (isset($input['amount'])) {
            $input['amount'] = str_replace(',', '.', $input['amount']);
        }
        $rules = [
            'bin' => ['required', 'digits:12'], 'area' => ['required', 'alpha_dash:ascii', 'max:32'],
            'teacher' => ['required', 'alpha_dash:ascii', 'max:100'], 'position' => ['required', 'string', 'max:150'],
            'placement' => ['required', Rule::in(['primary', 'secondary'])], 'year' => ['required', 'integer', 'between:1900,9998'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,999999.99'],
            'unit' => ['required', Rule::in(['academic_hour', 'clock_hour', 'fte'])],
            'period' => ['required', Rule::in($type === 'supplementary' ? ['week'] : ['week', 'academic_year'])],
        ];
        foreach (self::DETAILS[$type] as $key => $label) {
            $rules[$key] = match ($key) {
                'module' => ['required_without:subject', 'nullable', 'string', 'max:150'],
                'subject' => ['required_without:module', 'nullable', 'string', 'max:150'],
                'club' => ['required_without:section', 'nullable', 'string', 'max:150'],
                'section' => ['required_without:club', 'nullable', 'string', 'max:150'],
                'specialty_code', 'qualification_code' => ['required', 'alpha_dash:ascii', 'max:32'],
                default => ['required', 'string', 'max:150'],
            };
        }
        $data = Validator::make($input, $rules, [
            'required' => __('«:attribute» өрісі міндетті.'),
            'required_without' => __('«:attribute» немесе «:values» өрісін толтырыңыз.'),
            'in' => __('«:attribute» мәні жарамсыз.'),
        ], $this->columns($type))->validate();
        $officialNames = $type === 'college'
            ? $this->qualifications->validate($data['specialty_code'], $data['qualification_code'])
            : null;
        $teacher = DB::table('users')->where('username', $data['teacher'])->where('role', UserRole::TEACHER->value)
            ->when($lock, fn (Builder $q) => $q->lockForUpdate())->first(['id']);
        if ($teacher === null) {
            $this->invalid('Осы логинмен тіркелген педагог табылмады.');
        }
        $organization = DB::table('education_organizations as o')->join('education_organization_types as t', 'o.type_id', '=', 't.id')
            ->join('education_areas as a', 'o.area_id', '=', 'a.id')->where('o.bin', $data['bin'])
            ->where('t.code', $type)->where('a.code', $data['area'])->where('o.is_active', true)
            ->whereNull('o.archived_at')->when($lock, fn (Builder $q) => $q->lockForUpdate())->first(['o.id']);
        if ($organization === null) {
            $this->invalid('БСН, түр және аумақ бойынша белсенді ұйым табылмады.');
        }
        $employment = DB::table('education_employments')->where('user_id', $teacher->id)->where('organization_id', $organization->id)
            ->when($lock, fn (Builder $q) => $q->lockForUpdate())->first();
        if ($employment !== null && ($employment->status !== 'active' || $employment->position_title !== $data['position'])) {
            $this->invalid('Қолданыстағы еңбек жазбасының күйін және лауазымын тексеріңіз.');
        }
        $primary = DB::table('education_primary_workplaces')->where('user_id', $teacher->id)
            ->when($lock, fn (Builder $q) => $q->lockForUpdate())->value('organization_id');
        $isPrimary = $data['placement'] === 'primary';
        if ($isPrimary && DB::table('school_user')->where('user_id', $teacher->id)->where('is_primary', true)->exists()) {
            $this->invalid('Педагогтің бұрынғы мектеп жүйесінде негізгі жұмыс орны бар.');
        }
        if ($primary !== null && (((int) $primary === (int) $organization->id) !== $isPrimary)) {
            $this->invalid('Расталған негізгі жұмыс орнымен қайшылық бар.');
        }
        $details = array_intersect_key($data, self::DETAILS[$type]);
        ksort($details);
        $encodedDetails = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $identity = hash('sha256', json_encode([$teacher->id, $organization->id, (int) $data['year'], $data['unit'], $data['period'], $encodedDetails], JSON_THROW_ON_ERROR));
        if (DB::table('education_workloads')->where('identity', $identity)->exists()) {
            $this->invalid('Осы жүктеме бұрын тіркелген.');
        }
        if ($officialNames !== null) {
            $details['classifier_source'] = CollegeQualificationReference::SOURCE;
            $details['classifier_revision'] = CollegeQualificationReference::REVISION;
            $details['classifier_specialty_name'] = implode(' / ', $officialNames['specialty']);
            $details['classifier_qualification_name'] = implode(' / ', $officialNames['qualification']);
            $encodedDetails = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return [
            'teacher_id' => $teacher->id, 'organization_id' => $organization->id,
            'employment_id' => $employment?->id, 'academic_year' => (int) $data['year'],
            'position_title' => $data['position'], 'was_primary' => $isPrimary,
            'amount' => $data['amount'], 'unit' => $data['unit'], 'period' => $data['period'],
            'details' => $encodedDetails, 'identity' => $identity,
        ];
    }

    private function persist(array $data): void
    {
        $timestamp = now();
        $placement = ['user_id' => $data['teacher_id'], 'organization_id' => $data['organization_id']];
        $employment = DB::table('education_employments')->where($placement)->first(['id']);
        $employmentId = $employment?->id ?? DB::table('education_employments')->insertGetId($placement + [
            'position_title' => $data['position_title'], 'status' => 'active', 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        if ($data['was_primary'] && ! DB::table('education_primary_workplaces')->where('user_id', $data['teacher_id'])->exists()) {
            DB::table('education_primary_workplaces')->insert($placement);
        }
        unset($data['teacher_id'], $data['organization_id']);
        $data['employment_id'] = $employmentId;
        DB::table('education_workloads')->insert($data + ['created_at' => $timestamp, 'updated_at' => $timestamp]);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['row' => __($message)]);
    }
}
