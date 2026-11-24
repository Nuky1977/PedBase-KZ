<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        aria-hidden="true"
        focusable="false"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M12 7c-2.4-2.1-5.2-2.7-8.5-2v12c3.3-.7 6.1-.1 8.5 2 2.4-2.1 5.2-2.7 8.5-2V5c-3.3-.7-6.1-.1-8.5 2v12M7 9h2m6 0h2M7 12h2m6 0h2"
        />
    </svg>
</template>
