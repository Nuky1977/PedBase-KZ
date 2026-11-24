<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Activity,
    Building2,
    BriefcaseBusiness,
    GraduationCap,
    Link2,
    School,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import WorkplaceStatistics from '@/components/WorkplaceStatistics.vue';
import { useTranslation } from '@/composables/useTranslation';
import { dashboard } from '@/routes/admin';
import { index as importsIndex } from '@/routes/admin/imports';
import { index as schoolsIndex } from '@/routes/admin/schools';
import { index as usersIndex } from '@/routes/admin/users';
import { index as catalogIndex } from '@/routes/education-organizations';
import { index as reportsIndex } from '@/routes/reports';

const { t } = useTranslation();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Басқару панелі', href: dashboard() }],
    },
});

type Stats = {
    schools: number;
    activeSchools: number;
    teachers: number;
    users: number;
    assignedTeachers: number;
    primaryAssignments: number;
    secondaryAssignments: number;
};

const props = defineProps<{
    stats: Stats;
    analytics: {
        catalogOrganizations: number | null;
        placements: {
            catalog: boolean;
            organizations: number;
            teachers: number;
            primary: number;
            secondary: number;
        };
        workloadByYear: { year: number; hours: number }[];
        recentActivities: { action: string; created_at: string }[];
    };
}>();

const overview = [
    { key: 'schools', label: t('Барлық мектеп'), icon: School },
    { key: 'activeSchools', label: t('Белсенді мектеп'), icon: School },
    { key: 'teachers', label: t('Педагогтер'), icon: GraduationCap },
    { key: 'users', label: t('Пайдаланушылар'), icon: Users },
] as const;

const assignments = [
    { key: 'assignedTeachers', label: t('Мектепке бекітілген педагогтер') },
    {
        key: 'primaryAssignments',
        label: t('Негізгі жұмыс орны бойынша бекітулер'),
    },
    {
        key: 'secondaryAssignments',
        label: t('Қосымша жұмыс орны бойынша бекітулер'),
    },
] as const;

const numberFormat = new Intl.NumberFormat('kk-KZ');
const maxHours = computed(() =>
    Math.max(1, ...props.analytics.workloadByYear.map((item) => item.hours)),
);

const activityLabels: Record<string, string> = {
    'organization.assign': t('Ұйымға бекіту жаңартылды'),
    'employment.save': t('Еңбек деректері сақталды'),
    'assignment.create': t('Жүктеме қосылды'),
    'assignment.update': t('Жүктеме жаңартылды'),
    'spreadsheet.import.schools': t('Мектептер импортталды'),
    'spreadsheet.import.teachers': t('Педагогтер импортталды'),
};

const quickActions = [
    { label: t('Мектептер'), href: schoolsIndex() },
    { label: t('Пайдаланушылар'), href: usersIndex() },
    { label: t('Excel импорты'), href: importsIndex() },
    { label: t('Есептер'), href: reportsIndex() },
];
</script>

