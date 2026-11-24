<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageLinks from '@/components/PageLinks.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import WorkplaceStatistics from '@/components/WorkplaceStatistics.vue';
import { useTranslation } from '@/composables/useTranslation';
import { school as employment } from '@/routes/admin/employment';
import { access, school } from '@/routes/organization';
import { index as reports } from '@/routes/reports';

const { t } = useTranslation();

const props = defineProps<{
    schools: {
        data: { id: number; name: string }[];
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
    };
    canManageAccess: boolean;
    canManageEmployment: boolean;
    role: 'admin' | 'school_admin' | 'methodist';
    statistics: {
        catalog: boolean;
        organizations: number;
        teachers: number;
        primary: number;
        secondary: number;
    };
}>();

const titles = {
    admin: t('Білім беру ұйымдары'),
    school_admin: t('Мектеп әкімшісінің кабинеті'),
    methodist: t('Әдіскер кабинеті'),
};
</script>

<template>
    <div class="mx-auto w-full max-w-7xl space-y-5 p-4 sm:p-8">
        <Head :title="titles[props.role]" />
        <header class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ titles[props.role] }}</h1>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child
                    ><Link :href="reports()">{{ t('Есептер') }}</Link></Button
                >
                <Button v-if="canManageAccess" as-child
                    ><Link :href="access()">{{
                        t('Өкілеттіктер')
                    }}</Link></Button
                >
            </div>
        </header>
        <WorkplaceStatistics :statistics="statistics" />
        <p v-if="!schools.data.length" class="text-muted-foreground">
            {{ t('Сізге қолжетімді мектеп әлі бекітілмеген.') }}
        </p>
        <Card v-for="item in schools.data" :key="item.id" class="py-4">
            <CardContent
                class="flex flex-wrap items-center justify-between gap-3"
            >
                <h2 class="font-medium">{{ item.name }}</h2>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child
                        ><Link :href="school(item.id)">{{
                            t('Педагогтер')
                        }}</Link></Button
                    >
                    <Button
                        v-if="canManageEmployment"
                        variant="outline"
                        as-child
                        ><Link :href="employment(item.id)">{{
                            t('Еңбек деректері')
                        }}</Link></Button
                    >
                </div>
            </CardContent>
        </Card>
        <PageLinks
            :previous="schools.prev_page_url"
            :next="schools.next_page_url"
        />
    </div>
</template>
