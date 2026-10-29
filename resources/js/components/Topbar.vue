<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Bell, Plus } from 'lucide-vue-next';

defineProps<{
    title?: string;
    subtitle?: string;
    showPharmacySwitch?: boolean;
}>();
</script>

<template>
    <div class="bg-card border-b border-border px-8 py-4 flex items-center gap-4">
        <div class="flex-1">
            <h1 class="font-headings font-bold text-2xl text-foreground leading-tight">{{ title || 'Tableau de bord' }}</h1>
            <p class="text-sm text-muted-foreground mt-0.5">{{ subtitle || '' }}</p>
        </div>

        <div v-if="showPharmacySwitch" class="flex items-center bg-input border border-border rounded-md px-1 py-1 gap-1">
            <a v-for="(p, i) in ['Toutes', 'KM5', 'Ratoma', 'Kindia']" :key="p" class="px-3 py-1.5 rounded-sm text-sm font-medium" :class="i === 0 ? 'bg-dark text-white' : 'text-muted-foreground'">{{ p }}</a>
        </div>

        <div class="flex items-center gap-2 bg-success-soft border border-primary/20 rounded-md px-3 py-2">
            <span class="w-2.5 h-2.5 rounded-full bg-primary" />
            <span class="text-sm font-medium text-accent">En ligne</span>
            <span class="text-xs text-accent/70">· Synchro 09:42</span>
        </div>

        <Link href="/vente" class="flex items-center gap-2 bg-primary text-primary-foreground text-sm font-medium rounded-md px-4 py-2.5">
            <Plus class="w-4 h-4" /> Nouvelle vente
        </Link>
        <button class="w-10 h-10 rounded-md border border-border bg-card flex items-center justify-center text-muted-foreground">
            <Bell class="w-4 h-4" />
        </button>
    </div>
</template>
