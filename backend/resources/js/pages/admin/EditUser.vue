<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

defineProps<{
    user: {
        id: number;
        name: string;
        username: string;
        email: string;
        role: string;
    };
    isSelf: boolean;
}>();
</script>

<template>
    <Head :title="t('Пайдаланушыны өңдеу')" />

    <div class="mx-auto max-w-2xl p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                {{ t('Пайдаланушыны өңдеу') }}
            </h1>

            <p class="mt-1 text-sm text-muted-foreground">
                {{ t('Пайдаланушы мәліметтерін өзгерту') }}
            </p>
        </div>

        <Form
            :action="`/admin/users/${user.id}`"
            method="put"
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
                    :value="user.name"
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
                    :value="user.username"
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
                    :value="user.email"
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
                    :value="user.role"
                    :disabled="isSelf"
                    required
                    class="w-full rounded-md border p-2 disabled:bg-gray-100 disabled:text-muted-foreground"
                >
                    <option value="teacher">{{ t('Педагог') }}</option>
                    <option value="school_admin">
                        {{ t('Мектеп әкімшісі') }}
                    </option>
                    <option value="methodist">{{ t('Әдіскер') }}</option>
                    <option value="admin">{{ t('Жүйе әкімшісі') }}</option>
                </select>
                <input
                    v-if="isSelf"
                    type="hidden"
                    name="role"
                    :value="user.role"
                />
                <p v-if="isSelf" class="mt-1 text-sm text-muted-foreground">
                    {{ t('Өз аккаунтыңыздың рөлін өзгерте алмайсыз.') }}
                </p>

                <p v-if="errors.role" class="mt-1 text-sm text-red-600">
                    {{ errors.role }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="processing"
                    class="rounded-md bg-black px-5 py-2 text-white disabled:opacity-50"
                >
                    {{
                        processing
                            ? t('Сақталуда...')
                            : t('Өзгерістерді сақтау')
                    }}
                </button>

                <Link href="/admin/users" class="rounded-md border px-5 py-2">
                    {{ t('Болдырмау') }}
                </Link>
            </div>
        </Form>
    </div>
</template>
