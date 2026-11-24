<script setup lang="ts">
defineProps<{
    statistics: {
        catalog: boolean;
        primary: number;
        secondary: number;
        organizations?: number;
        teachers?: number;
    };
}>();
</script>

<template>
    <section
        class="min-w-0 space-y-3"
        aria-label="Жұмыс орындарының көрсеткіштері"
    >
        <h2 class="text-lg font-semibold">
            {{
                statistics.catalog
                    ? 'Каталогтағы жұмыс орындары'
                    : 'Мектептердегі жұмыс орындары'
            }}
        </h2>
        <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-if="statistics.organizations !== undefined"
                class="rounded-xl border bg-card p-4"
            >
                <dt class="text-sm text-muted-foreground">
                    Қолжетімді ұйымдар
                </dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ statistics.organizations }}
                </dd>
            </div>
            <div
                v-if="statistics.teachers !== undefined"
                class="rounded-xl border bg-card p-4"
            >
                <dt class="text-sm text-muted-foreground">
                    Бірегей педагогтер
                </dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ statistics.teachers }}
                </dd>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <dt class="text-sm text-muted-foreground">
                    Негізгі жұмыс орындары
                </dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ statistics.primary }}
                </dd>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <dt class="text-sm text-muted-foreground">
                    Қосымша жұмыс орындары
                </dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ statistics.secondary }}
                </dd>
            </div>
        </dl>
    </section>
</template>
