<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { InterfaceLocale } from '@/composables/useTranslation';
import { useTranslation } from '@/composables/useTranslation';
import { update } from '@/routes/interface-locale';

const page = usePage();
const { locale, t } = useTranslation();
const busy = ref(false);
const failed = ref(false);
const languages = [
    { code: 'kk', short: 'ҚАЗ', name: 'Қазақша' },
    { code: 'ru', short: 'РУС', name: 'Русский' },
    { code: 'en', short: 'ENG', name: 'English' },
] as const;

function changeLanguage(value: InterfaceLocale) {
    if (busy.value || value === locale.value) {
        return;
    }

    failed.value = false;
    busy.value = true;
    router.post(
        update.url(),
        { locale: value, returnTo: page.url },
        {
            onError: () => {
                failed.value = true;
            },
            onNetworkError: () => {
                failed.value = true;
            },
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="shrink-0">
        <div
            :aria-label="t('Интерфейс тілі')"
            :aria-busy="busy"
            role="group"
            class="inline-flex flex-wrap items-center gap-1 rounded-lg border border-current/15 p-0.5"
        >
            <button
                v-for="language in languages"
                :key="language.code"
                type="button"
                :lang="language.code"
                :aria-label="language.name"
                :aria-pressed="locale === language.code"
                :disabled="busy"
                class="min-h-10 min-w-10 rounded-md px-1.5 text-xs font-semibold hover:underline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-current aria-pressed:bg-primary aria-pressed:text-primary-foreground"
                @click="changeLanguage(language.code)"
            >
                {{ language.short }}
            </button>
        </div>
        <p v-if="failed" role="alert" class="mt-1 text-xs">
            {{ t('Тілді ауыстыру мүмкін болмады.') }}
        </p>
    </div>
</template>
