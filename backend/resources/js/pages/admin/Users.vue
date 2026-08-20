<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const page = usePage<{
    flash: {
        success?: string;
    };
}>();
const deleteUser = (user: { id: number; name: string }) => {
    if (!confirm(`${user.name} пайдаланушысын өшіруге сенімдісіз бе?`)) {
        return;
    }

    router.delete(`/admin/users/${user.id}`, {
        preserveScroll: true,
    });
};
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
        <div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold">
        PedBase KZ — Пайдаланушылар
    </h1>

    <Link
        href="/admin/users/create"
        class="rounded-md bg-black px-4 py-2 text-sm font-medium text-white"
    >
        + Пайдаланушы қосу
    </Link>
</div>
<div
    v-if="page.props.flash?.success"
    class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-700"
>
    {{ page.props.flash.success }}
</div>
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
                        <td class="p-3">
    <div class="flex items-center gap-2">
        <Link
            :href="`/admin/users/${user.id}/edit`"
            class="rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-gray-50"
        >
            Өңдеу
        </Link>

        <button
            type="button"
            :disabled="page.props.auth.user.id === user.id"
            class="rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
            @click="deleteUser(user)"
        >
            Өшіру
        </button>
    </div>
       
</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>