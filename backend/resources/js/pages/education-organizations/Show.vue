<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { index } from '@/routes/education-organizations';

defineProps<{
    organization: {
        name: string;
        short_name: string | null;
        type_name: string;
        region_name: string;
        area_name: string;
        locality: string;
        bin: string;
        is_active: boolean;
    };
}>();
</script>

<template>
    <section class="max-w-2xl space-y-5 p-4 md:p-6">
        <Head :title="organization.name" />
        <Link :href="index()" class="text-sm underline"
            >Білім беру ұйымдарына қайту</Link
        >
        <h1 class="text-2xl font-semibold">{{ organization.name }}</h1>
        <dl class="grid gap-3 rounded-lg border p-4">
            <div v-if="organization.short_name">
                <dt class="text-sm text-muted-foreground">Қысқаша атауы</dt>
                <dd>{{ organization.short_name }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">БСН</dt>
                <dd>{{ organization.bin }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">Ұйым түрі</dt>
                <dd>{{ organization.type_name }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">Әкімшілік аумақ</dt>
                <dd>
                    {{ organization.region_name }} ·
                    {{ organization.area_name }} · {{ organization.locality }}
                </dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">Мәртебесі</dt>
                <dd>
                    {{ organization.is_active ? 'Белсенді' : 'Белсенді емес' }}
                </dd>
            </div>
        </dl>
    </section>
</template>
