<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import { index, template } from '@/routes/education-organizations';
import { confirm, preview } from '@/routes/education-organizations/imports';

const { t } = useTranslation();

const props = defineProps<{
    types: Record<string, string>;
    workloadTypes: string[];
    regions: { id: number; code: string; name: string }[];
    areas: { region_id: number; code: string; name: string }[];
}>();

type Row = {
    number: number;
    name?: string;
    bin?: string;
    type?: string;
    area?: string;
    errors: string[];
};

const file = ref<File | null>(null);
const rows = ref<Row[]>([]);
const token = ref<string | null>(null);
const error = ref('');
const status = ref('');
const busy = ref(false);
const region = ref('');
const workload = ref('');
const teacherAccounts = computed(() => workload.value === 'teacher-accounts');
const areas = computed(() => {
    const selected = props.regions.find((item) => item.code === region.value);

    return props.areas.filter((item) => item.region_id === selected?.id);
});

function select(event: Event) {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
    rows.value = [];
    token.value = null;
    error.value = '';
    status.value = '';
}

async function submit(save: boolean) {
    if (busy.value || !file.value || (save && !token.value)) {
        return;
    }

    const body = new FormData();
    body.append('file', file.value);

    if (teacherAccounts.value) {
        body.append('teacher_accounts', '1');
    } else if (workload.value) {
        body.append('workload_type', workload.value);
    }

    if (save && token.value) {
        body.append('token', token.value);
    }

    const csrf = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='));
    busy.value = true;
    error.value = '';
    status.value = '';
    token.value = null;

    if (!save) {
        rows.value = [];
    }

    try {
        const response = await fetch((save ? confirm : preview).url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    csrf?.slice('XSRF-TOKEN='.length) ?? '',
                ),
            },
            body,
        });
        const result = await response.json();

        if (!response.ok) {
            error.value =
                result.errors?.file?.join('\n') ??
                result.errors?.token?.[0] ??
                result.errors?.workload_type?.[0] ??
                result.errors?.teacher_accounts?.[0] ??
                t('Импорт сұрауы орындалмады.');
        } else if (save) {
            rows.value = [];
            status.value = t('Сақталған жазбалар саны: :count.', {
                count: result.created,
            });
        } else {
            rows.value = result.rows;
            token.value = result.token;
        }
    } catch {
        error.value = t(
            'Сұрау нәтижесін алу мүмкін болмады. Файлды қайта тексеріңіз.',
        );
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <section class="space-y-5 p-4 md:p-6">
        <Head :title="t('Excel импорты')" />
        <Link :href="index()" class="text-sm underline">{{
            t('Ұйымдар каталогы')
        }}</Link>
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
            {{ t('Excel импорты') }}
        </h1>
        <label class="grid max-w-sm gap-1 text-sm">
            {{ t('Импорт түрі') }}
            <select
                v-model="workload"
                :disabled="busy"
                class="rounded-md border bg-background p-2"
                @change="
                    rows = [];
                    token = null;
                    error = '';
                    status = '';
                "
            >
                <option value="">{{ t('Ұйымдарды тіркеу') }}</option>
                <option value="teacher-accounts">
                    {{ t('Педагог аккаунттарын тіркеу') }}
                </option>
                <option v-for="code in workloadTypes" :key="code" :value="code">
                    {{ t(types[code] ?? code) }} {{ t('· жүктеме') }}
                </option>
            </select>
        </label>
        <a
            v-if="workload"
            :href="
                template.url(
                    workload,
                    teacherAccounts ? undefined : { query: { workload: 1 } },
                )
            "
            class="text-sm underline"
        >
            {{
                teacherAccounts
                    ? t('Педагог аккаунттарының XLSX үлгісі')
                    : t('Жүктеме XLSX үлгісі')
            }}
        </a>
        <ul v-else class="flex flex-wrap gap-4 text-sm">
            <li v-for="(name, code) in types" :key="code">
                <a
                    :href="template.url(code, { query: { registration: 1 } })"
                    class="underline"
                    >{{ t(name) }} XLSX</a
                >
            </li>
        </ul>
        <p v-if="teacherAccounts" class="text-sm">
            {{
                t(
                    'Әр жұмыс орнын жеке жолға енгізіңіз. Алғашқы құпиясөзді педагог «Құпиясөзді ұмыттыңыз ба?» бөлімі арқылы өзі орнатады.',
                )
            }}
        </p>
        <details class="space-y-2 text-sm">
            <summary class="cursor-pointer">
                {{ t('Аумақ кодтарын анықтамалықтан қарау') }}
            </summary>
            <p>
                {{
                    t(
                        'Сәйкестікті тексеріп, XLSX файлындағы облыс пен аудан кодтарын түзетіңіз.',
                    )
                }}
            </p>
            <label class="flex flex-wrap items-center gap-2">
                {{ t('Облыс') }}
                <select
                    v-model="region"
                    class="rounded-md border bg-background p-2"
                >
                    <option value="">{{ t('Облысты таңдаңыз') }}</option>
                    <option
                        v-for="item in regions"
                        :key="item.code"
                        :value="item.code"
                    >
                        {{ item.name }} · {{ item.code }}
                    </option>
                </select>
            </label>
            <ul
                v-if="areas.length"
                class="max-h-48 list-inside list-disc overflow-auto"
            >
                <li v-for="area in areas" :key="area.code">
                    {{ area.name }} · {{ area.code }}
                </li>
            </ul>
            <p v-else-if="region || !regions.length">
                {{ t('Тіркелген аумақтар табылмады.') }}
            </p>
        </details>
        <form
            class="flex flex-wrap items-end gap-3"
            @submit.prevent="submit(false)"
        >
            <label class="grid max-w-full min-w-0 gap-1 text-sm">
                {{ t('XLSX файлы') }}
                <input
                    type="file"
                    accept=".xlsx"
                    :disabled="busy"
                    class="min-w-0 rounded-md border p-2"
                    @change="select"
                />
            </label>
            <button
                :disabled="busy || !file"
                class="rounded-md border px-3 py-2 text-sm disabled:opacity-50"
            >
                {{ t('Алдын ала тексеру') }}
            </button>
            <button
                v-if="token"
                type="button"
                :disabled="busy"
                class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50"
                @click="submit(true)"
            >
                {{ t('Растау және сақтау') }}
            </button>
        </form>
        <p
            v-if="error"
            role="alert"
            class="text-sm wrap-anywhere whitespace-pre-line text-destructive"
        >
            {{ error }}
        </p>
        <p v-if="status" role="status" class="text-sm">{{ status }}</p>
        <div v-if="rows.length" class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="p-2">{{ t('Жол') }}</th>
                        <th v-if="!teacherAccounts" class="p-2">
                            {{ t('Ұйым') }}
                        </th>
                        <th v-if="!teacherAccounts" class="p-2">
                            {{ t('БСН') }}
                        </th>
                        <th v-if="!teacherAccounts" class="p-2">
                            {{ t('Түрі') }}
                        </th>
                        <th v-if="!teacherAccounts" class="p-2">
                            {{ t('Аумақ коды') }}
                        </th>
                        <th class="p-2">{{ t('Тексеру нәтижесі') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.number"
                        class="border-b align-top"
                    >
                        <td class="p-2">{{ row.number }}</td>
                        <td v-if="!teacherAccounts" class="p-2">
                            {{ row.name }}
                        </td>
                        <td v-if="!teacherAccounts" class="p-2">
                            {{ row.bin }}
                        </td>
                        <td v-if="!teacherAccounts" class="p-2">
                            {{ row.type }}
                        </td>
                        <td v-if="!teacherAccounts" class="p-2">
                            {{ row.area }}
                        </td>
                        <td class="p-2">
                            <ul
                                v-if="row.errors.length"
                                class="text-destructive"
                            >
                                <li
                                    v-for="message in row.errors"
                                    :key="message"
                                >
                                    {{ message }}
                                </li>
                            </ul>
                            <span v-else>{{ t('Жарамды') }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
