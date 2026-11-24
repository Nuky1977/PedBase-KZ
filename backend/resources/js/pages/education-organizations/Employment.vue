<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageLinks from '@/components/PageLinks.vue';
import { index as reports } from '@/routes/education-organizations/reports';

defineProps<{
    employments: {
        data: {
            id: number;
            name: string;
            position_title: string | null;
            is_primary: boolean;
            organization_archived: boolean;
            status: string;
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
</script>

<template>
    <section class="max-w-2xl space-y-5 p-4 md:p-6">
        <Head title="Ұйымдардағы жұмыс орындарым" />
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
            Ұйымдардағы жұмыс орындарым
        </h1>
        <Link :href="reports()" class="text-sm underline"
            >Менің жүктемем және кәсіби дамуым</Link
        >
        <p
            v-if="!employments.data.length"
            class="text-sm text-muted-foreground"
        >
            Еңбек жазбалары табылмады.
        </p>
        <ul v-else class="space-y-3">
            <li
                v-for="employment in employments.data"
                :key="employment.id"
                class="rounded-lg border p-4"
            >
                <p class="font-semibold">{{ employment.name }}</p>
                <p v-if="employment.position_title" class="text-sm">
                    {{ employment.position_title }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{
                        employment.is_primary
                            ? 'Негізгі жұмыс орны'
                            : 'Қосымша жұмыс орны'
                    }}
                    ·
                    {{
                        employment.status === 'active'
                            ? 'Белсенді'
                            : 'Белсенді емес'
                    }}
                </p>
                <p
                    v-if="employment.organization_archived"
                    class="text-sm text-muted-foreground"
                >
                    Ұйым мұрағатталған
                </p>
            </li>
        </ul>
        <PageLinks
            :previous="employments.prev_page_url"
            :next="employments.next_page_url"
        />
    </section>
</template>
