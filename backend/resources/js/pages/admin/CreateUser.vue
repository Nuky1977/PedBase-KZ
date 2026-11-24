<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
</script>

<template>
    <Head :title="t('Пайдаланушы қосу')" />

    <div class="mx-auto max-w-2xl p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                {{ t('Жаңа пайдаланушы қосу') }}
            </h1>

            <p class="mt-1 text-sm text-muted-foreground">
                {{ t('PedBase KZ жүйесіне жаңа пайдаланушы тіркеу') }}
            </p>
        </div>

        <Form
            action="/admin/users"
            method="post"
            #default="{ errors, processing }"
            class="space-y-5"
        >
            <div>
                <label for="name" class="mb-1 block font-medium">
                    {{ t('Аты-жөні') }}
                </label>

                <input
                    id="name"
                    name="name"
                    type="text"
                    required
                    class="w-full rounded-md border p-2"
                />

                <p v-if="errors.name" class="mt-1 text-sm text-red-600">
                    {{ errors.name }}
                </p>
            </div>

            <div>
                <label for="username" class="mb-1 block font-medium">
                    {{ t('Логин') }}
                </label>

                <input
                    id="username"
                    name="username"
                    type="text"
                    required
                    class="w-full rounded-md border p-2"
                />

                <p v-if="errors.username" class="mt-1 text-sm text-red-600">
                    {{ errors.username }}
                </p>
            </div>

            <div>
                <label for="email" class="mb-1 block font-medium">
                    {{ t('Email') }}
                </label>

                <input
                    id="email"
                    name="email"
                    type="email"
                    required
                    class="w-full rounded-md border p-2"
                />

                <p v-if="errors.email" class="mt-1 text-sm text-red-600">
                    {{ errors.email }}
                </p>
            </div>

            <div>
                <label for="role" class="mb-1 block font-medium">
                    {{ t('Рөлі') }}
                </label>

                <select
                    id="role"
                    name="role"
                    required
                    class="w-full rounded-md border p-2"
                >
                    <option value="teacher">{{ t('Педагог') }}</option>
                    <option value="school_admin">
                        {{ t('Мектеп әкімшісі') }}
                    </option>
                    <option value="methodist">{{ t('Әдіскер') }}</option>
                    <option value="admin">{{ t('Жүйе әкімшісі') }}</option>
                </select>

                <p v-if="errors.role" class="mt-1 text-sm text-red-600">
                    {{ errors.role }}
                </p>
            </div>

            <div>
                <label for="password" class="mb-1 block font-medium">
                    {{ t('Уақытша құпия сөз') }}
                </label>

                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    class="w-full rounded-md border p-2"
                />

                <p v-if="errors.password" class="mt-1 text-sm text-red-600">
                    {{ errors.password }}
                </p>
            </div>

            <button
                type="submit"
                :disabled="processing"
                class="rounded-md bg-black px-5 py-2 text-white disabled:opacity-50"
            >
                {{ processing ? t('Сақталуда...') : t('Пайдаланушыны сақтау') }}
            </button>
        </Form>
    </div>
</template>
