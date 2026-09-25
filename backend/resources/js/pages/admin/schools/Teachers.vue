<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';

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

defineProps<{
    school: School;
    teachers: Teacher[];
    availableTeachers: Teacher[];
}>();

const detachTeacher = (
    schoolId: number,
    teacher: Teacher,
): void => {
    if (teacher.is_primary) {
        return;
    }

    const confirmed = window.confirm(
        `${teacher.name} педагогін осы мектептен ажыратуға сенімдісіз бе?`,
    );

    if (!confirmed) {
        return;
    }

    router.delete(
        `/admin/schools/${schoolId}/teachers/${teacher.id}`,
        {
            preserveScroll: true,
        },
    );
};

const makeTeacherPrimary = (
    schoolId: number,
    teacher: Teacher,
): void => {
    if (teacher.is_primary) {
        return;
    }

    const confirmed = window.confirm(
        `${teacher.name} педагогі үшін осы мектепті негізгі жұмыс орны ретінде белгілеуге сенімдісіз бе?`,
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
    <Head :title="`${school.name} — педагогтар`" />

    <div class="min-h-screen bg-gray-50 p-8">
        <div class="mx-auto max-w-7xl">
            <!-- Тақырып -->
            <div class="mb-6 flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold">
                        {{ school.name }}
                    </h1>

                    <p class="mt-2 text-gray-500">
                        Мектепке тіркелген педагогтарды басқару
                    </p>
                </div>

                <Link
                    href="/admin/schools"
                    class="rounded-md border bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50"
                >
                    ← Мектептерге қайту
                </Link>
            </div>

            <!-- Педагогты мектепке бекіту -->
            <div class="mb-6 rounded-xl border bg-white p-6">
                <h2 class="text-xl font-semibold">
                    Педагогты мектепке бекіту
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    Жүйеде тіркелген педагогты таңдап,
                    жұмыс орнының түрін көрсетіңіз.
                </p>

                <Form
                    :action="`/admin/schools/${school.id}/teachers`"
                    method="post"
                    #default="{ errors, processing }"
                    class="mt-5 grid gap-4 md:grid-cols-3"
                >
                    <!-- Педагог -->
                    <div>
                        <label
                            for="teacher_id"
                            class="mb-1 block font-medium"
                        >
                            Педагог
                        </label>

                        <select
                            id="teacher_id"
                            name="teacher_id"
                            required
                            class="w-full rounded-md border p-2"
                        >
                            <option value="">
                                Педагогты таңдаңыз
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
                        <label
                            for="is_primary"
                            class="mb-1 block font-medium"
                        >
                            Жұмыс орны
                        </label>

                        <select
                            id="is_primary"
                            name="is_primary"
                            required
                            class="w-full rounded-md border p-2"
                        >
                            <option value="1">
                                Негізгі жұмыс орны
                            </option>

                            <option value="0">
                                Қосымша жұмыс орны
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
                                processing ||
                                availableTeachers.length === 0
                            "
                            class="w-full rounded-md bg-black px-5 py-2 text-white disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                processing
                                    ? 'Бекітілуде...'
                                    : 'Педагогты бекіту'
                            }}
                        </button>
                    </div>
                </Form>

                <p
                    v-if="availableTeachers.length === 0"
                    class="mt-4 text-sm text-gray-500"
                >
                    Бекітуге қолжетімді педагог жоқ.
                </p>
            </div>

            <!-- Педагогтар саны -->
            <div class="mb-6 rounded-xl border bg-white p-5">
                <div class="text-sm text-gray-500">
                    Педагогтар саны
                </div>

                <div class="mt-1 text-3xl font-bold">
                    {{ teachers.length }}
                </div>
            </div>

            <!-- Педагогтар кестесі -->
            <div class="overflow-hidden rounded-xl border bg-white">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-3 text-left">
                                №
                            </th>

                            <th class="p-3 text-left">
                                Аты-жөні
                            </th>

                            <th class="p-3 text-left">
                                Логин
                            </th>

                            <th class="p-3 text-left">
                                Email
                            </th>

                            <th class="p-3 text-left">
                                Жұмыс орны
                            </th>

                            <th class="p-3 text-left">
                                Әрекет
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="(teacher, index) in teachers"
                            :key="teacher.id"
                            class="border-t"
                        >
                            <!-- № -->
                            <td class="p-3">
                                {{ index + 1 }}
                            </td>

                            <!-- Аты-жөні -->
                            <td class="p-3 font-medium">
                                {{ teacher.name }}
                            </td>

                            <!-- Логин -->
                            <td class="p-3">
                                {{ teacher.username }}
                            </td>

                            <!-- Email -->
                            <td class="p-3">
                                {{ teacher.email }}
                            </td>

                            <!-- Жұмыс орны -->
                            <td class="p-3">
                                <span
                                    v-if="teacher.is_primary"
                                    class="rounded-full bg-green-50 px-3 py-1 text-sm text-green-700"
                                >
                                    Негізгі жұмыс орны
                                </span>

                                <span
                                    v-else
                                    class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-600"
                                >
                                    Қосымша жұмыс орны
                                </span>
                            </td>

                            <!-- Әрекеттер -->
                            <td class="p-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <!-- Қосымша мектепті негізгі ету -->
                                    <button
                                        v-if="!teacher.is_primary"
                                        type="button"
                                        class="rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-gray-50"
                                        @click="
                                            makeTeacherPrimary(
                                                school.id,
                                                teacher,
                                            )
                                        "
                                    >
                                        Негізгі ету
                                    </button>

                                    <!-- Мектептен ажырату -->
                                    <button
                                        type="button"
                                        :disabled="teacher.is_primary"
                                        class="rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                                        @click="
                                            detachTeacher(
                                                school.id,
                                                teacher,
                                            )
                                        "
                                    >
                                        Ажырату
                                    </button>
                                </div>

                                <p
                                    v-if="teacher.is_primary"
                                    class="mt-1 max-w-52 text-xs text-gray-500"
                                >
                                    Негізгі жұмыс орнын тікелей
                                    ажыратуға болмайды
                                </p>
                            </td>
                        </tr>

                        <!-- Педагогтар жоқ -->
                        <tr v-if="teachers.length === 0">
                            <td
                                colspan="6"
                                class="p-8 text-center text-gray-500"
                            >
                                Бұл мектепке педагогтар әлі тіркелмеген.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>