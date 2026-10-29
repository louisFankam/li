<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Transferts', href: '/transfers' }];
const props = defineProps<{
    transfers: { id: number; reference: string; from: string; to: string; status: string; items: { id: number; product: string; product_id: number; requested: number; shipped: number; received: number }[]; date: string }[];
    pharmacies: { id: number; name: string }[];
    products: { id: number; name: string }[];
}>();

const form = useForm({
    from_pharmacy_id: null as number | null,
    to_pharmacy_id: null as number | null,
    items: [{ product_id: null as number | null, quantity: 1 }],
    notes: '',
});
function addItem() { form.items.push({ product_id: null, quantity: 1 }); }
function removeItem(i: number) { form.items.splice(i, 1); }
const submit = () => form.post(route('transfers.store'), { onSuccess: () => form.reset() });

function ship(id: number) { router.post(route('transfers.ship', id)); }
function cancel(id: number) { if (confirm('Annuler ?')) router.post(route('transfers.cancel', id)); }
function receive(t: any) {
    const items = t.items.map((i: any) => ({ id: i.id, quantity_received: i.shipped }));
    router.post(route('transfers.receive', t.id), { items });
}
</script>

<template>
    <Head title="Transferts" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-8 flex flex-col gap-4">
            <div class="bg-card border border-border rounded-lg p-5">
                <div class="font-headings font-bold text-lg mb-3">Nouveau transfert</div>
                <form @submit.prevent="submit" class="flex flex-col gap-3">
                    <div class="grid grid-cols-2 gap-2">
                        <select v-model="form.from_pharmacy_id" class="border border-border rounded-md px-3 py-2 text-sm">
                            <option :value="null">Expéditeur</option>
                            <option v-for="p in props.pharmacies" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <select v-model="form.to_pharmacy_id" class="border border-border rounded-md px-3 py-2 text-sm">
                            <option :value="null">Destinataire</option>
                            <option v-for="p in props.pharmacies" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div v-for="(it, i) in form.items" :key="i" class="grid grid-cols-3 gap-2">
                        <select v-model="it.product_id" class="border border-border rounded-md px-3 py-2 text-sm col-span-2">
                            <option :value="null">Produit</option>
                            <option v-for="p in props.products" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <input v-model.number="it.quantity" type="number" min="1" class="border border-border rounded-md px-3 py-2 text-sm" />
                        <button type="button" @click="removeItem(i)" class="text-xs border border-danger text-danger rounded-md px-2">Retirer</button>
                    </div>
                    <button type="button" @click="addItem" class="text-sm border border-border rounded-md px-3 py-2 w-fit">+ Ligne</button>
                    <button type="submit" class="bg-primary text-white rounded-md px-4 py-2 text-sm w-fit">Créer</button>
                </form>
            </div>

            <div class="bg-card border border-border rounded-lg overflow-hidden">
                <div v-for="t in props.transfers" :key="t.id" class="p-4 border-t border-border first:border-0">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-bold">{{ t.reference }}</span>
                            <span class="text-xs text-muted-foreground ml-2">{{ t.from }} → {{ t.to }} · {{ t.date }}</span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-xs font-medium px-2 py-1 rounded-sm bg-muted">{{ t.status }}</span>
                            <button v-if="t.status === 'valide'" @click="ship(t.id)" class="text-xs bg-primary text-white rounded-sm px-2.5 py-1.5">Expédier</button>
                            <button v-if="t.status === 'expedie' || t.status === 'partiellement_recu'" @click="receive(t)" class="text-xs bg-accent text-white rounded-sm px-2.5 py-1.5">Réceptionner</button>
                            <button v-if="t.status !== 'recu' && t.status !== 'annule'" @click="cancel(t.id)" class="text-xs border border-danger text-danger rounded-sm px-2.5 py-1.5">Annuler</button>
                        </div>
                    </div>
                    <div class="mt-2 text-sm text-muted-foreground">
                        <div v-for="i in t.items" :key="i.id">{{ i.product }} — demandé {{ i.requested }}, expédié {{ i.shipped }}, reçu {{ i.received }}</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
