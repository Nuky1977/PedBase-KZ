<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageLinks from '@/components/PageLinks.vue';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/dossiers';
import { index } from '@/routes/organization';

defineProps<{
    school: { id: number; name: string };
    teachers: {
        data: { id: number; name: string }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
</script>

<template>
    <div class="mx-auto w-full max-w-5xl space-y-5 p-4 sm:p-8">
        <Head :title="school.name" />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ school.name }}</h1>
            <Button variant="outline" as-child
                ><Link :href="index()">Мектептерге оралу</Link></Button
            >
        </div>
        <p v-if="!teachers.data.length" class="text-muted-foreground">
            Педагогтер тіркелмеген.
        </p>
        <ul v-else class="divide-y rounded-xl border bg-card px-4">
            <li
                v-for="teacher in teachers.data"
                :key="teacher.id"
                class="flex flex-wrap items-center justify-between gap-2 py-3"
            >
                <span>{{ teacher.name }}</span>
                <Button size="sm" variant="outline" as-child
                    ><Link :href="show(teacher.id)">Жеке іс</Link></Button
                >
            </li>
        </ul>
        <PageLinks
            :previous="teachers.prev_page_url"
            :next="teachers.next_page_url"
        />
    </div>
</template>
