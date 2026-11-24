<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PageLinks from '@/components/PageLinks.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/composables/useTranslation';
import { index as organization } from '@/routes/organization';
import { exportMethod, index } from '@/routes/reports';

const { t } = useTranslation();

const props = defineProps<{
    filters: {
        report: string;
        school_id: number | null;
        academic_year: number | null;
    };
    definitions: Record<
        string,
        { title: string; columns: Record<string, string> }
    >;
    schools: { id: number; name: string }[];
    rows: {
        data: Record<string, string | number | null>[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    warningDays: number;
}>();
const report = ref(props.filters.report);
const school = ref(props.filters.school_id?.toString() ?? '');
const year = ref(props.filters.academic_year?.toString() ?? '');
const definition = computed(() => props.definitions[props.filters.report]);
const yearReports = ['subjects', 'courses', 'workload', 'years'];
const inputClass = 'h-9 w-full rounded-md border bg-background px-2 text-sm';
const exportQuery = computed(() => ({
    report: props.filters.report,
    school_id: props.filters.school_id ?? undefined,
    academic_year: props.filters.academic_year ?? undefined,
}));

function applyFilters() {
    router.get(index.url(), {
        report: report.value,
        school_id: school.value || undefined,
        academic_year: yearReports.includes(report.value)
            ? year.value || undefined
            : undefined,
    });
}
</script>

<template>
    <div class="mx-auto w-full max-w-6xl space-y-5 p-4 sm:p-8">
        <Head :title="t('Есептер мен аналитика')" />
        <header class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                {{ t('Есептер мен аналитика') }}
            </h1>
            <Button variant="outline" as-child
                ><Link :href="organization()">{{ t('Ұйымдар') }}</Link></Button
            >
        </header>
        <Card>
            <CardContent>
                <form
                    class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    @submit.prevent="applyFilters"
                >
                    <div class="space-y-1">
                        <Label for="report">{{ t('Есеп түрі') }}</Label>
                        <select
                            id="report"
                            v-model="report"
                            :class="inputClass"
                        >
                            <option
                                v-for="(item, key) in definitions"
                                :key="key"
                                :value="key"
                            >
                                {{ item.title }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <Label for="school">{{ t('Мектеп') }}</Label>
                        <select
                            id="school"
                            v-model="school"
                            :class="inputClass"
                        >
                            <option value="">
                                {{ t('Қолжетімді мектептердің барлығы') }}
                            </option>
                            <option
                                v-for="item in schools"
                                :key="item.id"
                                :value="String(item.id)"
                            >
                                {{ item.name }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <Label for="year">{{
                            t('Оқу жылының басталуы')
                        }}</Label>
                        <input
                            id="year"
                            v-model="year"
                            :class="inputClass"
                            :disabled="!yearReports.includes(report)"
                            type="number"
                            min="1900"
                            max="9998"
                        />
                    </div>
                    <Button>{{ t('Қолдану') }}</Button>
                </form>
            </CardContent>
        </Card>
        <section class="space-y-3">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ definition.title }}
                    </h2>
                    <p
                        v-if="filters.report === 'attestations'"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('Келесі') }} {{ warningDays }}
                        {{ t('күнде аяқталатын тіркелген мерзімдер.') }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" as-child
                        ><a
                            :href="
                                exportMethod.url('csv', { query: exportQuery })
                            "
                            >{{ t('CSV жүктеу') }}</a
                        ></Button
                    >
                    <Button variant="outline" as-child
                        ><a
                            :href="
                                exportMethod.url('xlsx', { query: exportQuery })
                            "
                            >{{ t('Excel жүктеу') }}</a
                        ></Button
                    >
                </div>
            </header>
            <p
                v-if="!rows.data.length"
                class="rounded-lg border p-4 text-muted-foreground"
            >
                {{ t('Таңдалған есепте дерек жоқ.') }}
            </p>
            <div v-else class="overflow-x-auto rounded-lg border bg-card">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted">
                        <tr>
                            <th
                                v-for="(label, key) in definition.columns"
                                :key="key"
                                scope="col"
                                class="p-3"
                            >
                                {{ label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(item, row) in rows.data" :key="row">
                            <td
                                v-for="(_, key) in definition.columns"
                                :key="key"
                                class="p-3"
                            >
                                {{ item[key] ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <PageLinks :previous="rows.prev_page_url" :next="rows.next_page_url" />
    </div>
</template>