<template>
    <div class="min-h-full flex-1 bg-muted/40">
        <Head :title="t('Басқару панелі')" />

        <div
            class="mx-auto flex w-full max-w-7xl flex-col gap-8 px-4 py-6 sm:px-8 sm:py-10"
        >
            <header class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <h1
                        class="text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        {{ t('Басқару панелі') }}
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground sm:text-base">
                        {{
                            t(
                                'Мектептер, педагогтер және олардың жұмыс орындары',
                            )
                        }}
                    </p>
                </div>

                <nav :aria-label="t('Жылдам өту')" class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="usersIndex()">
                            <Users aria-hidden="true" />
                            {{ t('Пайдаланушылар') }}
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="schoolsIndex()">
                            <School aria-hidden="true" />
                            {{ t('Мектептер') }}
                            <ArrowUpRight aria-hidden="true" />
                        </Link>
                    </Button>
                </nav>
            </header>

            <section aria-labelledby="overview-heading" class="space-y-3">
                <h2 id="overview-heading" class="text-lg font-semibold">
                    {{ t('Жалпы көрсеткіштер') }}
                </h2>
                <div
                    class="grid gap-3 sm:grid-cols-2"
                    :class="
                        analytics.catalogOrganizations === null
                            ? 'xl:grid-cols-4'
                            : 'xl:grid-cols-5'
                    "
                >
                    <Card
                        v-for="metric in overview"
                        :key="metric.key"
                        class="gap-0 py-0 shadow-xs"
                    >
                        <CardContent class="flex items-center gap-3 p-4">
                            <div
                                class="shrink-0 rounded-xl bg-blue-50 p-2.5 text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                            >
                                <component
                                    :is="metric.icon"
                                    class="size-5"
                                    aria-hidden="true"
                                />
                            </div>
                            <dl class="min-w-0">
                                <dt class="text-sm text-muted-foreground">
                                    {{ metric.label }}
                                </dt>
                                <dd
                                    class="mt-1 text-3xl font-semibold tracking-tight tabular-nums"
                                >
                                    {{ numberFormat.format(stats[metric.key]) }}
                                </dd>
                            </dl>
                        </CardContent>
                    </Card>
                    <Card
                        v-if="analytics.catalogOrganizations !== null"
                        class="gap-0 py-0 shadow-xs"
                    >
                        <CardContent class="flex items-center gap-4 p-4 sm:p-5">
                            <Building2
                                class="size-9 shrink-0 rounded-lg bg-blue-100 p-2 text-blue-800"
                                aria-hidden="true"
                            />
                            <dl>
                                <dt class="text-sm text-muted-foreground">
                                    {{ t('Каталогтағы ұйымдар') }}
                                </dt>
                                <dd class="text-3xl font-semibold tabular-nums">
                                    {{
                                        numberFormat.format(
                                            analytics.catalogOrganizations,
                                        )
                                    }}
                                </dd>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <div
                class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]"
            >
                <section
                    class="min-w-0 rounded-xl border bg-card p-4 sm:p-5"
                    aria-labelledby="workload-heading"
                >
                    <h2 id="workload-heading" class="text-lg font-semibold">
                        {{ t('Оқу жылдары бойынша мектеп жүктемесі') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            t(
                                'Белсенді еңбек жазбаларындағы апталық сағаттар жиыны',
                            )
                        }}
                    </p>
                    <p
                        v-if="!analytics.workloadByYear.length"
                        class="mt-5 text-sm text-muted-foreground"
                    >
                        {{ t('Жүктеме деректері жоқ.') }}
                    </p>
                    <ul v-else class="mt-5 space-y-4">
                        <li
                            v-for="item in analytics.workloadByYear"
                            :key="item.year"
                        >
                            <div
                                class="mb-1 flex flex-wrap justify-between gap-2 text-sm"
                            >
                                <span>{{ item.year }}–{{ item.year + 1 }}</span>
                                <span class="font-semibold tabular-nums"
                                    >{{ numberFormat.format(item.hours) }}
                                    {{ t('сағ') }}</span
                                >
                            </div>
                            <div
                                class="h-2 overflow-hidden rounded-full bg-muted"
                                aria-hidden="true"
                            >
                                <div
                                    class="h-full rounded-full bg-blue-600"
                                    :style="{
                                        width: `${(item.hours / maxHours) * 100}%`,
                                    }"
                                />
                            </div>
                        </li>
                    </ul>
                </section>
                <section
                    class="min-w-0 rounded-xl border bg-card p-4 sm:p-5"
                    aria-labelledby="activity-heading"
                >
                    <h2 id="activity-heading" class="text-lg font-semibold">
                        {{ t('Соңғы әрекеттер') }}
                    </h2>
                    <p
                        v-if="!analytics.recentActivities.length"
                        class="mt-5 text-sm text-muted-foreground"
                    >
                        {{ t('Әрекет тіркелмеген.') }}
                    </p>
                    <ul v-else class="mt-2 divide-y">
                        <li
                            v-for="(item, index) in analytics.recentActivities"
                            :key="index"
                            class="flex items-start gap-3 py-3"
                        >
                            <Activity
                                class="mt-1 size-4 shrink-0 text-blue-600"
                                aria-hidden="true"
                            />
                            <div>
                                <p class="text-sm font-medium">
                                    {{ activityLabels[item.action] }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ item.created_at.slice(0, 16) }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>

            <section
                class="rounded-xl border bg-card p-4 sm:p-5"
                aria-labelledby="quick-actions"
            >
                <h2 id="quick-actions" class="mb-3 text-lg font-semibold">
                    {{ t('Жылдам әрекеттер') }}
                </h2>
                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
                    <Button
                        v-for="action in quickActions"
                        :key="action.label"
                        variant="outline"
                        class="min-h-11 justify-between"
                        as-child
                    >
                        <Link :href="action.href"
                            >{{ action.label }}
                            <ArrowUpRight aria-hidden="true"
                        /></Link>
                    </Button>
                    <Button
                        v-if="analytics.catalogOrganizations !== null"
                        variant="outline"
                        class="min-h-11 justify-between"
                        as-child
                    >
                        <Link :href="catalogIndex()"
                            >{{ t('Ұйымдар каталогы') }}
                            <ArrowUpRight aria-hidden="true"
                        /></Link>
                    </Button>
                </div>
            </section>

            <WorkplaceStatistics
                v-if="analytics.placements.catalog"
                :statistics="analytics.placements"
            />
            <section aria-labelledby="assignments-heading" class="space-y-3">
                <h2
                    id="assignments-heading"
                    class="flex items-center gap-2 text-lg font-semibold"
                >
                    <BriefcaseBusiness
                        class="size-5 text-muted-foreground"
                        aria-hidden="true"
                    />
                    {{ t('Жұмыс орны бойынша бекітулер') }}
                </h2>
                <Card class="gap-0 py-0 shadow-xs">
                    <CardContent class="p-0">
                        <dl
                            class="grid divide-y md:grid-cols-3 md:divide-x md:divide-y-0"
                        >
                            <div
                                v-for="metric in assignments"
                                :key="metric.key"
                                class="space-y-2 p-4 sm:p-5"
                            >
                                <dt
                                    class="flex items-start gap-2 text-sm text-muted-foreground"
                                >
                                    <Link2
                                        class="mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {{ metric.label }}
                                </dt>
                                <dd
                                    class="text-2xl font-semibold tracking-tight tabular-nums"
                                >
                                    {{ numberFormat.format(stats[metric.key]) }}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </section>
        </div>
    </div>
</template>
