<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import PageLinks from '@/components/PageLinks.vue';
import { index, show, template } from '@/routes/education-organizations';

defineProps<{
    showTemplates: boolean;
    type: string | null;
    types: { code: string; name: string }[];
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

function filter(event: Event) {
    const type = (event.target as HTMLSelectElement).value;
    router.get(index.url(), type ? { type } : {}, {
        replace: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <section class="space-y-5 p-4 md:p-6">
        <Head title="Білім беру ұйымдары" />
        <h1 class="text-2xl font-semibold">Білім беру ұйымдары</h1>
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
        <label class="flex max-w-xs flex-col gap-2 text-sm">
            Ұйым түрі
            <select
                :value="type ?? ''"
                class="rounded-md border bg-background p-2"
                @change="filter"
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
                    {{
                        {
                            active: 'Белсенді',
                            inactive: 'Белсенді емес',
                            archived: 'Мұрағатталған',
                        }[organization.status]
                    }}
                </p>
            </li>
        </ul>
        <PageLinks
            :previous="organizations.prev_page_url"
            :next="organizations.next_page_url"
        />
    </section>
</template>
