<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { confirm, preview, template } from '@/routes/admin/imports';

type Kind = 'schools' | 'teachers';
type Preview = {
    rows: { number: number; errors: string[]; primary: boolean }[];
    total: number;
    valid: number;
    new_records: number;
    placements: number;
    hours_by_year: { year: number; hours: string }[];
    token: string | null;
};

defineProps<{ columns: Record<Kind, string[]> }>();

const kind = ref<Kind>('schools');
const file = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const result = ref<Preview | null>(null);
const busy = ref(false);
const error = ref('');
const success = ref('');
const recordLabel = computed(() =>
    kind.value === 'schools' ? 'мектеп' : 'педагог аккаунты',
);

function reset() {
    file.value = null;
    result.value = null;
    error.value = '';
    success.value = '';

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function choose(event: Event) {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
    result.value = null;
    error.value = '';
    success.value = '';
}

async function submit(commit: boolean) {
    if (!file.value || busy.value || (commit && !result.value?.token)) {
        return;
    }

    const body = new FormData();
    body.append('file', file.value);

    if (commit && result.value?.token) {
        body.append('token', result.value.token);
    } else {
        result.value = null;
    }

    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='));
    busy.value = true;
    error.value = '';
    success.value = '';

    try {
        const response = await fetch(
            (commit ? confirm : preview).url(kind.value),
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        cookie?.slice('XSRF-TOKEN='.length) ?? '',
                    ),
                },
                body,
            },
        );

        if (response.status === 419 || response.status === 401) {
            error.value =
                'Сессия мерзімі аяқталды. Бетті қайта ашып, жүйеге кіріңіз.';

            return;
        }

        if (response.status === 403 || response.status === 429) {
            error.value =
                response.status === 403
                    ? 'Импортқа рұқсат жоқ.'
                    : 'Сұраулар тым жиі жіберілді. Кейін қайталаңыз.';

            return;
        }

        const data = await response.json();

        if (!response.ok) {
            error.value =
                data.errors?.file?.[0] ??
                data.errors?.token?.[0] ??
                'Импорт сұрауы орындалмады.';

            if (commit) {
                result.value = null;
            }
        } else if (commit) {
            reset();
            success.value = 'Сақталған жазба саны: ' + data.created;
        } else {
            result.value = data as Preview;
        }
    } catch {
        error.value =
            'Серверден жауап алу мүмкін болмады. Жазбалардың күйін тексеріп, файлды қайта қараңыз.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="mx-auto w-full max-w-4xl space-y-5 p-4 sm:p-8">
        <Head title="Excel импорты" />
        <header>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                Excel импорты
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Мектептерді немесе педагогтерді XLSX файлынан қосыңыз. Деректер
                растаудан кейін сақталады.
            </p>
        </header>

        <Card>
            <CardHeader
                ><CardTitle>Файлды алдын ала тексеру</CardTitle></CardHeader
            >
            <CardContent>
                <form class="space-y-4" @submit.prevent="submit(false)">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="space-y-2">
                            <Label for="import-kind">Импорт түрі</Label>
                            <select
                                id="import-kind"
                                v-model="kind"
                                class="h-9 rounded-md border bg-background px-3 text-sm"
                                :disabled="busy"
                                @change="reset"
                            >
                                <option value="schools">Мектептер</option>
                                <option value="teachers">Педагогтер</option>
                            </select>
                        </div>
                        <Button variant="outline" as-child
                            ><a :href="template.url(kind)"
                                >XLSX үлгісін жүктеу</a
                            ></Button
                        >
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ columns[kind].join(' · ') }}
                    </p>
                    <p
                        v-if="kind === 'teachers'"
                        class="text-sm text-muted-foreground"
                    >
                        Пән коды мен белсенді мектептің БСН-і жүйеде болуы
                        керек. Бірінші мектеп негізгі жұмыс орны болады. Алғашқы
                        кіру үшін құпиясөзді растаулы email арнасы арқылы орнату
                        қажет.
                    </p>
                    <div class="space-y-2">
                        <Label for="import-file">XLSX файлы</Label>
                        <input
                            id="import-file"
                            ref="fileInput"
                            type="file"
                            accept=".xlsx"
                            :disabled="busy"
                            class="block w-full min-w-0 text-sm"
                            @change="choose"
                        />
                        <p class="text-sm text-muted-foreground">
                            Ең көбі 2 МБ және 100 дерек жолы.
                        </p>
                    </div>
                    <Button :disabled="!file || busy">{{
                        busy ? 'Өңделуде…' : 'Алдын ала тексеру'
                    }}</Button>
                </form>
                <p
                    v-if="error"
                    role="alert"
                    class="mt-3 text-sm text-destructive"
                >
                    {{ error }}
                </p>
                <p
                    v-if="success"
                    role="status"
                    class="mt-3 text-sm font-medium"
                >
                    {{ success }}
                </p>
            </CardContent>
        </Card>

        <Card v-if="result">
            <CardHeader><CardTitle>Алдын ала қарау</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <p class="text-sm" role="status">
                    Жолдар: {{ result.total }}. Жарамды: {{ result.valid }}.
                    Қатесі бар: {{ result.total - result.valid }}.
                    <span v-if="result.token"
                        >Расталғанда {{ result.new_records }}
                        {{ recordLabel }} қосылады.</span
                    >
                </p>
                <div
                    v-if="kind === 'teachers' && result.token"
                    class="space-y-1 text-sm"
                >
                    <p>
                        Жұмыс орны бойынша бекітулер: {{ result.placements }}.
                    </p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li
                            v-for="item in result.hours_by_year"
                            :key="item.year"
                        >
                            {{ item.year }}–{{ item.year + 1 }} оқу жылының
                            жиынтық апталық жүктемесі: {{ item.hours }} сағат.
                        </li>
                    </ul>
                </div>
                <ul class="max-h-80 divide-y overflow-y-auto rounded-md border">
                    <li
                        v-for="row in result.rows"
                        :key="row.number"
                        class="grid gap-1 px-3 py-2 text-sm sm:grid-cols-[6rem_1fr]"
                    >
                        <span class="font-medium">{{ row.number }}-жол</span>
                        <ul
                            v-if="row.errors.length"
                            class="list-disc space-y-1 pl-5 text-destructive"
                        >
                            <li v-for="message in row.errors" :key="message">
                                {{ message }}
                            </li>
                        </ul>
                        <span v-else>{{
                            row.primary
                                ? 'Жарамды · негізгі жұмыс орны'
                                : 'Жарамды'
                        }}</span>
                    </li>
                </ul>
                <Button
                    v-if="result.token"
                    :disabled="busy"
                    @click="submit(true)"
                    >Растау және сақтау</Button
                >
                <p v-else class="text-sm text-muted-foreground">
                    Қате жолдарды түзетіп, файлды қайта жүктеңіз.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
