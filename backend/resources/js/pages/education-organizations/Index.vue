<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUpRight,
    Building2,
    MapPin,
    Plus,
    SlidersHorizontal,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import PageLinks from '@/components/PageLinks.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/composables/useTranslation';
import {
    create,
    index,
    show,
    template,
} from '@/routes/education-organizations';
import { index as imports } from '@/routes/education-organizations/imports';
import { index as reports } from '@/routes/education-organizations/reports';

const { t } = useTranslation();

const props = defineProps<{
    showTemplates: boolean;
    type: string | null;
    types: { code: string; name: string }[];
    filters: Partial<
        Record<'search' | 'type' | 'region' | 'area' | 'status', string | null>
    >;
    regions: { code: string; name: string }[];
    areas: { code: string; name: string; region_code: string }[];
    organizations: {
        data: {
            id: number;
            name: string;
            type_name: string;
            region_name: string;
            area_name: string;
            locality: string;
            status: 'active' | 'inactive' | 'archived';
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

const draftTemplates = [
    { code: 'school', name: t('Мектеп') },
    { code: 'college', name: t('Колледж') },
    { code: 'preschool', name: t('Балабақша') },
    { code: 'supplementary', name: t('Қосымша білім беру') },
    { code: 'special', name: t('Арнайы білім беру') },
];

type Filter = 'type' | 'region' | 'area' | 'status';

const statuses = {
    active: {
        label: t('Белсенді'),
        classes:
            'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    },
    inactive: {
        label: t('Белсенді емес'),
        classes:
            'bg-amber-100 text-amber-950 dark:bg-amber-950 dark:text-amber-200',
    },
    archived: {
        label: t('Мұрағатталған'),
        classes:
            'bg-slate-200 text-slate-900 dark:bg-slate-800 dark:text-slate-100',
    },
};

const form = reactive<Record<Filter | 'search', string>>({
    search: '',
    type: '',
    region: '',
    area: '',
    status: '',
});
watch(
    () => props.filters,
    (filters) => {
        form.search = filters.search ?? '';
        form.type = filters.type ?? '';
        form.region = filters.region ?? '';
        form.area = filters.area ?? '';
        form.status = filters.status ?? '';
    },
    { immediate: true },
);

const fields = computed<
    {
        key: Filter;
        label: string;
        all: string;
        items: { code: string; name: string }[];
    }[]
>(() => [
    {
        key: 'type',
        label: t('Ұйым түрі'),
        all: t('Барлық түрі'),
        items: props.types.map((item) => ({ ...item, name: t(item.name) })),
    },
    {
        key: 'region',
        label: t('Облыс немесе қала'),
        all: t('Барлық облыс пен қала'),
        items: props.regions,
    },
    {
        key: 'area',
        label: t('Аудан немесе қала'),
        all: t('Барлық аудан мен қала'),
        items: props.areas.filter(
            (area) => !form.region || area.region_code === form.region,
        ),
    },
    {
        key: 'status',
        label: t('Мәртебе'),
        all: t('Барлық мәртебе'),
        items: Object.entries(statuses).map(([code, status]) => ({
            code,
            name: status.label,
        })),
    },
]);
const hasFilters = computed(() => Object.values(props.filters).some(Boolean));
const loading = ref(false);
const error = ref('');

function filter(reset = false) {
    if (loading.value) {
        return;
    }

    loading.value = true;
    error.value = '';
    const fail = () => {
        error.value = t(
            'Ұйымдар тізімін жаңарту мүмкін болмады. Қайталап көріңіз.',
        );

        return false;
    };

    router.get(
        index.url(),
        reset
            ? {}
            : {
                  search: form.search.trim() || undefined,
                  type: form.type || undefined,
                  region: form.region || undefined,
                  area: form.area || undefined,
                  status: form.status || undefined,
              },
        {
            replace: true,
            preserveScroll: true,
            preserveState: true,
            onError: fail,
            onHttpException: fail,
            onNetworkError: fail,
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}
</script>

<template>
    <section
        class="mx-auto w-full max-w-7xl min-w-0 space-y-6 px-3 py-4 sm:px-6 lg:py-7"
    >
        <Head :title="t('Білім беру ұйымдары')" />
        <header
            class="flex flex-col gap-5 rounded-2xl border border-border bg-card p-5 sm:p-7 lg:flex-row lg:items-end lg:justify-between"
        >
            <div class="min-w-0">
                <div
                    class="mb-3 flex size-10 items-center justify-center rounded-xl bg-blue-800 text-white dark:bg-blue-300 dark:text-blue-950"
                >
                    <Building2 class="size-5" aria-hidden="true" />
                </div>
                <h1
                    class="text-2xl leading-tight font-bold tracking-tight text-foreground sm:text-3xl"
                >
                    {{ t('Білім беру ұйымдары') }}
                </h1>
            </div>
            <nav
                :aria-label="t('Каталог әрекеттері')"
                class="flex flex-wrap gap-2 max-sm:[&>a]:w-full"
            >
                <Button
                    v-if="showTemplates"
                    as-child
                    class="min-h-11 bg-blue-800 text-white hover:bg-blue-900 dark:bg-blue-300 dark:text-blue-950 dark:hover:bg-blue-200"
                >
                    <Link :href="create()"
                        ><Plus aria-hidden="true" /> {{ t('Ұйым қосу') }}</Link
                    >
                </Button>
                <Button
                    v-if="showTemplates"
                    as-child
                    variant="outline"
                    class="min-h-11"
                >
                    <Link :href="imports()">{{ t('Excel импорты') }}</Link>
                </Button>
                <Button as-child variant="outline" class="min-h-11">
                    <Link :href="reports()"
                        >{{ t('Каталог есептері') }}
                        <ArrowUpRight aria-hidden="true"
                    /></Link>
                </Button>
            </nav>
        </header>

        <div class="rounded-2xl border bg-card p-4 sm:p-5">
            <h2
                id="catalog-filters"
                class="mb-4 flex items-center gap-2 text-base font-semibold"
            >
                <SlidersHorizontal class="size-4" aria-hidden="true" />
                {{ t('Сүзгілер') }}
            </h2>
            <form
                aria-labelledby="catalog-filters"
                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                @submit.prevent="filter()"
            >
                <div class="min-w-0 sm:col-span-2 xl:col-span-4">
                    <label
                        for="organization-search"
                        class="mb-1.5 block text-sm font-medium"
                        >{{ t('Ұйым атауы бойынша іздеу') }}</label
                    >
                    <Input
                        id="organization-search"
                        v-model="form.search"
                        type="search"
                        maxlength="120"
                        :disabled="loading"
                        class="min-h-11 sm:max-w-md"
                    />
                </div>
                <label
                    v-for="field in fields"
                    :key="field.key"
                    class="grid min-w-0 gap-1.5 text-sm font-medium"
                >
                    {{ field.label }}
                    <select
                        v-model="form[field.key]"
                        :disabled="loading"
                        class="h-11 w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm font-normal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-65"
                        @change="field.key === 'region' && (form.area = '')"
                    >
                        <option value="">{{ field.all }}</option>
                        <option
                            v-for="item in field.items"
                            :key="item.code"
                            :value="item.code"
                        >
                            {{ item.name }}
                        </option>
                    </select>
                </label>
                <div
                    class="flex flex-wrap items-center gap-2 sm:col-span-2 xl:col-span-4"
                >
                    <Button
                        type="submit"
                        :disabled="loading"
                        class="min-h-11"
                        >{{ t('Сүзгіні қолдану') }}</Button
                    >
                    <Button
                        v-if="hasFilters || Object.values(form).some(Boolean)"
                        type="button"
                        variant="ghost"
                        :disabled="loading"
                        class="min-h-11"
                        @click="filter(true)"
                    >
                        {{ t('Сүзгіні тазарту') }}
                    </Button>
                </div>
            </form>
            <p
                v-if="loading"
                class="mt-3 text-sm text-muted-foreground"
                role="status"
            >
                {{ t('Ұйымдар жүктелуде…') }}
            </p>
            <p
                v-if="error"
                class="mt-3 text-sm font-medium text-destructive"
                role="alert"
            >
                {{ error }}
            </p>
        </div>

        <section
            aria-labelledby="catalog-results"
            :aria-busy="loading"
            class="space-y-3"
        >
            <h2 id="catalog-results" class="text-lg font-semibold">
                {{ t('Ұйымдар') }}
            </h2>
            <div
                v-if="!organizations.data.length"
                class="grid justify-items-center gap-3 rounded-2xl border border-dashed bg-card p-7 text-center sm:p-10"
            >
                <span
                    class="grid size-11 place-items-center rounded-xl bg-muted text-muted-foreground"
                    ><Building2 class="size-5" aria-hidden="true"
                /></span>
                <p class="font-semibold">
                    {{
                        hasFilters
                            ? t('Сүзгіге сәйкес ұйым жоқ')
                            : t('Қолжетімді ұйымдар жоқ')
                    }}
                </p>
                <Button
                    v-if="hasFilters"
                    type="button"
                    variant="outline"
                    :disabled="loading"
                    @click="filter(true)"
                    >{{ t('Барлық ұйымды көрсету') }}</Button
                >
                <Button v-else-if="showTemplates" as-child variant="outline"
                    ><Link :href="create()">{{ t('Ұйым қосу') }}</Link></Button
                >
            </div>
            <ul v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <li
                    v-for="organization in organizations.data"
                    :key="organization.id"
                    class="flex min-w-0 flex-col gap-4 rounded-2xl border bg-card p-4 shadow-xs sm:p-5"
                >
                    <div class="flex items-start justify-between gap-2">
                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-lg bg-blue-50 text-blue-800 dark:bg-blue-950 dark:text-blue-200"
                            ><Building2 class="size-4" aria-hidden="true"
                        /></span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="statuses[organization.status].classes"
                        >
                            <span
                                aria-hidden="true"
                                class="size-1.5 shrink-0 rounded-full bg-current"
                            />{{ statuses[organization.status].label }}
                        </span>
                    </div>
                    <div class="min-w-0">
                        <h3
                            class="text-base leading-snug font-semibold wrap-anywhere"
                        >
                            <Link
                                :href="show(organization.id)"
                                class="rounded-sm underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                                >{{ organization.name }}</Link
                            >
                        </h3>
                        <p
                            class="mt-1 w-fit max-w-full rounded-md bg-muted px-2 py-1 text-xs font-medium wrap-anywhere text-foreground"
                        >
                            {{ t(organization.type_name) }}
                        </p>
                    </div>
                    <p
                        class="mt-auto flex gap-2 border-t pt-3 text-sm text-muted-foreground"
                    >
                        <MapPin
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="wrap-anywhere">{{
                            [
                                ...new Set(
                                    [
                                        organization.region_name,
                                        organization.area_name,
                                        organization.locality,
                                    ].filter(Boolean),
                                ),
                            ].join(' · ')
                        }}</span>
                    </p>
                </li>
            </ul>
            <PageLinks
                :previous="organizations.prev_page_url"
                :next="organizations.next_page_url"
            />
        </section>

        <details
            v-if="showTemplates"
            class="group rounded-xl border px-4 py-3 text-sm"
        >
            <summary
                class="flex cursor-pointer items-center justify-between gap-2 rounded-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
            >
                {{ t('Еңбек деректерінің жобалық XLSX үлгілері') }}
                <ArrowDown
                    class="size-4 shrink-0 group-open:rotate-180"
                    aria-hidden="true"
                />
            </summary>
            <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-2">
                <li v-for="item in draftTemplates" :key="item.code">
                    <a
                        :href="template.url(item.code)"
                        class="rounded-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                        >{{ item.name }}</a
                    >
                </li>
            </ul>
        </details>
    </section>
</template>
