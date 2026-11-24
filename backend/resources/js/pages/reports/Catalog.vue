<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import PageLinks from '@/components/PageLinks.vue';
import { exportMethod, index } from '@/routes/education-organizations/reports';

type Filters = {
    report: string;
    organization_id?: number;
    type?: string;
    region?: string;
    area?: string;
    year?: number;
    subject?: string;
    module?: string;
    unit?: string;
    period?: string;
    teacher?: string;
    category?: string;
    awarded_from?: string;
    awarded_until?: string;
    provider?: string;
};

const props = defineProps<{
    filters: Filters;
    definitions: Record<string, string>;
    columns: Record<string, string>;
    rows: {
        data: Record<string, string | number | null>[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
const form = reactive({ ...props.filters });
const isWorkload = computed(() =>
    ['workloads', 'workload_totals'].includes(form.report),
);
const keys = computed(() =>
    Object.keys(props.columns).filter((key) =>
        Object.hasOwn(props.rows.data[0] ?? {}, key),
    ),
);

const detailLabels: Record<string, string> = {
    specialty_code: 'Мамандық коды',
    specialty_name: 'Мамандық',
    qualification_code: 'Біліктілік коды',
    qualification_name: 'Біліктілік',
    program: 'Бағдарлама',
    module: 'Модуль',
    subject: 'Пән',
    direction: 'Бағыт',
    age_group: 'Жас тобы',
    category: 'Санат',
    club: 'Үйірме',
    section: 'Секция',
    classifier_source: 'Сыныптауыштың дереккөзі',
    classifier_revision: 'Сыныптауыш редакциясы',
    classifier_specialty_name: 'Мамандықтың ресми атауы',
    classifier_qualification_name: 'Біліктіліктің ресми атауы',
};
const valueLabels: Record<string, Record<string, string>> = {
    was_primary: { '0': 'Қосымша', '1': 'Негізгі' },
    unit: {
        academic_hour: 'Академиялық сағат',
        clock_hour: 'Сағат',
        fte: 'Мөлшерлеме',
    },
    period: { week: 'Апта', academic_year: 'Оқу жылы' },
};

function display(key: string, value: string | number | null) {
    if (value === null) {
        return '—';
    }

    if (key === 'details' && typeof value === 'string') {
        return Object.entries(JSON.parse(value) as Record<string, string>)
            .map(([field, text]) => `${detailLabels[field] ?? field} — ${text}`)
            .join(' · ');
    }

    return valueLabels[key]?.[value] ?? value;
}

function filter() {
    router.get(index.url(), {
        report: form.report,
        organization_id: form.organization_id ?? undefined,
        type: form.type || undefined,
        region: form.region || undefined,
        area: form.area || undefined,
        year: isWorkload.value ? form.year || undefined : undefined,
        subject: isWorkload.value ? form.subject || undefined : undefined,
        module: isWorkload.value ? form.module || undefined : undefined,
        unit: isWorkload.value ? form.unit || undefined : undefined,
        period: isWorkload.value ? form.period || undefined : undefined,
        teacher: form.teacher || undefined,
        category:
            form.report === 'categories'
                ? form.category || undefined
                : undefined,
        provider:
            form.report === 'courses' ? form.provider || undefined : undefined,
        awarded_from:
            form.report === 'categories'
                ? form.awarded_from || undefined
                : undefined,
        awarded_until:
            form.report === 'categories'
                ? form.awarded_until || undefined
                : undefined,
    });
}
</script>

<template>
    <section class="space-y-5 p-4 md:p-6">
        <Head title="Каталог есептері" />
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
            Каталог есептері
        </h1>
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="filter">
            <label class="grid gap-1 text-sm"
                >Есеп
                <select
                    v-model="form.report"
                    class="rounded-md border bg-background p-2"
                >
                    <option
                        v-for="(name, value) in definitions"
                        :key="value"
                        :value="value"
                    >
                        {{ name }}
                    </option>
                </select>
            </label>
            <template v-if="isWorkload">
                <label class="grid gap-1 text-sm">
                    Пән
                    <input
                        v-model="form.subject"
                        maxlength="150"
                        class="rounded-md border bg-background p-2"
                    />
                </label>
                <label class="grid gap-1 text-sm">
                    Кәсіптік модуль
                    <input
                        v-model="form.module"
                        maxlength="150"
                        class="rounded-md border bg-background p-2"
                    />
                </label>
                <label class="grid gap-1 text-sm">
                    Өлшем бірлігі
                    <select
                        v-model="form.unit"
                        class="rounded-md border bg-background p-2"
                    >
                        <option value="">Барлығы</option>
                        <option
                            v-for="(label, code) in valueLabels.unit"
                            :key="code"
                            :value="code"
                        >
                            {{ label }}
                        </option>
                    </select>
                </label>
                <label class="grid gap-1 text-sm">
                    Есептеу кезеңі
                    <select
                        v-model="form.period"
                        class="rounded-md border bg-background p-2"
                    >
                        <option value="">Барлығы</option>
                        <option
                            v-for="(label, code) in valueLabels.period"
                            :key="code"
                            :value="code"
                        >
                            {{ label }}
                        </option>
                    </select>
                </label>
            </template>
            <label class="grid gap-1 text-sm"
                >Ұйым түрінің коды
                <input
                    v-model="form.type"
                    maxlength="50"
                    class="rounded-md border bg-background p-2"
                />
            </label>
            <label class="grid gap-1 text-sm"
                >Облыс немесе қала коды
                <input
                    v-model="form.region"
                    maxlength="32"
                    class="rounded-md border bg-background p-2"
                />
            </label>
            <label class="grid gap-1 text-sm"
                >Аудан немесе қала коды
                <input
                    v-model="form.area"
                    maxlength="32"
                    class="rounded-md border bg-background p-2"
                />
            </label>
            <label v-if="isWorkload" class="grid gap-1 text-sm"
                >Оқу жылы
                <input
                    v-model.number="form.year"
                    type="number"
                    min="1900"
                    max="9998"
                    class="rounded-md border bg-background p-2"
                />
            </label>
            <label class="grid gap-1 text-sm">
                Педагогтің толық аты
                <input
                    v-model="form.teacher"
                    maxlength="255"
                    class="rounded-md border bg-background p-2"
                />
            </label>
            <label
                v-if="form.report === 'categories'"
                class="grid gap-1 text-sm"
            >
                Біліктілік санаты
                <input
                    v-model="form.category"
                    maxlength="150"
                    class="rounded-md border bg-background p-2"
                />
            </label>
            <label v-if="form.report === 'courses'" class="grid gap-1 text-sm">
                Курс провайдері
                <input
                    v-model="form.provider"
                    maxlength="255"
                    class="rounded-md border bg-background p-2"
                />
            </label>
            <template v-if="form.report === 'categories'">
                <label class="grid gap-1 text-sm">
                    Аттестаттау күні (бастап)
                    <input
                        v-model="form.awarded_from"
                        type="date"
                        class="rounded-md border bg-background p-2"
                    />
                </label>
                <label class="grid gap-1 text-sm">
                    Аттестаттау күні (дейін)
                    <input
                        v-model="form.awarded_until"
                        type="date"
                        :min="form.awarded_from"
                        class="rounded-md border bg-background p-2"
                    />
                </label>
            </template>
            <button class="rounded-md border px-3 py-2 text-sm">Көрсету</button>
        </form>
        <nav class="flex gap-4 text-sm">
            <a
                class="underline"
                :href="exportMethod.url('csv', { query: filters })"
                >CSV жүктеу</a
            >
            <a
                class="underline"
                :href="exportMethod.url('xlsx', { query: filters })"
                >XLSX жүктеу</a
            >
        </nav>
        <p v-if="!rows.data.length" class="text-sm text-muted-foreground">
            Қолжетімді жазбалар табылмады.
        </p>
        <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b">
                        <th v-for="key in keys" :key="key" class="p-2">
                            {{ columns[key] }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, i) in rows.data"
                        :key="i"
                        class="border-b align-top"
                    >
                        <td v-for="key in keys" :key="key" class="p-2">
                            {{ display(key, row[key] ?? null) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <PageLinks :previous="rows.prev_page_url" :next="rows.next_page_url" />
    </section>
</template>
