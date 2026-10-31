<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Conflits de synchronisation', href: '/sync/conflicts' }];
defineProps<{ conflicts: any[] }>();
</script>

<template>
    <Head title="Conflits de synchronisation" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-8">
            <h1 class="font-headings font-bold text-2xl">Conflits de synchronisation</h1>
            <div v-if="conflicts.length === 0" class="text-muted-foreground">Aucun conflit ouvert.</div>
            <div v-for="c in conflicts" :key="c.id" class="bg-card border border-border rounded-lg p-5">
                <div class="flex items-center justify-between">
                    <span class="font-medium">{{ c.type }}</span>
                    <span class="text-xs text-muted-foreground">{{ c.created_at }} · {{ c.pharmacy }}</span>
                </div>
                <pre class="mt-3 text-xs bg-muted/60 rounded p-3 overflow-auto">{{ JSON.stringify(c.details, null, 2) }}</pre>
            </div>
        </div>
    </AppLayout>
</template>
