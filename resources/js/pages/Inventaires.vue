<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inventaires', href: '/inventaires' }];
const props = defineProps<{ products: { id: number; name: string; theoretical: number }[] }>();

const form = useForm({ counts: props.products.map((p) => ({ product_id: p.id, counted: p.theoretical })) });
const adj = useForm({ product_id: null as number | null, type: 'perte', quantity: 0, reason: '' });

function submit() {
    form.post(route('inventaires.store'), { onSuccess: () => {} });
}
function adjust() {
    adj.post(route('inventaires.adjust'), { onSuccess: () => adj.reset() });
}
</script>

<template>
    <Head title="Inventaires" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-8">
            <div class="rounded-lg border border-border bg-card p-5">
                <div class="mb-3 font-headings text-lg font-bold">Comptage physique</div>
                <form @submit.prevent="submit">
                    <div v-for="(p, i) in props.products" :key="p.id" class="grid grid-cols-3 items-center gap-2 border-b border-border py-2">
                        <span class="text-sm">{{ p.name }}</span>
                        <span class="text-xs text-muted-foreground">Théorique : {{ p.theoretical }}</span>
                        <input
                            v-model.number="form.counts[i].counted"
                            type="number"
                            min="0"
                            class="rounded-md border border-border px-3 py-2 text-sm"
                        />
                    </div>
                    <button type="submit" class="mt-4 rounded-md bg-primary px-4 py-2 text-sm text-white">Valider l'inventaire</button>
                </form>
            </div>

            <div class="rounded-lg border border-border bg-card p-5">
                <div class="mb-3 font-headings text-lg font-bold">Ajustement de stock (perte/casse/correction)</div>
                <form @submit.prevent="adjust" class="grid grid-cols-4 items-end gap-2">
                    <select v-model="adj.product_id" class="rounded-md border border-border px-3 py-2 text-sm">
                        <option :value="null">Produit</option>
                        <option v-for="p in props.products" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <select v-model="adj.type" class="rounded-md border border-border px-3 py-2 text-sm">
                        <option value="perte">Perte</option>
                        <option value="casse">Casse</option>
                        <option value="correction">Correction</option>
                    </select>
                    <input
                        v-model.number="adj.quantity"
                        type="number"
                        placeholder="Quantité (+/-)"
                        class="rounded-md border border-border px-3 py-2 text-sm"
                    />
                    <input v-model="adj.reason" placeholder="Motif" class="rounded-md border border-border px-3 py-2 text-sm" />
                    <button type="submit" class="col-span-4 rounded-md bg-primary px-4 py-2 text-sm text-white">Enregistrer</button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
