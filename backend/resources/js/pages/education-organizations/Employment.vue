<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageLinks from '@/components/PageLinks.vue';
import { useTranslation } from '@/composables/useTranslation';
import { index as reports } from '@/routes/education-organizations/reports';

const { t } = useTranslation();

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
        <Head :title="t('Ұйымдардағы жұмыс орындарым')" />
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
            {{ t('Ұйымдардағы жұмыс орындарым') }}
        </h1>
        <Link :href="reports()" class="text-sm underline">{{
            t('Менің жүктемем және кәсіби дамуым')
        }}</Link>
        <p
            v-if="!employments.data.length"
            class="text-sm text-muted-foreground"
        >
            {{ t('Еңбек жазбалары табылмады.') }}
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
                            ? t('Негізгі жұмыс орны')
                            : t('Қосымша жұмыс орны')
                    }}
                    ·
                    {{
                        employment.status === 'active'
                            ? t('Белсенді')
                            : t('Белсенді емес')
                    }}
                </p>
                <p
                    v-if="employment.organization_archived"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('Ұйым мұрағатталған') }}
                </p>
            </li>
        </ul>
        <PageLinks
            :previous="employments.prev_page_url"
            :next="employments.next_page_url"
        />
    </section>
</template>
