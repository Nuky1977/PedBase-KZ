<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    BriefcaseBusiness,
    GraduationCap,
    Link2,
    School,
    Users,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes/admin';
import { index as schoolsIndex } from '@/routes/admin/schools';
import { index as usersIndex } from '@/routes/admin/users';

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

defineProps<{ stats: Stats }>();

const overview = [
    { key: 'schools', label: 'Барлық мектеп', icon: School },
    { key: 'activeSchools', label: 'Белсенді мектеп', icon: School },
    { key: 'teachers', label: 'Педагогтер', icon: GraduationCap },
    { key: 'users', label: 'Пайдаланушылар', icon: Users },
] as const;

const assignments = [
    { key: 'assignedTeachers', label: 'Мектепке бекітілген педагогтер' },
    {
        key: 'primaryAssignments',
        label: 'Негізгі жұмыс орны бойынша бекітулер',
    },
    {
        key: 'secondaryAssignments',
        label: 'Қосымша жұмыс орны бойынша бекітулер',
    },
] as const;

const numberFormat = new Intl.NumberFormat('kk-KZ');
</script>

<template>
    <div class="min-h-full flex-1 bg-muted/40">
        <Head title="Басқару панелі" />

        <div
            class="mx-auto flex w-full max-w-7xl flex-col gap-8 px-4 py-6 sm:px-8 sm:py-10"
        >
            <header class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <h1
                        class="text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        Басқару панелі
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground sm:text-base">
                        Мектептер, педагогтер және олардың жұмыс орындары
                    </p>
                </div>

                <nav aria-label="Жылдам өту" class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="usersIndex()">
                            <Users aria-hidden="true" />
                            Пайдаланушылар
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="schoolsIndex()">
                            <School aria-hidden="true" />
                            Мектептер
                            <ArrowUpRight aria-hidden="true" />
                        </Link>
                    </Button>
                </nav>
            </header>

            <section aria-labelledby="overview-heading" class="space-y-3">
                <h2 id="overview-heading" class="text-lg font-semibold">
                    Жалпы көрсеткіштер
                </h2>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Card
                        v-for="metric in overview"
                        :key="metric.key"
                        class="gap-0 py-0 shadow-xs"
                    >
                        <CardContent class="flex items-center gap-4 p-4 sm:p-5">
                            <div
                                class="rounded-xl bg-sky-50 p-2.5 text-sky-700 dark:bg-sky-950 dark:text-sky-300"
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
                </div>
            </section>

            <section aria-labelledby="assignments-heading" class="space-y-3">
                <h2
                    id="assignments-heading"
                    class="flex items-center gap-2 text-lg font-semibold"
                >
                    <BriefcaseBusiness
                        class="size-5 text-muted-foreground"
                        aria-hidden="true"
                    />
                    Жұмыс орны бойынша бекітулер
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
