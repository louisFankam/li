<script setup lang="ts">
import { Banknote, Building, Clock, Package, ShieldCheck, TriangleAlert, Wallet } from 'lucide-vue-next';

const props = withDefaults(defineProps<{
    label?: string;
    value?: string;
    delta?: string;
    positive?: boolean;
    icon?: string;
    spark?: number[];
}>(), {
    label: "Chiffre d'affaires",
    value: '0',
    delta: '',
    positive: true,
    icon: 'banknote',
    spark: () => [35, 55, 42, 68, 60, 82, 95],
});

const icons: Record<string, any> = { banknote: Banknote, building: Building, clock: Clock, package: Package, 'shield-check': ShieldCheck, 'triangle-alert': TriangleAlert, wallet: Wallet };
const iconComponent = icons[props.icon] || Banknote;
const max = Math.max(...props.spark);
</script>

<template>
    <div class="bg-card border border-border rounded-lg p-5 flex-1">
        <div class="flex items-start justify-between">
            <div class="w-10 h-10 rounded-md bg-secondary text-secondary-foreground flex items-center justify-center">
                <component :is="iconComponent" class="w-5 h-5" />
            </div>
            <span :class="['text-xs font-medium px-2 py-1 rounded-sm', positive ? 'bg-success-soft text-accent' : 'bg-danger-soft text-danger']">{{ delta }}</span>
        </div>
        <div class="text-sm text-muted-foreground mt-4 font-medium">{{ label }}</div>
        <div class="font-headings font-bold text-2xl text-foreground mt-1">{{ value }}</div>
        <div class="flex items-end gap-1.5 h-10 mt-3">
            <div v-for="(v, i) in spark" :key="i" :class="['flex-1 rounded-sm', i === spark.length - 1 ? 'bg-primary' : positive ? 'bg-secondary' : 'bg-muted']" :style="{ height: (v / max) * 100 + '%' }"></div>
        </div>
    </div>
</template>
PHP