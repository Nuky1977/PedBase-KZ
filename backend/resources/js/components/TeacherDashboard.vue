<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    BriefcaseBusiness,
    Building2,
    FolderOpen,
} from '@lucide/vue';
import { computed } from 'vue';
import WorkplaceStatistics from '@/components/WorkplaceStatistics.vue';
import { useTranslation } from '@/composables/useTranslation';
import { show as dossier } from '@/routes/dossiers';
import { organizationEmployment } from '@/routes/teacher';
import { index as employment } from '@/routes/teacher/employment';

const { t } = useTranslation();

const page = usePage();
defineProps<{
    placements: { catalog: boolean; primary: number; secondary: number };
}>();
const actions = computed(() => {
    const user = page.props.auth.user;

    if (!user || user.role !== 'teacher') {
        return [];
    }

    const links = [
        { title: t('Жеке ісім'), href: dossier(user.id), icon: FolderOpen },
        {
            title: t('Мектептегі еңбек деректерім'),
            href: employment(),
            icon: BriefcaseBusiness,
        },
    ];

    if (page.props.educationCatalogEnabled) {
        links.push({
            title: t('Ұйымдардағы жұмыс орындарым'),
            href: organizationEmployment(),
            icon: Building2,
        });
    }

    return links;
});
</script>

<template>
    <section class="mx-auto w-full max-w-7xl space-y-5 p-4 sm:p-8">
        <Head :title="t('Педагог кабинеті')" />
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
            {{ t('Педагог кабинеті') }}
        </h1>
        <WorkplaceStatistics :statistics="placements" />
        <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <li v-for="action in actions" :key="action.title">
                <Link
                    :href="action.href"
                    class="flex min-h-24 items-center gap-3 rounded-xl border bg-card p-4 font-medium hover:border-blue-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    <component
                        :is="action.icon"
                        class="size-10 shrink-0 rounded-xl bg-blue-100 p-2.5 text-blue-800"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1 wrap-anywhere">{{
                        action.title
                    }}</span>
                    <ArrowUpRight class="size-4 shrink-0" aria-hidden="true" />
                </Link>
            </li>
        </ul>
    </section>
</template>
