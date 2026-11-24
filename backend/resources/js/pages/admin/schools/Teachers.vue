<script setup lang="ts">
import { Form, Head, Link, router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import PageLinks from '@/components/PageLinks.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/composables/useTranslation';
import { school as schoolEmployment } from '@/routes/admin/employment';
import { teachers as schoolTeachers } from '@/routes/admin/schools';
import { show as dossier } from '@/routes/dossiers';

const { t } = useTranslation();

type School = {
    id: number;
    name: string;
    short_name: string | null;
};

type Teacher = {
    id: number;
    name: string;
    username: string;
    email: string;
    is_primary?: boolean;
};

const props = defineProps<{
    school: School;
    teachers: {
        data: Teacher[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { name: string };
    teacherCount: number;
    availableTeachers: Teacher[];
}>();

const search = useForm({ name: props.filters.name });
watch(
    () => props.filters.name,
    (value) => {
        search.name = value;
    },
);

function filter() {
    search.get(schoolTeachers.url(props.school.id), {
        preserveScroll: true,
        replace: true,
    });
}

const detachTeacher = (schoolId: number, teacher: Teacher): void => {
    if (teacher.is_primary) {
        return;
    }

    const confirmed = window.confirm(
        t(':name педагогін осы мектептен ажыратуға сенімдісіз бе?', {
            name: teacher.name,
        }),
    );

    if (!confirmed) {
        return;
    }

    router.delete(`/admin/schools/${schoolId}/teachers/${teacher.id}`, {
        preserveScroll: true,
    });
};

const makeTeacherPrimary = (schoolId: number, teacher: Teacher): void => {
    if (teacher.is_primary) {
        return;
    }

    const confirmed = window.confirm(
        t(
            ':name педагогі үшін осы мектепті негізгі жұмыс орны ретінде белгілеуге сенімдісіз бе?',
            { name: teacher.name },
        ),
    );

    if (!confirmed) {
        return;
    }

    router.patch(
        `/admin/schools/${schoolId}/teachers/${teacher.id}/primary`,
        {},
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head :title="t(':name — педагогтер', { name: school.name })" />

    <div class="min-h-full bg-muted/40 p-4 sm:p-8">
        <div class="mx-auto max-w-7xl">
            <!-- Тақырып -->
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold">
                        {{ school.name }}
                    </h1>

                    <p class="mt-2 text-muted-foreground">
                        {{ t('Мектепке тіркелген педагогтарды басқару') }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="schoolEmployment(school.id)"
                        class="rounded-md border bg-card px-4 py-2 text-sm font-medium hover:bg-gray-50"
                    >
                        {{ t('Еңбек деректері') }}
                    </Link>
                    <Link
                        href="/admin/schools"
                        class="rounded-md border bg-card px-4 py-2 text-sm font-medium hover:bg-gray-50"
                    >
                        {{ t('← Мектептерге қайту') }}
                    </Link>
                </div>
            </div>

            <!-- Педагогты мектепке бекіту -->
            <div class="mb-6 rounded-xl border bg-card p-6">
                <h2 class="text-xl font-semibold">
                    {{ t('Педагогты мектепке бекіту') }}
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        t(
                            'Жүйеде тіркелген педагогты таңдап, жұмыс орнының түрін көрсетіңіз.',
                        )
                    }}
                </p>

                <Form
                    :action="`/admin/schools/${school.id}/teachers`"
                    method="post"
                    #default="{ errors, processing }"
                    class="mt-5 grid gap-4 md:grid-cols-3"
                >
                    <!-- Педагог -->
                    <div>
                        <label for="teacher_id" class="mb-1 block font-medium">
                            {{ t('Педагог') }}
                        </label>

                        <select
                            id="teacher_id"
                            name="teacher_id"
                            required
                            class="w-full rounded-md border p-2"
                        >
                            <option value="">
                                {{ t('Педагогты таңдаңыз') }}
                            </option>

                            <option
                                v-for="teacher in availableTeachers"
                                :key="teacher.id"
                                :value="teacher.id"
                            >
                                {{ teacher.name }}
                                ({{ teacher.username }})
                            </option>
                        </select>

                        <p
                            v-if="errors.teacher_id"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ errors.teacher_id }}
                        </p>
                    </div>

                    <!-- Жұмыс орны -->
                    <div>
                        <label for="is_primary" class="mb-1 block font-medium">
                            {{ t('Жұмыс орны') }}
                        </label>

                        <select
                            id="is_primary"
                            name="is_primary"
                            required
                            class="w-full rounded-md border p-2"
                        >
                            <option value="1">
                                {{ t('Негізгі жұмыс орны') }}
                            </option>

                            <option value="0">
                                {{ t('Қосымша жұмыс орны') }}
                            </option>
                        </select>

                        <p
                            v-if="errors.is_primary"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ errors.is_primary }}
                        </p>
                    </div>

                    <!-- Бекіту -->
                    <div class="flex items-end">
                        <button
                            type="submit"
                            :disabled="
                                processing || availableTeachers.length === 0
                            "
                            class="w-full rounded-md bg-black px-5 py-2 text-white disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                processing
                                    ? t('Бекітілуде...')
                                    : t('Педагогты бекіту')
                            }}
                        </button>
                    </div>
                </Form>

                <p
                    v-if="availableTeachers.length === 0"
                    class="mt-4 text-sm text-muted-foreground"
                >
                    {{ t('Бекітуге қолжетімді педагог жоқ.') }}
                </p>
            </div>

            <!-- Педагогтар саны -->
            <div class="mb-6 rounded-xl border bg-card p-5">
                <div class="text-sm text-muted-foreground">
                    {{ t('Педагогтар саны') }}
                </div>

                <div class="mt-1 text-3xl font-bold">
                    {{ teacherCount }}
                </div>
            </div>

            <!-- Педагогтар кестесі -->
            <form
                class="mb-4 space-y-2 rounded-xl border bg-card p-4"
                @submit.prevent="filter"
            >
                <label
                    for="assigned-teacher-search"
                    class="mb-2 block text-sm font-medium"
                    >{{ t('Аты-жөні бойынша іздеу') }}</label
                >
                <Input
                    id="assigned-teacher-search"
                    v-model="search.name"
                    type="search"
                    maxlength="150"
                    class="min-h-11 sm:max-w-sm"
                />
                <div class="flex flex-wrap gap-2">
                    <Button class="min-h-11" :disabled="search.processing">{{
                        t('Іздеу')
                    }}</Button>
                    <Button variant="outline" class="min-h-11" as-child
                        ><Link :href="schoolTeachers(school.id)">{{
                            t('Тазарту')
                        }}</Link></Button
                    >
                </div>
                <p
                    v-if="search.errors.name"
                    class="text-destructive"
                    role="alert"
                >
                    {{ search.errors.name }}
                </p>
            </form>
            <p
                v-if="!teachers.data.length"
                class="rounded-xl border border-dashed p-5 text-muted-foreground"
            >
                {{
                    teacherCount
                        ? t('Іздеуге сәйкес педагог табылмады.')
                        : t('Бұл мектепке педагог бекітілмеген.')
                }}
            </p>
            <ul v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <li
                    v-for="teacher in teachers.data"
                    :key="teacher.id"
                    class="flex min-w-0 flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl bg-blue-100 text-sm font-semibold text-blue-800"
                            aria-hidden="true"
                            >{{
                                teacher.name
                                    .trim()
                                    .slice(0, 1)
                                    .toLocaleUpperCase('kk-KZ')
                            }}</span
                        >
                        <div class="min-w-0 wrap-anywhere">
                            <h2 class="font-semibold">{{ teacher.name }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ teacher.username }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ teacher.email }}
                            </p>
                        </div>
                    </div>
                    <span
                        class="w-fit rounded-full bg-muted px-2.5 py-1 text-xs font-medium"
                    >
                        {{
                            teacher.is_primary
                                ? t('Негізгі жұмыс орны')
                                : t('Қосымша жұмыс орны')
                        }}
                    </span>
                    <div class="mt-auto flex flex-wrap gap-2">
                        <Button variant="outline" class="min-h-11" as-child>
                            <Link :href="dossier(teacher.id)">{{
                                t('Жеке іс')
                            }}</Link>
                        </Button>
                        <template v-if="!teacher.is_primary">
                            <Button
                                variant="outline"
                                class="min-h-11"
                                @click="makeTeacherPrimary(school.id, teacher)"
                                >{{ t('Негізгі ету') }}</Button
                            >
                            <Button
                                variant="outline"
                                class="min-h-11"
                                @click="detachTeacher(school.id, teacher)"
                                >{{ t('Ажырату') }}</Button
                            >
                        </template>
                    </div>
                    <p
                        v-if="teacher.is_primary"
                        class="text-xs text-muted-foreground"
                    >
                        {{
                            t('Негізгі жұмыс орнын тікелей ажыратуға болмайды.')
                        }}
                    </p>
                </li>
            </ul>
            <PageLinks
                :previous="teachers.prev_page_url"
                :next="teachers.next_page_url"
            />
        </div>
    </div>
</template>
