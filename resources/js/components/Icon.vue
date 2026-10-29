<script setup lang="ts">
import { cn } from '@/lib/utils';
import * as icons from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    /** Nom Lucide en kebab-case, comme dans la maquette : "triangle-alert". */
    name: string;
    class?: string;
    size?: number | string;
    color?: string;
    strokeWidth?: number | string;
}

const props = withDefaults(defineProps<Props>(), {
    class: '',
    size: 18,
    strokeWidth: 2,
});

const className = computed(() => cn('shrink-0', props.class));

const icon = computed(() => {
    const pascal = props.name
        .split('-')
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join('');

    return (icons as Record<string, unknown>)[pascal] ?? icons.Circle;
});
</script>

<template>
    <component :is="icon" :class="className" :size="Number(size)" :stroke-width="strokeWidth" :color="color" />
</template>
