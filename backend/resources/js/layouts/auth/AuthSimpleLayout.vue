<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { useTranslation } from '@/composables/useTranslation';
import { home } from '@/routes';

const { t } = useTranslation();

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <LocaleSwitcher class="self-end" />
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 rounded-lg font-semibold focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                    >
                        <div
                            class="flex size-11 items-center justify-center rounded-xl bg-teal-800 text-white dark:bg-teal-300 dark:text-teal-950"
                        >
                            <AppLogoIcon class="size-7" />
                        </div>
                        <span>PedBase-KZ</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 v-if="title" class="text-xl font-medium">
                            {{ t(title) }}
                        </h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description ? t(description) : '' }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
