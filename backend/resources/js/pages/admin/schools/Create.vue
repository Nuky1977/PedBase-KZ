<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Мектеп қосу" />

    <div class="min-h-screen bg-gray-50 p-8">
        <div class="mx-auto max-w-2xl">
            <div class="mb-6">
                <h1 class="text-3xl font-bold">
                    Жаңа мектеп қосу
                </h1>

                <p class="mt-2 text-gray-500">
                    Аудан мектебінің негізгі мәліметтерін енгізіңіз
                </p>
            </div>

            <div class="rounded-xl border bg-white p-6">
                <Form
                    action="/admin/schools"
                    method="post"
                    #default="{ errors, processing }"
                    class="space-y-5"
                >
                    <div>
                        <label for="name" class="mb-1 block font-medium">
                            Мектептің толық атауы *
                        </label>

                        <input
                            id="name"
                            name="name"
                            type="text"
                            required
                            class="w-full rounded-md border p-2"
                            placeholder="Мысалы: Абай атындағы орта мектебі"
                        />

                        <p
                            v-if="errors.name"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ errors.name }}
                        </p>
                    </div>

                    <div>
                        <label for="short_name" class="mb-1 block font-medium">
                            Қысқаша атауы
                        </label>

                        <input
                            id="short_name"
                            name="short_name"
                            type="text"
                            class="w-full rounded-md border p-2"
                            placeholder="Мысалы: Абай ОМ"
                        />

                        <p
                            v-if="errors.short_name"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ errors.short_name }}
                        </p>
                    </div>

                    <div>
                        <label for="bin" class="mb-1 block font-medium">
                            БСН
                        </label>

                        <input
                            id="bin"
                            name="bin"
                            type="text"
                            inputmode="numeric"
                            maxlength="12"
                            class="w-full rounded-md border p-2"
                            placeholder="12 таңбалы БСН"
                        />

                        <p
                            v-if="errors.bin"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ errors.bin }}
                        </p>
                    </div>

                    <div>
                        <label for="type" class="mb-1 block font-medium">
                            Мектеп түрі
                        </label>

                        <select
                            id="type"
                            name="type"
                            class="w-full rounded-md border p-2"
                        >
                            <option value="">Таңдаңыз</option>
                            <option value="Жалпы білім беретін мектеп">
                                Жалпы білім беретін мектеп
                            </option>
                            <option value="Мектеп-гимназия">
                                Мектеп-гимназия
                            </option>
                            <option value="Мектеп-лицей">
                                Мектеп-лицей
                            </option>
                            <option value="Негізгі орта мектеп">
                                Негізгі орта мектеп
                            </option>
                            <option value="Бастауыш мектеп">
                                Бастауыш мектеп
                            </option>
                        </select>

                        <p
                            v-if="errors.type"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ errors.type }}
                        </p>
                    </div>

                    <div>
                        <label for="locality" class="mb-1 block font-medium">
                            Елді мекен
                        </label>

                        <input
                            id="locality"
                            name="locality"
                            type="text"
                            class="w-full rounded-md border p-2"
                            placeholder="Мысалы: Сарқан қаласы"
                        />

                        <p
                            v-if="errors.locality"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ errors.locality }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="is_active"
                            name="is_active"
                            type="checkbox"
                            value="1"
                            checked
                        />

                        <label for="is_active" class="font-medium">
                            Белсенді мектеп
                        </label>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button
                            type="submit"
                            :disabled="processing"
                            class="rounded-md bg-black px-5 py-2 text-white disabled:opacity-50"
                        >
                            {{ processing ? 'Сақталуда...' : 'Мектепті сақтау' }}
                        </button>

                        <Link
                            href="/admin/schools"
                            class="rounded-md border px-5 py-2"
                        >
                            Болдырмау
                        </Link>
                    </div>
                </Form>
            </div>
        </div>
    </div>
</template>