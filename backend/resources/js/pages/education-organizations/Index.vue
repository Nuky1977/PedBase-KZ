<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import PageLinks from '@/components/PageLinks.vue';
import {
    create,
    index,
    show,
    template,
} from '@/routes/education-organizations';
import { index as imports } from '@/routes/education-organizations/imports';
import { index as reports } from '@/routes/education-organizations/reports';

const props = defineProps<{
    showTemplates: boolean;
    type: string | null;
    types: { code: string; name: string }[];
    filters: Partial<
        Record<'type' | 'region' | 'area' | 'status', string | null>
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
    { code: 'school', name: 'Мектеп' },
    { code: 'college', name: 'Колледж' },
    { code: 'preschool', name: 'Балабақша' },
    { code: 'supplementary', name: 'Қосымша білім беру' },
    { code: 'special', name: 'Арнайы білім беру' },
];

const form = reactive({ ...props.filters });
const availableAreas = computed(() =>
    props.areas.filter(
        (area) => !form.region || area.region_code === form.region,
    ),
);
const statuses = {
    active: 'Белсенді',
    inactive: 'Белсенді емес',
    archived: 'Мұрағатталған',
};

function filter() {
    router.get(
        index.url(),
        {
            type: form.type || undefined,
            region: form.region || undefined,
            area: form.area || undefined,
            status: form.status || undefined,
        },
        {
            replace: true,
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <section class="space-y-5 p-4 md:p-6">
        <Head title="Білім беру ұйымдары" />
        <h1 class="text-2xl font-semibold">Білім беру ұйымдары</h1>
        <Link :href="reports()" class="text-sm underline"
            >Каталог есептері</Link
        >
        <nav v-if="showTemplates" class="flex flex-wrap gap-4 text-sm">
            <Link :href="create()" class="underline">Ұйым қосу</Link>
            <Link :href="imports()" class="underline">Excel импорты</Link>
        </nav>
        <details v-if="showTemplates" class="text-sm">
            <summary class="cursor-pointer">
                Еңбек деректерінің жобалық XLSX үлгілері
            </summary>
            <ul class="mt-2 flex flex-wrap gap-3">
                <li v-for="item in draftTemplates" :key="item.code">
                    <a :href="template.url(item.code)" class="underline">{{
                        item.name
                    }}</a>
                </li>
            </ul>
        </details>
        <form
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5"
            @submit.prevent="filter"
        >
            <label class="grid min-w-0 gap-1 text-sm">
                Ұйым түрі
                <select
                    v-model="form.type"
                    class="rounded-md border bg-background p-2"
                >
                    <option value="">Барлық түрі</option>
                    <option
                        v-for="item in types"
                        :key="item.code"
                        :value="item.code"
                    >
                        {{ item.name }}
                    </option>
                </select>
            </label>
            <label class="grid min-w-0 gap-1 text-sm">
                Облыс немесе қала
                <select
                    v-model="form.region"
                    class="rounded-md border bg-background p-2"
                    @change="form.area = ''"
                >
                    <option value="">Барлық облыс пен қала</option>
                    <option
                        v-for="item in regions"
                        :key="item.code"
                        :value="item.code"
                    >
                        {{ item.name }}
                    </option>
                </select>
            </label>
            <label class="grid min-w-0 gap-1 text-sm">
                Аудан немесе қала
                <select
                    v-model="form.area"
                    class="rounded-md border bg-background p-2"
                >
                    <option value="">Барлық аудан мен қала</option>
                    <option
                        v-for="item in availableAreas"
                        :key="item.code"
                        :value="item.code"
                    >
                        {{ item.name }}
                    </option>
                </select>
            </label>
            <label class="grid min-w-0 gap-1 text-sm">
                Мәртебе
                <select
                    v-model="form.status"
                    class="rounded-md border bg-background p-2"
                >
                    <option value="">Барлық мәртебе</option>
                    <option
                        v-for="(label, code) in statuses"
                        :key="code"
                        :value="code"
                    >
                        {{ label }}
                    </option>
                </select>
            </label>
            <button class="self-end rounded-md border px-3 py-2 text-sm">
                Сүзу
            </button>
        </form>
        <p
            v-if="!organizations.data.length"
            class="text-sm text-muted-foreground"
        >
            Қолжетімді ұйымдар табылмады.
        </p>
        <ul v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <li
                v-for="organization in organizations.data"
                :key="organization.id"
                class="rounded-lg border p-4"
            >
                <Link
                    :href="show(organization.id)"
                    class="font-semibold underline-offset-4 hover:underline"
                    >{{ organization.name }}</Link
                >
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ organization.type_name }} ·
                    {{ organization.region_name }} ·
                    {{ organization.area_name }} · {{ organization.locality }}
                </p>
                <p class="mt-2 text-sm">
                    {{ statuses[organization.status] }}
                </p>
            </li>
        </ul>
        <PageLinks
            :previous="organizations.prev_page_url"
            :next="organizations.next_page_url"
        />
    </section>
</template>
