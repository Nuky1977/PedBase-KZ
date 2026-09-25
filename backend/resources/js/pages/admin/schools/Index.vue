<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

type School = {
    id: number;
    name: string;
    short_name: string | null;
    bin: string | null;
    type: string | null;
    locality: string | null;
    is_active: boolean;
};

defineProps<{
    schools: School[];
}>();

const toggleSchoolStatus = (school: School) => {
    const action = school.is_active
        ? 'белсенді емес күйге ауыстыруға'
        : 'белсенді күйге ауыстыруға';

    if (!confirm(`${school.name} мектебін ${action} сенімдісіз бе?`)) {
        return;
    }

    router.patch(
        `/admin/schools/${school.id}/status`,
        {},
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Мектептер" />

    <div class="min-h-screen bg-gray-50 p-8">
        <div class="mx-auto max-w-7xl">
            <!-- Тақырып және мектеп қосу батырмасы -->
            <div class="mb-6 flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold">
                        Мектептер
                    </h1>

                    <p class="mt-2 text-gray-500">
                        PedBase KZ — аудан мектептерінің тізімі
                    </p>
                </div>

                <Link
                    href="/admin/schools/create"
                    class="rounded-md bg-black px-4 py-2 text-sm font-medium text-white"
                >
                    + Мектеп қосу
                </Link>
            </div>

            <!-- Мектептер кестесі -->
            <div class="overflow-hidden rounded-xl border bg-white">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-3 text-left">
                                №
                            </th>

                            <th class="p-3 text-left">
                                Мектеп атауы
                            </th>

                            <th class="p-3 text-left">
                                Қысқаша атауы
                            </th>

                            <th class="p-3 text-left">
                                БСН
                            </th>

                            <th class="p-3 text-left">
                                Түрі
                            </th>

                            <th class="p-3 text-left">
                                Елді мекен
                            </th>

                            <th class="p-3 text-left">
                                Мәртебесі
                            </th>

                            <th class="p-3 text-left">
                                Әрекет
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="(school, index) in schools"
                            :key="school.id"
                            class="border-t"
                        >
                            <!-- № -->
                            <td class="p-3">
                                {{ index + 1 }}
                            </td>

                            <!-- Толық атауы -->
                            <td class="p-3 font-medium">
                                {{ school.name }}
                            </td>

                            <!-- Қысқаша атауы -->
                            <td class="p-3">
                                {{ school.short_name ?? '—' }}
                            </td>

                            <!-- БСН -->
                            <td class="p-3">
                                {{ school.bin ?? '—' }}
                            </td>

                            <!-- Түрі -->
                            <td class="p-3">
                                {{ school.type ?? '—' }}
                            </td>

                            <!-- Елді мекен -->
                            <td class="p-3">
                                {{ school.locality ?? '—' }}
                            </td>

                            <!-- Мәртебесі -->
                            <td class="p-3">
                                <span
                                    v-if="school.is_active"
                                    class="rounded-full bg-green-50 px-3 py-1 text-sm text-green-700"
                                >
                                    Белсенді
                                </span>

                                <span
                                    v-else
                                    class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-500"
                                >
                                    Белсенді емес
                                </span>
                            </td>

                            <!-- Әрекеттер -->
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="`/admin/schools/${school.id}/edit`"
                                        class="rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-gray-50"
                                    >
                                        Өңдеу
                                    </Link>

                                    <button
                                        type="button"
                                        class="rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-gray-50"
                                        @click="toggleSchoolStatus(school)"
                                    >
                                        {{
                                            school.is_active
                                                ? 'Белсенді емес ету'
                                                : 'Белсенді ету'
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Мектептер жоқ болса -->
                        <tr v-if="schools.length === 0">
                            <td
                                colspan="8"
                                class="p-8 text-center text-gray-500"
                            >
                                Мектептер әлі енгізілмеген.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>