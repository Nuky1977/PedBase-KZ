<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EmploymentWorkload from '@/components/EmploymentWorkload.vue';
import PageLinks from '@/components/PageLinks.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { show } from '@/routes/admin/employment';
import { index as organization } from '@/routes/organization';
import type { TeacherEmployment } from '@/types/employment';

defineProps<{
    school: { id: number; name: string } | null;
    pagination?: { previous: string | null; next: string | null };
    records: {
        id: number;
        name: string;
        is_primary: boolean;
        employment: TeacherEmployment | null;
    }[];
}>();
</script>

<template>
    <div class="mx-auto w-full max-w-5xl space-y-5 px-4 py-6 sm:px-8">
        <Head
            :title="
                school ? 'Мектептің еңбек деректері' : 'Менің еңбек деректерім'
            "
        />
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    {{
                        school
                            ? 'Мектептің еңбек деректері'
                            : 'Менің еңбек деректерім'
                    }}
                </h1>
                <p v-if="school" class="mt-1 text-muted-foreground">
                    {{ school.name }}
                </p>
            </div>
            <Button v-if="school" variant="outline" as-child>
                <Link :href="organization()">Ұйымдарға оралу</Link>
            </Button>
        </header>

        <Card v-if="!records.length">
            <CardContent class="text-sm text-muted-foreground">
                {{
                    school
                        ? 'Бұл мектепке педагог бекітілмеген.'
                        : 'Сіз әлі ешбір мектепке бекітілмегенсіз.'
                }}
            </CardContent>
        </Card>

        <Card v-for="record in records" :key="record.id">
            <CardHeader
                class="flex flex-row flex-wrap items-start justify-between gap-3"
            >
                <div class="space-y-2">
                    <CardTitle>{{ record.name }}</CardTitle>
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            :variant="
                                record.is_primary ? 'default' : 'secondary'
                            "
                        >
                            {{
                                record.is_primary
                                    ? 'Негізгі жұмыс орны'
                                    : 'Қосымша жұмыс орны'
                            }}
                        </Badge>
                        <Badge v-if="record.employment" variant="outline">
                            {{
                                record.employment.status === 'on_leave'
                                    ? 'Демалыста'
                                    : 'Жұмыс істейді'
                            }}
                        </Badge>
                    </div>
                </div>
                <Button v-if="school" variant="outline" size="sm" as-child>
                    <Link
                        :href="show({ school: school.id, teacher: record.id })"
                        >Өңдеу</Link
                    >
                </Button>
            </CardHeader>
            <CardContent class="space-y-3">
                <p class="text-sm">
                    Лауазымы:
                    {{
                        record.employment?.position_title ?? 'Әлі енгізілмеген'
                    }}
                </p>
                <EmploymentWorkload :employment="record.employment" />
            </CardContent>
        </Card>
        <PageLinks
            v-if="pagination"
            :previous="pagination.previous"
            :next="pagination.next"
        />
    </div>
</template>
