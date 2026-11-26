<script setup lang="ts">
import TeacherDashboard from '@/components/TeacherDashboard.vue';
defineProps<{
    placements: { catalog: boolean; primary: number; secondary: number };
}>();
</script>

<template>
    <TeacherDashboard :placements="placements" />
</template>
