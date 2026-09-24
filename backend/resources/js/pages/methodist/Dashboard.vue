<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Әдіскер кабинеті" />

    <div class="min-h-screen bg-gray-50 p-8">
        <div class="mx-auto max-w-7xl">
            <div class="mb-8">
                <h1 class="text-3xl font-bold">
                    Әдіскер кабинеті
                </h1>

                <p class="mt-2 text-gray-500">
                    PedBase KZ — аудан педагогтері туралы мәліметтерді басқару
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-xl border bg-white p-5">
                    <div class="text-sm text-gray-500">Мектептер</div>
                    <div class="mt-2 text-3xl font-bold">—</div>
                </div>

                <div class="rounded-xl border bg-white p-5">
                    <div class="text-sm text-gray-500">Педагогтар</div>
                    <div class="mt-2 text-3xl font-bold">—</div>
                </div>

                <div class="rounded-xl border bg-white p-5">
                    <div class="text-sm text-gray-500">Сапалық құрам</div>
                    <div class="mt-2 text-3xl font-bold">—</div>
                </div>

                <div class="rounded-xl border bg-white p-5">
                    <div class="text-sm text-gray-500">Курстар</div>
                    <div class="mt-2 text-3xl font-bold">—</div>
                </div>

                <div class="rounded-xl border bg-white p-5">
                    <div class="text-sm text-gray-500">Аттестаттау</div>
                    <div class="mt-2 text-3xl font-bold">—</div>
                </div>
            </div>

            <div class="mt-8 rounded-xl border bg-white p-6">
                <h2 class="text-xl font-semibold">
                    Мектептер
                </h2>

                <p class="mt-2 text-gray-500">
                    Келесі кезеңде аудан мектептерінің тізімі осы жерде көрсетіледі.
                </p>
            </div>
        </div>
    </div>
</template>