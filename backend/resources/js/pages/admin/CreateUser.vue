<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Пайдаланушы қосу" />

    <div class="mx-auto max-w-2xl p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">
                Жаңа пайдаланушы қосу
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                PedBase KZ жүйесіне жаңа пайдаланушы тіркеу
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
                    Аты-жөні
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
                    Логин
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
                    Email
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
                    Рөлі
                </label>

                <select
                    id="role"
                    name="role"
                    required
                    class="w-full rounded-md border p-2"
                >
                    <option value="teacher">Мұғалім</option>
                    <option value="admin">Әкімші</option>
                </select>

                <p v-if="errors.role" class="mt-1 text-sm text-red-600">
                    {{ errors.role }}
                </p>
            </div>

            <div>
                <label for="password" class="mb-1 block font-medium">
                    Уақытша құпия сөз
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
                {{ processing ? 'Сақталуда...' : 'Пайдаланушыны сақтау' }}
            </button>
        </Form>
    </div>
</template>