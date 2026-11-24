<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/composables/useTranslation';
import { dashboard, login } from '@/routes';

const { t } = useTranslation();

const page = usePage();
</script>

<template>
    <Head :title="t('Басты бет')" />
    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <header class="border-b">
            <div class="mx-auto flex max-w-6xl items-center px-4 py-4 sm:px-8">
                <AppLogo />
                <LocaleSwitcher />
            </div>
        </header>
        <main
            class="mx-auto grid w-full max-w-6xl flex-1 content-center px-4 py-12 sm:px-8"
        >
            <section
                class="rounded-3xl border border-teal-900/15 bg-teal-50 p-6 sm:p-12 lg:p-16 dark:border-teal-300/20 dark:bg-teal-950/45"
            >
                <h1
                    class="max-w-3xl text-3xl leading-tight font-bold tracking-tight sm:text-5xl"
                >
                    {{ t('Білім беру ұйымдарын басқару жүйесі') }}
                </h1>
                <p
                    class="mt-4 max-w-xl text-base leading-relaxed text-muted-foreground"
                >
                    {{
                        t(
                            'Ұйымдар каталогы, педагогтердің жеке істері, еңбек деректері және есептер.',
                        )
                    }}
                </p>
                <Button
                    as-child
                    class="mt-7 h-11 bg-teal-800 text-white hover:bg-teal-900 dark:bg-teal-300 dark:text-teal-950 dark:hover:bg-teal-200"
                >
                    <Link :href="page.props.auth.user ? dashboard() : login()">
                        {{
                            page.props.auth.user
                                ? t('Жеке кабинетке өту')
                                : t('Жүйеге кіру')
                        }}
                        <ArrowRight aria-hidden="true" />
                    </Link>
                </Button>
            </section>
        </main>
    </div>
</template>
