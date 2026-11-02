<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router } from '@inertiajs/vue3';
import { Check, RotateCcw, X } from 'lucide-vue-next';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Conflits de synchronisation', href: '/sync/conflicts' }];

type SyncConflict = {
    id: number;
    sync_event_id: number;
    type: string;
    pharmacy: string | null;
    details: Record<string, unknown> | null;
    payload: Record<string, unknown> | null;
    created_at: string;
};

const props = defineProps<{ conflicts: SyncConflict[] }>();

const adjustments = ref<Record<number, string>>({});

function resolve(conflict: SyncConflict, resolution: 'applied' | 'cancelled' | 'adjusted') {
    const data: { resolution: string; payload?: Record<string, FormDataConvertible> } = { resolution };

    if (resolution === 'adjusted') {
        try {
            data.payload = JSON.parse(adjustments.value[conflict.id] || '{}') as Record<string, FormDataConvertible>;
        } catch {
            window.alert('Le payload ajusté doit être un JSON valide.');
            return;
        }
    }

    if (resolution === 'cancelled' && !window.confirm('Annuler définitivement cette opération locale ?')) {
        return;
    }

    router.post(route('sync.conflicts.resolve', conflict.id), data, { preserveScroll: true });
}
</script>

<template>
    <Head title="Conflits de synchronisation" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 lg:p-8">
            <h1 class="font-headings text-2xl font-bold">Conflits de synchronisation</h1>
            <div v-if="props.conflicts.length === 0" class="text-muted-foreground">Aucun conflit ouvert.</div>
            <div v-for="c in props.conflicts" :key="c.id" class="rounded-lg border border-border bg-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium">{{ c.type }}</span>
                    <span class="text-xs text-muted-foreground">{{ c.created_at }} · {{ c.pharmacy }}</span>
                </div>
                <pre class="mt-3 overflow-auto rounded bg-muted/60 p-3 text-xs">{{ JSON.stringify(c.details, null, 2) }}</pre>

                <details class="mt-3 text-sm">
                    <summary class="cursor-pointer font-medium">Payload local</summary>
                    <pre class="mt-2 overflow-auto rounded bg-muted/60 p-3 text-xs">{{ JSON.stringify(c.payload, null, 2) }}</pre>
                    <label :for="`adjustment-${c.id}`" class="mt-3 block text-xs font-medium">Payload ajusté (JSON)</label>
                    <textarea
                        :id="`adjustment-${c.id}`"
                        v-model="adjustments[c.id]"
                        rows="5"
                        class="mt-1 w-full rounded-md border border-border bg-input p-2 font-mono text-xs"
                    />
                </details>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded-md bg-primary px-3 py-2 text-sm font-medium text-white"
                        @click="resolve(c, 'applied')"
                    >
                        <Check class="h-4 w-4" /> Appliquer
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded-md border border-border px-3 py-2 text-sm font-medium"
                        @click="resolve(c, 'adjusted')"
                    >
                        <RotateCcw class="h-4 w-4" /> Appliquer l’ajustement
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded-md border border-danger px-3 py-2 text-sm font-medium text-danger"
                        @click="resolve(c, 'cancelled')"
                    >
                        <X class="h-4 w-4" /> Annuler l’opération
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
