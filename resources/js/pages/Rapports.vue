<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Rapports', href: '/rapports' }];
const props = defineProps<{
    sales: any[];
    filters: { from: string; to: string };
}>();

const from = (props.filters?.from) || '';
const to = (props.filters?.to) || '';

function apply() {
    const f = (document.getElementById('from') as HTMLInputElement).value;
    const t = (document.getElementById('to') as HTMLInputElement).value;
    router.get(route('rapports'), { from: f, to: t }, { preserveState: true });
}
function exportCsv() {
    const f = (document.getElementById('from') as HTMLInputElement).value;
    const t = (document.getElementById('to') as HTMLInputElement).value;
    window.location.href = route('rapports.export') + '?from=' + f + '&to=' + t;
}
</script>

<template>
    <Head title="Rapports" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-8">
            <div class="bg-card border border-border rounded-lg p-5 flex items-end gap-3">
                <div>
                    <label class="text-xs text-muted-foreground">Du</label>
                    <input id="from" type="date" :value="from" class="border border-border rounded-md px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-xs text-muted-foreground">Au</label>
                    <input id="to" type="date" :value="to" class="border border-border rounded-md px-3 py-2 text-sm" />
                </div>
                <button @click="apply" class="bg-primary text-white rounded-md px-4 py-2 text-sm">Filtrer</button>
                <button @click="exportCsv" class="border border-border rounded-md px-4 py-2 text-sm">Exporter CSV</button>
            </div>
            <div class="bg-card border border-border rounded-lg overflow-hidden">
                <div class="grid grid-cols-6 bg-muted/60 text-xs font-medium text-muted-foreground px-4 py-2.5">
                    <span>N° vente</span><span>Date</span><span>Vendeur</span><span>Client</span><span>Total</span><span>Statut</span>
                </div>
                <div v-for="s in props.sales" :key="s.number" class="grid grid-cols-6 px-4 py-3 border-t border-border text-sm">
                    <span class="font-bold">{{ s.number }}</span>
                    <span>{{ s.date }}</span>
                    <span>{{ s.vendeur }}</span>
                    <span>{{ s.client }}</span>
                    <span>{{ s.total }} GNF</span>
                    <span>{{ s.status }}</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

