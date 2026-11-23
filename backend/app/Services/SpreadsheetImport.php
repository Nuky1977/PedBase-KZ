<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\School;
use App\Models\Subject;
use App\Models\TeacherEmployment;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;

class SpreadsheetImport
{
    public const COLUMNS = [
        'schools' => ['name' => 'Атауы', 'short_name' => 'Қысқаша атауы', 'bin' => 'БСН', 'type' => 'Түрі', 'locality' => 'Елді мекені', 'is_active' => 'Белсенді'],
        'teachers' => ['name' => 'Аты-жөні', 'username' => 'Логин', 'email' => 'Email', 'bin' => 'Мектеп БСН', 'subject' => 'Пән коды', 'position_title' => 'Лауазымы', 'weekly_hours' => 'Апталық жүктеме', 'academic_year' => 'Оқу жылы'],
    ];

    public const CATALOG_TEACHERS = 'education_teachers';

    public const CATALOG_TEACHER_COLUMNS = [
        'name' => 'Аты-жөні', 'username' => 'Логин', 'email' => 'Email',
        'bin' => 'Ұйым БСН', 'type' => 'Ұйым түрінің коды', 'area' => 'Аумақ коды',
        'position_title' => 'Лауазымы', 'placement' => 'Жұмыс орны',
    ];

    public function __construct(private SpreadsheetImportReader $reader) {}

    public function inspect(string $kind, string $path, bool $lock = false): array
    {
        $data = $this->reader->read($path);
        $columns = $kind === self::CATALOG_TEACHERS ? self::CATALOG_TEACHER_COLUMNS : self::COLUMNS[$kind];
        $headers = $data[1] ?? [];
        if ($kind === self::CATALOG_TEACHERS && ! in_array($columns['placement'], $headers, true)) {
            unset($columns['placement']);
        }
        if (count($headers) !== count($columns) || array_diff($headers, $columns) || count(array_unique($headers)) !== count($columns)) {
            throw ValidationException::withMessages(['file' => 'Бірінші жолда үлгідегі барлық баған атауы дәл бір рет берілуі керек.']);
        }
        unset($data[1]);
        if ($data === []) {
            throw ValidationException::withMessages(['file' => 'Импорттайтын дерек жолдары жоқ.']);
        }

        $map = array_map(fn (string $header) => array_search($header, $columns, true), $headers);
        $valid = [];
        $errors = [];
        foreach ($data as $number => $cells) {
            if (array_diff_key($cells, $map)) {
                $errors[$number][] = 'Үлгіде көрсетілмеген бағанға мән енгізілген.';

                continue;
            }
            $row = array_fill_keys(array_keys($columns), null);
            foreach ($cells as $column => $value) {
                $row[$map[$column]] = $value;
            }
            $row = $this->normalize($kind, $row);
            $validator = Validator::make($row, $this->rules($kind), [
                'required' => '«:attribute» бағанын толтырыңыз.',
                'max' => '«:attribute» мәні :max таңбадан аспауы керек.',
                'digits' => '«:attribute» дәл :digits цифрдан тұруы керек.',
                'alpha_dash' => '«:attribute» үшін латын әрпі, сан, дефис немесе астыңғы сызық қолданыңыз.',
                'email' => 'Email пішімі жарамсыз.',
                'ascii' => 'Email үшін ASCII таңбаларын қолданыңыз.',
                'is_active.in' => '«Белсенді» бағанына «Иә» немесе «Жоқ» енгізіңіз.',
                'type.in' => 'Ұйым түрінің коды жарамсыз.',
                'integer' => '«:attribute» бағанына бүтін сан енгізіңіз.',
                'numeric' => '«:attribute» бағанына сан енгізіңіз.',
                'decimal' => 'Апталық жүктемеде екі ондық таңбадан артық болмауы керек.',
                'between' => '«:attribute» мәні :min мен :max арасында болуы керек.',
                'regex' => '«Оқу жылы» бағанына төрт таңбалы басталу жылын енгізіңіз.',
            ], $columns);
            if ($validator->fails()) {
                $errors[$number] = $validator->errors()->all();

                continue;
            }
            $valid[$number] = $validator->validated();
        }

        $primary = [];
        if ($kind === 'schools') {
            $this->checkSchools($valid, $errors, $lock);
        } elseif ($kind === self::CATALOG_TEACHERS) {
            $this->checkCatalogTeachers($valid, $errors, $lock);
        } else {
            $this->checkTeachers($valid, $errors, $primary, $lock);
        }
        $accepted = array_diff_key($valid, $errors);
        $placementCount = 0;
        $hoursByYear = [];
        if ($kind === 'teachers' && $errors === []) {
            $placements = [];
            foreach ($accepted as $row) {
                $placements[$row['username']][$row['school_id']] = true;
                $year = (int) $row['academic_year'];
                $hoursByYear[$year] = ($hoursByYear[$year] ?? 0) + (int) round((float) $row['weekly_hours'] * 100);
            }
            $placementCount = array_sum(array_map('count', $placements));
            ksort($hoursByYear);
        }

        return [
            'data' => $valid,
            'errors' => $errors,
            'preview' => [
                'rows' => collect(array_keys($data))->map(fn (int $number) => [
                    'number' => $number,
                    'errors' => $errors[$number] ?? [],
                    'primary' => isset($primary[$number]) && ! isset($errors[$number]),
                ])->all(),
                'total' => count($data),
                'valid' => count($data) - count($errors),
                'new_records' => $kind === 'schools' ? count($accepted) : count(array_unique(array_column($accepted, 'username'))),
                'placements' => $placementCount,
                'hours_by_year' => array_map(fn (int $year, int $hours) => [
                    'year' => $year,
                    'hours' => number_format($hours / 100, 2, ',', ''),
                ], array_keys($hoursByYear), array_values($hoursByYear)),
            ],
        ];
    }

