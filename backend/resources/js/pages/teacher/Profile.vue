<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';

type User = {
    id: number;
    name: string;
    username: string;
    email: string;
};

type Profile = {
    iin: string | null;
    birth_date: string | null;
    gender: string | null;
    phone: string | null;
    education_level: string | null;
    educational_institution: string | null;
    graduation_year: number | null;
    diploma_number: string | null;
    specialty: string | null;
    total_experience_months: number | null;
    teaching_experience_months: number | null;
};

defineProps<{
    user: User;
    profile: Profile | null;
}>();
</script>

<template>
    <Head title="Менің профилім" />

    <div class="min-h-screen bg-gray-50 p-8">
        <div class="mx-auto max-w-5xl">
            <!-- Тақырып -->
            <div class="mb-6 flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold">
                        Менің профилім
                    </h1>

                    <p class="mt-2 text-gray-500">
                        PedBase KZ — педагогтің электрондық іс қағазы
                    </p>
                </div>

                <Link
                    href="/teacher"
                    class="rounded-md border bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50"
                >
                    ← Кабинетке қайту
                </Link>
            </div>

            <!-- Аккаунт мәліметтері -->
            <div class="mb-6 rounded-xl border bg-white p-6">
                <h2 class="mb-5 text-xl font-semibold">
                    Аккаунт мәліметтері
                </h2>

                <div class="grid gap-5 md:grid-cols-3">
                    <div>
                        <div class="text-sm text-gray-500">
                            Аты-жөні
                        </div>

                        <div class="mt-1 font-medium">
                            {{ user.name }}
                        </div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">
                            Логин
                        </div>

                        <div class="mt-1 font-medium">
                            {{ user.username }}
                        </div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">
                            Email
                        </div>

                        <div class="mt-1 font-medium">
                            {{ user.email }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Профиль формасы -->
            <Form
                action="/teacher/profile"
                method="put"
                #default="{ errors, processing }"
                class="space-y-6"
            >
                <!-- Жеке мәліметтер -->
                <div class="rounded-xl border bg-white p-6">
                    <h2 class="mb-5 text-xl font-semibold">
                        Жеке мәліметтер
                    </h2>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                for="iin"
                                class="mb-1 block font-medium"
                            >
                                ЖСН
                            </label>

                            <input
                                id="iin"
                                name="iin"
                                type="text"
                                inputmode="numeric"
                                maxlength="12"
                                :value="profile?.iin ?? ''"
                                class="w-full rounded-md border p-2"
                                placeholder="12 таңбалы ЖСН"
                            />

                            <p
                                v-if="errors.iin"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ errors.iin }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="birth_date"
                                class="mb-1 block font-medium"
                            >
                                Туған күні
                            </label>

                            <input
                                id="birth_date"
                                name="birth_date"
                                type="date"
                                :value="profile?.birth_date ?? ''"
                                class="w-full rounded-md border p-2"
                            />

                            <p
                                v-if="errors.birth_date"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ errors.birth_date }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="gender"
                                class="mb-1 block font-medium"
                            >
                                Жынысы
                            </label>

                            <select
                                id="gender"
                                name="gender"
                                :value="profile?.gender ?? ''"
                                class="w-full rounded-md border p-2"
                            >
                                <option value="">
                                    Таңдаңыз
                                </option>

                                <option value="Ер">
                                    Ер
                                </option>

                                <option value="Әйел">
                                    Әйел
                                </option>
                            </select>

                            <p
                                v-if="errors.gender"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ errors.gender }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="phone"
                                class="mb-1 block font-medium"
                            >
                                Телефон
                            </label>

                            <input
                                id="phone"
                                name="phone"
                                type="text"
                                :value="profile?.phone ?? ''"
                                class="w-full rounded-md border p-2"
                                placeholder="+7 700 000 00 00"
                            />

                            <p
                                v-if="errors.phone"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ errors.phone }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Білімі -->
                <div class="rounded-xl border bg-white p-6">
                    <h2 class="mb-5 text-xl font-semibold">
                        Білімі
                    </h2>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                for="education_level"
                                class="mb-1 block font-medium"
                            >
                                Білім деңгейі
                            </label>

                            <select
                                id="education_level"
                                name="education_level"
                                :value="profile?.education_level ?? ''"
                                class="w-full rounded-md border p-2"
                            >
                                <option value="">
                                    Таңдаңыз
                                </option>

                                <option value="Жоғары">
                                    Жоғары
                                </option>

                                <option value="Жоғары оқу орнынан кейінгі">
                                    Жоғары оқу орнынан кейінгі
                                </option>

                                <option value="Техникалық және кәсіптік">
                                    Техникалық және кәсіптік
                                </option>

                                <option value="Орта білімнен кейінгі">
                                    Орта білімнен кейінгі
                                </option>
                            </select>

                            <p
                                v-if="errors.education_level"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ errors.education_level }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="educational_institution"
                                class="mb-1 block font-medium"
                            >
                                Білім беру ұйымы
                            </label>

                            <input
                                id="educational_institution"
                                name="educational_institution"
                                type="text"
                                :value="
                                    profile?.educational_institution ?? ''
                                "
                                class="w-full rounded-md border p-2"
                                placeholder="Университет немесе колледж атауы"
                            />

                            <p
                                v-if="errors.educational_institution"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ errors.educational_institution }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="graduation_year"
                                class="mb-1 block font-medium"
                            >
                                Бітірген жылы
                            </label>

                            <input
                                id="graduation_year"
                                name="graduation_year"
                                type="number"
                                min="1900"
                                :value="profile?.graduation_year ?? ''"
                                class="w-full rounded-md border p-2"
                                placeholder="Мысалы: 2010"
                            />

                            <p
                                v-if="errors.graduation_year"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ errors.graduation_year }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="diploma_number"
                                class="mb-1 block font-medium"
                            >
                                Диплом нөмірі
                            </label>

                            <input
                                id="diploma_number"
                                name="diploma_number"
                                type="text"
                                :value="profile?.diploma_number ?? ''"
                                class="w-full rounded-md border p-2"
                            />

                            <p
                                v-if="errors.diploma_number"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ errors.diploma_number }}
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <label
                                for="specialty"
                                class="mb-1 block font-medium"
                            >
                                Диплом бойынша мамандығы
                            </label>

                            <input
                                id="specialty"
                                name="specialty"
                                type="text"
                                :value="profile?.specialty ?? ''"
                                class="w-full rounded-md border p-2"
                                placeholder="Мысалы: Информатика"
                            />

                            <p
                                v-if="errors.specialty"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ errors.specialty }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Еңбек өтілі -->
                <div class="rounded-xl border bg-white p-6">
                    <h2 class="mb-2 text-xl font-semibold">
                        Еңбек өтілі
                    </h2>

                    <p class="mb-5 text-sm text-gray-500">
                        Өтіл әзірге ай санымен енгізіледі.
                        Мысалы: 10 жыл = 120 ай.
                    </p>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                for="total_experience_months"
                                class="mb-1 block font-medium"
                            >
                                Жалпы еңбек өтілі, ай
                            </label>

                            <input
                                id="total_experience_months"
                                name="total_experience_months"
                                type="number"
                                min="0"
                                max="1200"
                                :value="
                                    profile?.total_experience_months ?? ''
                                "
                                class="w-full rounded-md border p-2"
                                placeholder="Мысалы: 264"
                            />

                            <p
                                v-if="errors.total_experience_months"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ errors.total_experience_months }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="teaching_experience_months"
                                class="mb-1 block font-medium"
                            >
                                Педагогикалық өтілі, ай
                            </label>

                            <input
                                id="teaching_experience_months"
                                name="teaching_experience_months"
                                type="number"
                                min="0"
                                max="1200"
                                :value="
                                    profile?.teaching_experience_months ?? ''
                                "
                                class="w-full rounded-md border p-2"
                                placeholder="Мысалы: 252"
                            />

                            <p
                                v-if="errors.teaching_experience_months"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ errors.teaching_experience_months }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Сақтау -->
                <div class="flex items-center justify-end gap-3">
                    <Link
                        href="/teacher"
                        class="rounded-md border bg-white px-5 py-2"
                    >
                        Болдырмау
                    </Link>

                    <button
                        type="submit"
                        :disabled="processing"
                        class="rounded-md bg-black px-6 py-2 font-medium text-white disabled:opacity-50"
                    >
                        {{
                            processing
                                ? 'Сақталуда...'
                                : 'Профильді сақтау'
                        }}
                    </button>
                </div>
            </Form>
        </div>
    </div>
</template>