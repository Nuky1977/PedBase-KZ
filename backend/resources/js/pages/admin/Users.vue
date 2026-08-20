<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    users: Array<{
        id: number;
        name: string;
        username: string;
        role: string;
        email: string;
    }>;
}>();
</script>

<template>
    <Head title="Пайдаланушылар" />

    <div class="p-8">
        <h1 class="mb-6 text-2xl font-bold">
            PedBase KZ — Пайдаланушылар
        </h1>

        <div class="overflow-hidden rounded-lg border">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-3 text-left">Аты-жөні</th>
                        <th class="p-3 text-left">Логин</th>
                        <th class="p-3 text-left">Рөлі</th>
                        <th class="p-3 text-left">Email</th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="user in users"
                        :key="user.id"
                        class="border-t"
                    >
                        <td class="p-3">{{ user.name }}</td>
                        <td class="p-3">{{ user.username }}</td>
                        <td class="p-3">{{ user.role }}</td>
                        <td class="p-3">{{ user.email }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>