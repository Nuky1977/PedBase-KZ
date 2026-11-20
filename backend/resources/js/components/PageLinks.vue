<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

defineProps<{ previous: string | null; next: string | null }>();
</script>

<template>
    <nav v-if="previous || next" aria-label="Бет ауыстыру" class="flex gap-2">
        <Button v-if="previous" variant="outline" as-child>
            <Link :href="previous" preserve-scroll>Алдыңғы бет</Link>
        </Button>
        <Button v-if="next" variant="outline" as-child>
            <Link :href="next" preserve-scroll>Келесі бет</Link>
        </Button>
    </nav>
</template>
