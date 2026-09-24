<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Педагог кабинеті" />

    <div class="min-h-screen bg-gray-50 p-8">
        <div class="mx-auto max-w-7xl">
            <div class="mb-8">
                <h1 class="text-3xl font-bold">
                    Педагог кабинеті
                </h1>

                <p class="mt-2 text-gray-500">
                    PedBase KZ — педагогтің электрондық іс қағазы
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border bg-white p-5">
                    <div class="text-sm text-gray-500">
                        Жеке мәліметтер
                    </div>
                    <div class="mt-2 text-lg font-semibold">
                        Профиль
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-5">
                    <div class="text-sm text-gray-500">
                        Біліктілік санаты
                    </div>
                    <div class="mt-2 text-3xl font-bold">
                        —
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-5">
                    <div class="text-sm text-gray-500">
                        Курстар
                    </div>
                    <div class="mt-2 text-3xl font-bold">
                        —
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-5">
                    <div class="text-sm text-gray-500">
                        Жетістіктер
                    </div>
                    <div class="mt-2 text-3xl font-bold">
                        —
                    </div>
                </div>
            </div>

            <div class="mt-8 rounded-xl border bg-white p-6">
                <h2 class="text-xl font-semibold">
                    Менің электрондық іс қағазым
                </h2>

                <p class="mt-2 text-gray-500">
                    Кейін бұл бөлімде жұмыс орны, негізгі пән,
                    лауазым, санат, курстар, аттестаттау,
                    жетістіктер және құжаттар көрсетіледі.
                </p>
            </div>
        </div>
    </div>
</template>