    public function save(string $kind, string $path): int
    {
        return DB::transaction(function () use ($kind, $path) {
            $result = $this->inspect($kind, $path, lock: true);
            if ($result['errors'] !== []) {
                throw ValidationException::withMessages(['file' => 'Деректерді сақтау мүмкін болмады. Файлды қайта тексеріңіз.']);
            }

            if ($kind === 'schools') {
                foreach ($result['data'] as $row) {
                    School::create($row);
                }
            } elseif ($kind === self::CATALOG_TEACHERS) {
                $this->saveCatalogTeachers($result['data']);
            } else {
                $this->saveTeachers($result['data']);
            }

            AuditEvent::record('spreadsheet.import.'.$kind, 'spreadsheet_import', 0);

            return $result['preview']['new_records'];
        }, 3);
    }

    private function rules(string $kind): array
    {
        if ($kind === 'schools') {
            return [
                'name' => ['required', 'string', 'max:255'],
                'short_name' => ['nullable', 'string', 'max:255'],
                'bin' => ['required', 'digits:12'],
                'type' => ['nullable', 'string', 'max:50'],
                'locality' => ['nullable', 'string', 'max:255'],
                'is_active' => ['required', 'in:0,1'],
            ];
        }

        $account = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'alpha_dash:ascii', 'max:100'],
            'email' => ['required', 'ascii', 'email', 'max:255'],
            'bin' => ['required', 'digits:12'],
            'position_title' => ['required', 'string', 'max:150'],
        ];
        if ($kind === self::CATALOG_TEACHERS) {
            return $account + [
                'type' => ['required', Rule::in(array_keys(EducationOrganizationRegistration::TYPES))],
                'area' => ['required', 'alpha_dash:ascii', 'max:32'],
                'placement' => ['nullable', Rule::in(['primary', 'secondary'])],
            ];
        }

        return $account + [
            'subject' => ['required', 'alpha_dash:ascii', 'max:50'],
            'weekly_hours' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,999.99'],
            'academic_year' => ['required', 'regex:/^[0-9]{4}$/D', 'integer', 'between:1900,9998'],
        ];
    }

    private function normalize(string $kind, array $row): array
    {
        if ($kind === 'schools') {
            $row['is_active'] = match (Str::lower($row['is_active'] ?? '')) {
                '1', 'иә', 'true' => '1',
                '0', 'жоқ', 'false' => '0',
                default => $row['is_active'],
            };
        } else {
            $row['username'] = strtolower($row['username'] ?? '');
            $row['email'] = strtolower($row['email'] ?? '');
            $row['subject'] = strtoupper($row['subject'] ?? '');
            $row['weekly_hours'] = str_replace(',', '.', $row['weekly_hours'] ?? '');
        }

        return $row;
    }

    private function checkSchools(array $rows, array &$errors, bool $lock): void
    {
        $existing = School::whereIn('bin', array_column($rows, 'bin'))->when($lock, fn ($query) => $query->lockForUpdate())->pluck('bin')->flip();
        $seen = [];
        foreach ($rows as $number => $row) {
            $bin = $row['bin'];
            if ($existing->has($bin)) {
                $errors[$number][] = 'Бұл БСН дерекқорда бұрыннан бар.';
            }
            if (isset($seen[$bin])) {
                $errors[$number][] = 'Бұл БСН файлда қайталанған.';
                $errors[$seen[$bin]][] = 'Бұл БСН файлда қайталанған.';
            } else {
                $seen[$bin] = $number;
            }
        }
    }

    private function checkTeachers(array &$rows, array &$errors, array &$primary, bool $lock): void
    {
        $schools = School::whereIn('bin', array_column($rows, 'bin'))->where('is_active', true)
            ->when($lock, fn ($query) => $query->orderBy('id')->lockForUpdate())->pluck('id', 'bin');
        $subjects = Subject::whereIn('code', array_column($rows, 'subject'))
            ->when($lock, fn ($query) => $query->orderBy('id')->lockForUpdate())->pluck('id', 'code');
        [$logins, $emails] = $this->existingAccounts($rows);
        $teachers = $usedEmails = $positions = $assignments = [];

        foreach ($rows as $number => &$row) {
            $login = $row['username'];
            $email = $row['email'];
            $school = $row['school_id'] = $schools->get($row['bin']);
            $subject = $row['subject_id'] = $subjects->get($row['subject']);
            if ($school === null) {
                $errors[$number][] = 'БСН бойынша белсенді мектеп табылмады.';
            }
            if ($subject === null) {
                $errors[$number][] = 'Пән коды анықтамалықтан табылмады.';
            }
            if ($logins->has($login) || $emails->has($email)) {
                $errors[$number][] = 'Логин немесе email дерекқорда бұрыннан бар.';
            }
            if (isset($teachers[$login]) && $teachers[$login] !== [$email, $row['name']]) {
                $errors[$number][] = 'Бір логин үшін аты-жөні мен email бірдей болуы керек.';
            }
            if (isset($usedEmails[$email]) && $usedEmails[$email] !== $login) {
                $errors[$number][] = 'Бір email бірнеше логинге берілген.';
            }
            if (! isset($teachers[$login])) {
                $primary[$number] = true;
            }
            $teachers[$login] ??= [$email, $row['name']];
            $usedEmails[$email] ??= $login;
            $placement = $login.'|'.$row['bin'];
            if (isset($positions[$placement]) && $positions[$placement] !== $row['position_title']) {
                $errors[$number][] = 'Бір мектептегі лауазым барлық жолда бірдей болуы керек.';
            }
            $positions[$placement] ??= $row['position_title'];
            $assignment = $placement.'|'.$row['subject'].'|'.$row['academic_year'];
            if (isset($assignments[$assignment])) {
                $errors[$number][] = 'Бұл мектеп, пән және оқу жылы қайталанған.';
            }
            $assignments[$assignment] = true;
        }
        unset($row);
    }

    private function existingAccounts(array $rows): array
    {
        $accounts = User::query()->where(function ($query) use ($rows) {
            $query->whereIn(DB::raw('LOWER(username)'), array_column($rows, 'username'))
                ->orWhereIn(DB::raw('LOWER(email)'), array_column($rows, 'email'));
        })->get(['username', 'email']);

        return [
            $accounts->pluck('username')->filter()->map(fn ($value) => strtolower($value))->flip(),
            $accounts->pluck('email')->map(fn ($value) => strtolower($value))->flip(),
        ];
    }

    private function checkCatalogTeachers(array &$rows, array &$errors, bool $lock): void
    {
        if (! Features::enabled(Features::resetPasswords())) {
            throw ValidationException::withMessages(['file' => 'Педагогті тіркеу үшін құпиясөзді қалпына келтіру қызметі қосулы болуы керек.']);
        }
        $organizations = DB::table('education_organizations as o')
            ->join('education_organization_types as t', 't.id', '=', 'o.type_id')
            ->join('education_areas as a', 'a.id', '=', 'o.area_id')
            ->whereIn('o.bin', array_column($rows, 'bin'))->where('o.is_active', true)->whereNull('o.archived_at')
            ->when($lock, fn ($query) => $query->orderBy('o.id')->lockForUpdate())
            ->get(['o.id', 'o.bin', 't.code as type', 'a.code as area'])->keyBy('bin');
        [$existingLogins, $existingEmails] = $this->existingAccounts($rows);
        $logins = $emails = $placements = $primary = $firstRows = [];
        foreach ($rows as $number => &$row) {
            $login = $row['username'];
            $email = $row['email'];
            $names = [$email, $row['name']];
            $firstRows[$login] ??= $number;
            if ($existingLogins->has($login) || $existingEmails->has($email)) {
                $errors[$number][] = 'Логин немесе email дерекқорда бұрыннан бар.';
            }
            if ((isset($logins[$login]) && $logins[$login] !== $names) || (isset($emails[$email]) && $emails[$email] !== $login)) {
                $errors[$number][] = 'Файлдағы логин, email және аты-жөн сәйкестігін тексеріңіз.';
            }
            $row['placement'] = ($row['placement'] ?? null) ?: (isset($logins[$login]) ? 'secondary' : 'primary');
            if ($row['placement'] === 'primary') {
                if (isset($primary[$login])) {
                    $errors[$number][] = 'Бір педагогке бір ғана негізгі жұмыс орны беріледі.';
                }
                $primary[$login] = true;
            }
            $logins[$login] ??= $names;
            $emails[$email] ??= $login;
            if (isset($placements[$login][$row['bin']])) {
                $errors[$number][] = 'Осы ұйымдағы жұмыс орны файлда қайталанған.';
            }
            $placements[$login][$row['bin']] = true;
            $organization = $organizations->get($row['bin']);
            if ($organization === null || $organization->area !== $row['area'] || $organization->type !== $row['type']) {
                $errors[$number][] = 'БСН, түр және аумақ бойынша белсенді ұйым табылмады.';

                continue;
            }
            $row['organization_id'] = $organization->id;
        }
        unset($row);
        foreach (array_diff_key($firstRows, $primary) as $number) {
            $errors[$number][] = 'Негізгі жұмыс орнын primary деп белгілеңіз.';
        }
    }

    private function saveCatalogTeachers(array $rows): void
    {
        $users = [];
        foreach ($rows as $row) {
            $user = $users[$row['username']] ??= $this->createTeacher($row, catalog: true);
            $placement = ['user_id' => $user->id, 'organization_id' => $row['organization_id']];
            DB::table('education_employments')->insert($placement + [
                'position_title' => $row['position_title'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($row['placement'] === 'primary') {
                DB::table('education_primary_workplaces')->insert($placement);
            }
        }
    }

    private function createTeacher(array $row, bool $catalog = false): User
    {
        $user = new User([
            'name' => $row['name'],
            'username' => $row['username'],
            'email' => $row['email'],
            'role' => UserRole::TEACHER,
            'password' => Hash::make(bin2hex(random_bytes(32))),
        ]);
        if ($catalog) {
            $user->forceFill(['catalog_onboarding_pending' => true]);
        }
        $user->save();

        return $user;
    }

    private function saveTeachers(array $rows): void
    {
        $users = $employments = [];
        foreach ($rows as $row) {
            $login = $row['username'];
            $first = ! isset($users[$login]);
            $user = $users[$login] ??= $this->createTeacher($row);
            $schoolId = $row['school_id'];
            if (! isset($employments[$login][$schoolId])) {
                $user->schools()->attach($schoolId, ['is_primary' => $first]);
                $employment = TeacherEmployment::create([
                    'school_user_id' => $user->schools()->newPivotQuery()->where('school_id', $schoolId)->value('id'),
                    'school_id' => $schoolId,
                    'user_id' => $user->id,
                    'position_title' => $row['position_title'],
                    'status' => 'active',
                ]);
                $employments[$login][$schoolId] = $employment;
            }
            $employments[$login][$schoolId]->assignments()->create([
                'subject_id' => $row['subject_id'],
                'weekly_hours' => $row['weekly_hours'],
                'academic_year' => $row['academic_year'],
            ]);
        }
    }
}
