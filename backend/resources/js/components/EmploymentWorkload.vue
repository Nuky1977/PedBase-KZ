<script setup lang="ts">
import { useTranslation } from '@/composables/useTranslation';
import type { TeacherEmployment } from '@/types/employment';

const { t } = useTranslation();

defineProps<{ employment: TeacherEmployment | null }>();

const format = new Intl.NumberFormat('kk-KZ', { maximumFractionDigits: 2 });
</script>

<template>
    <p
        v-if="!employment?.assignments.length"
        class="text-sm text-muted-foreground"
    >
        {{ t('Пән жүктемесі енгізілмеген. Барлығы: 0 сағат/апта.') }}
    </p>
    <div v-else class="space-y-4">
        <section
            v-for="total in employment.totals"
            :key="total.academic_year"
            class="space-y-2"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-muted px-3 py-2 text-sm font-medium"
            >
                <h3>
                    {{ total.academic_year }}–{{ total.academic_year + 1 }}
                    {{ t('оқу жылы') }}
                </h3>
                <span class="tabular-nums"
                    >{{ t('Барлығы:') }}
                    {{ format.format(Number(total.weekly_hours)) }}
                    {{ t('сағат/апта') }}</span
                >
            </div>
            <ul class="divide-y">
                <li
                    v-for="assignment in employment.assignments.filter(
                        (item) => item.academic_year === total.academic_year,
                    )"
                    :key="assignment.id"
                    class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"
                >
                    <span class="min-w-0 break-words">{{
                        assignment.subject_name
                    }}</span>
                    <span class="tabular-nums"
                        >{{ format.format(Number(assignment.weekly_hours)) }}
                        {{ t('сағат/апта') }}</span
                    >
                    <slot name="actions" :assignment="assignment" />
                </li>
            </ul>
        </section>
    </div>
</template>
