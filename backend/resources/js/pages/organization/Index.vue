<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageLinks from '@/components/PageLinks.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { school as employment } from '@/routes/admin/employment';
import { access, school } from '@/routes/organization';
import { index as reports } from '@/routes/reports';

const props = defineProps<{
    schools: {
        data: { id: number; name: string }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    canManageAccess: boolean;
    canManageEmployment: boolean;
    role: 'admin' | 'school_admin' | 'methodist';
}>();

const titles = {
    admin: 'Білім беру ұйымдары',
    school_admin: 'Мектеп әкімшісінің кабинеті',
    methodist: 'Әдіскер кабинеті',
};
</script>

<template>
    <div class="mx-auto w-full max-w-5xl space-y-5 p-4 sm:p-8">
        <Head :title="titles[props.role]" />
        <header class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ titles[props.role] }}</h1>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child
                    ><Link :href="reports()">Есептер</Link></Button
                >
                <Button v-if="canManageAccess" as-child
                    ><Link :href="access()">Өкілеттіктер</Link></Button
                >
            </div>
        </header>
        <p v-if="!schools.data.length" class="text-muted-foreground">
            Сізге қолжетімді мектеп әлі бекітілмеген.
        </p>
        <Card v-for="item in schools.data" :key="item.id" class="py-4">
            <CardContent
                class="flex flex-wrap items-center justify-between gap-3"
            >
                <h2 class="font-medium">{{ item.name }}</h2>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child
                        ><Link :href="school(item.id)">Педагогтер</Link></Button
                    >
                    <Button
                        v-if="canManageEmployment"
                        variant="outline"
                        as-child
                        ><Link :href="employment(item.id)"
                            >Еңбек деректері</Link
                        ></Button
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
