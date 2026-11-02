<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        label?: string;
        value?: string;
        delta?: string;
        positive?: boolean;
        icon?: string;
        spark?: number[];
    }>(),
    {
        label: "Chiffre d'affaires",
        value: '0',
        delta: '',
        positive: true,
        icon: 'banknote',
        spark: () => [35, 55, 42, 68, 60, 82, 95],
    },
);

const max = computed(() => Math.max(...props.spark, 1));
</script>

<template>
    <div class="flex-1 rounded-lg border border-border bg-card p-5">
        <div class="flex items-start justify-between">
            <div class="flex h-10 w-10 items-center justify-center rounded-md bg-secondary text-secondary-foreground">
                <Icon :name="icon" :size="20" />
            </div>
            <span
                v-if="delta"
                :class="['rounded-sm px-2 py-1 text-xs font-medium', positive ? 'bg-success-soft text-accent' : 'bg-danger-soft text-danger']"
            >
                {{ delta }}
            </span>
        </div>
        <div class="mt-4 text-sm font-medium text-muted-foreground">{{ label }}</div>
        <div class="mt-1 font-headings text-2xl font-bold text-foreground">{{ value }}</div>
        <div class="mt-3 flex h-10 items-end gap-1.5">
            <div
                v-for="(v, i) in spark"
                :key="i"
                :class="['flex-1 rounded-sm', i === spark.length - 1 ? 'bg-primary' : positive ? 'bg-secondary' : 'bg-muted']"
                :style="{ height: (v / max) * 100 + '%' }"
            />
        </div>
    </div>
</template>
