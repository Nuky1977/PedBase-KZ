<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import PageLinks from '@/components/PageLinks.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { show } from '@/routes/dossiers';
import {
    index,
    school as schoolRoute,
    teachers as directory,
} from '@/routes/organization';

const fields = [
    { key: 'name', label: 'Аты-жөні' },
    { key: 'organization', label: 'Ұйым атауы' },
    { key: 'subject', label: 'Пән атауы немесе коды' },
    { key: 'position', label: 'Лауазымы' },
    { key: 'category', label: 'Біліктілік санаты' },
] as const;

type Filters = Record<(typeof fields)[number]['key'], string>;

const props = defineProps<{
    school: { id: number; name: string } | null;
    filters: Partial<Filters> | [];
    teachers: {
        data: { id: number; name: string }[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

const endpoint = computed(() =>
    props.school ? schoolRoute.url(props.school.id) : directory.url(),
);
const form = useForm<Filters>({
    name: '',
    organization: '',
    subject: '',
    position: '',
    category: '',
});
watch(
    () => props.filters,
    (filters) => {
        for (const { key } of fields) {
            form[key] = Array.isArray(filters) ? '' : (filters[key] ?? '');
        }
    },
    { immediate: true },
);

function search() {
    form.transform((values) =>
        Object.fromEntries(
            Object.entries(values)
                .map(([key, value]) => [key, value.trim()])
                .filter(([, value]) => value),
        ),
    );
    form.get(endpoint.value, { preserveScroll: true, replace: true });
}
</script>

<template>
    <div class="mx-auto w-full max-w-7xl min-w-0 space-y-5 p-4 sm:p-8">
        <Head title="Педагогтер" />
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold">Педагогтер</h1>
                <p
                    v-if="school"
                    class="mt-1 text-sm wrap-anywhere text-muted-foreground"
                >
                    {{ school.name }}
                </p>
            </div>
            <Button variant="outline" class="min-h-11" as-child
                ><Link :href="index()">Мектептерге оралу</Link></Button
            >
        </header>
        <form
            class="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 xl:grid-cols-5"
            aria-label="Педагогтерді іздеу"
            @submit.prevent="search"
        >
            <label
                v-for="field in fields"
                :key="field.key"
                class="grid min-w-0 gap-1 text-sm font-medium"
            >
                {{ field.label }}
                <Input
                    v-model="form[field.key]"
                    maxlength="150"
                    type="search"
                    class="min-h-11 min-w-0"
                />
                <span
                    v-if="form.errors[field.key]"
                    class="text-destructive"
                    role="alert"
                    >{{ form.errors[field.key] }}</span
                >
            </label>
            <div class="flex flex-wrap gap-2 sm:col-span-2 xl:col-span-5">
                <Button
                    type="submit"
                    class="min-h-11"
                    :disabled="form.processing"
                    >Іздеу</Button
                >
                <Button variant="outline" class="min-h-11" as-child
                    ><Link :href="endpoint">Тазарту</Link></Button
                >
            </div>
        </form>
        <p role="status" class="text-sm text-muted-foreground">
            {{
                form.processing
                    ? 'Ізделіп жатыр…'
                    : `Табылған педагогтер: ${teachers.total}`
            }}
        </p>
        <p
            v-if="!teachers.data.length"
            class="rounded-xl border border-dashed p-5 text-muted-foreground"
        >
            Іздеуге сәйкес педагог табылмады.
        </p>
        <ul v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <li
                v-for="teacher in teachers.data"
                :key="teacher.id"
                class="flex min-w-0 flex-col items-start gap-3 rounded-xl border bg-card p-5 shadow-xs"
            >
                <span
                    class="grid size-10 place-items-center rounded-xl bg-blue-100 font-semibold text-blue-800"
                    aria-hidden="true"
                    >{{
                        teacher.name
                            .trim()
                            .slice(0, 1)
                            .toLocaleUpperCase('kk-KZ')
                    }}</span
                >
                <h2 class="font-semibold wrap-anywhere">{{ teacher.name }}</h2>
                <Button class="mt-auto min-h-11" variant="outline" as-child
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
