<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();
</script>

<template>
    <Head :title="t('Әдіскер кабинеті')" />

    <div class="min-h-screen bg-gray-50 p-8">
        <div class="mx-auto max-w-7xl">
            <div class="mb-8">
                <h1 class="text-3xl font-bold">
                    {{ t('Әдіскер кабинеті') }}
                </h1>

                <p class="mt-2 text-gray-500">
                    {{
                        t(
                            'PedBase KZ — аудан педагогтері туралы мәліметтерді басқару',
                        )
                    }}
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-xl border bg-white p-5">
                    <div class="text-sm text-gray-500">
                        {{ t('Мектептер') }}
                    </div>
                    <div class="mt-2 text-3xl font-bold">—</div>
                </div>

                <div class="rounded-xl border bg-white p-5">
                    <div class="text-sm text-gray-500">
                        {{ t('Педагогтар') }}
                    </div>
                    <div class="mt-2 text-3xl font-bold">—</div>
                </div>

                <div class="rounded-xl border bg-white p-5">
                    <div class="text-sm text-gray-500">
                        {{ t('Сапалық құрам') }}
                    </div>
                    <div class="mt-2 text-3xl font-bold">—</div>
                </div>

                <div class="rounded-xl border bg-white p-5">
                    <div class="text-sm text-gray-500">{{ t('Курстар') }}</div>
                    <div class="mt-2 text-3xl font-bold">—</div>
                </div>

                <div class="rounded-xl border bg-white p-5">
                    <div class="text-sm text-gray-500">
                        {{ t('Аттестаттау') }}
                    </div>
                    <div class="mt-2 text-3xl font-bold">—</div>
                </div>
            </div>

            <div class="mt-8 rounded-xl border bg-white p-6">
                <h2 class="text-xl font-semibold">
                    {{ t('Мектептер') }}
                </h2>

                <p class="mt-2 text-gray-500">
                    {{
                        t(
                            'Келесі кезеңде аудан мектептерінің тізімі осы жерде көрсетіледі.',
                        )
                    }}
                </p>
            </div>
        </div>
    </div>
</template>
