<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Plus, Trash2, Truck } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Achats & Fournisseurs', href: '/achats' }];

type PurchaseItem = {
    id: number;
    product: string;
    product_id: number;
    ordered: number;
    received: number;
    unit_price: number;
};

type Purchase = {
    id: number;
    supplier: string;
    status: string;
    invoice_number: string | null;
    payment_status: string;
    total: string;
    total_raw: number;
    items: PurchaseItem[];
    ordered_at: string | null;
    received_at: string | null;
};

const props = defineProps<{
    purchases: Purchase[];
    suppliers: { id: number; name: string }[];
    products: { id: number; name: string; purchase_price: number }[];
}>();

const statusLabels: Record<string, string> = {
    brouillon: 'Brouillon',
    envoyee: 'Envoyée',
    partiellement_recue: 'Partiellement reçue',
    recue: 'Reçue',
    annulee: 'Annulée',
};

const statusClasses: Record<string, string> = {
    brouillon: 'bg-muted text-muted-foreground',
    envoyee: 'bg-info-soft text-info',
    partiellement_recue: 'bg-warning-soft text-gold',
    recue: 'bg-success-soft text-accent',
    annulee: 'bg-danger-soft text-danger',
};

const money = (value: number) => value.toLocaleString('fr-FR') + ' GNF';

const form = useForm({
    supplier_id: null as number | null,
    invoice_number: '',
    items: [{ product_id: null as number | null, quantity: 1, unit_price: 0 }],
});

const formTotal = computed(() =>
    form.items.reduce((s, it) => s + (Number(it.quantity) || 0) * (Number(it.unit_price) || 0), 0),
);

function addItem() {
    form.items.push({ product_id: null, quantity: 1, unit_price: 0 });
}

function removeItem(index: number) {
    if (form.items.length === 1) return;
    form.items.splice(index, 1);
}

function onProductChange(index: number) {
    const line = form.items[index];
    const product = props.products.find((p) => p.id === line.product_id);
    if (product && !line.unit_price) {
        line.unit_price = product.purchase_price;
    }
}

function submit() {
    form.post(route('achats.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.items = [{ product_id: null, quantity: 1, unit_price: 0 }];
        },
    });
}

// --- Réception ---------------------------------------------------------
const receivingId = ref<number | null>(null);
const receipt = ref<{ id: number; name: string; ordered: number; quantity_received: number }[]>([]);

function openReceive(purchase: Purchase) {
    receivingId.value = purchase.id;
    receipt.value = purchase.items.map((it) => ({
        id: it.id,
        name: it.product,
        ordered: it.ordered,
        quantity_received: it.received,
    }));
}

function closeReceive() {
    receivingId.value = null;
    receipt.value = [];
}

function submitReceive(purchase: Purchase) {
    router.post(
        route('achats.receive', purchase.id),
        { items: receipt.value.map((l) => ({ id: l.id, quantity_received: l.quantity_received })) },
        {
            preserveScroll: true,
            onSuccess: () => closeReceive(),
        },
    );
}

function receiveAll(purchase: Purchase) {
    receipt.value = purchase.items.map((it) => ({
        id: it.id,
        name: it.product,
        ordered: it.ordered,
        quantity_received: it.ordered,
    }));
}

function markSent(purchase: Purchase) {
    router.post(route('achats.status', purchase.id), { status: 'envoyee' }, { preserveScroll: true });
}

function cancel(purchase: Purchase) {
    if (!window.confirm('Annuler cette commande ?')) return;
    router.post(route('achats.status', purchase.id), { status: 'annulee' }, { preserveScroll: true });
}

function canReceive(purchase: Purchase): boolean {
    return ['envoyee', 'partiellement_recue'].includes(purchase.status);
}
</script>

<template>
    <Head title="Achats" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 lg:p-8">
            <div class="rounded-lg border border-border bg-card p-5">
                <div class="mb-3 font-headings text-lg font-bold">Nouvelle commande</div>
                <form class="flex flex-col gap-3" @submit.prevent="submit">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <label for="supplier" class="text-xs font-medium text-muted-foreground">Fournisseur</label>
                            <select
                                id="supplier"
                                v-model="form.supplier_id"
                                class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                            >
                                <option :value="null">Sélectionner…</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="invoice" class="text-xs font-medium text-muted-foreground">N° de facture (optionnel)</label>
                            <input
                                id="invoice"
                                v-model="form.invoice_number"
                                type="text"
                                maxlength="100"
                                class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                            />
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <div class="hidden grid-cols-[2fr_100px_140px_40px] gap-2 text-xs font-medium text-muted-foreground md:grid">
                            <span>Produit</span><span>Quantité</span><span>Prix d'achat</span><span></span>
                        </div>
                        <div
                            v-for="(it, i) in form.items"
                            :key="i"
                            class="grid grid-cols-1 gap-2 md:grid-cols-[2fr_100px_140px_40px]"
                        >
                            <select
                                v-model="it.product_id"
                                class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                                aria-label="Produit"
                                @change="onProductChange(i)"
                            >
                                <option :value="null">Produit…</option>
                                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                            <input
                                v-model.number="it.quantity"
                                type="number"
                                min="1"
                                class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                                aria-label="Quantité"
                            />
                            <input
                                v-model.number="it.unit_price"
                                type="number"
                                min="0"
                                class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                                aria-label="Prix d'achat"
                            />
                            <button
                                type="button"
                                class="flex items-center justify-center rounded-md border border-border text-muted-foreground hover:text-danger disabled:opacity-40"
                                :disabled="form.items.length === 1"
                                aria-label="Retirer la ligne"
                                @click="removeItem(i)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm"
                            @click="addItem"
                        >
                            <Plus class="h-4 w-4" /> Ajouter une ligne
                        </button>
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-muted-foreground">Total : <strong class="text-foreground">{{ money(formTotal) }}</strong></span>
                            <button
                                type="submit"
                                class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                                :disabled="form.processing"
                            >
                                Créer la commande
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-lg border border-border bg-card">
                <div class="p-5 font-headings text-lg font-bold">Commandes</div>

                <div v-if="purchases.length === 0" class="px-5 py-8 text-center text-sm text-muted-foreground">
                    Aucune commande enregistrée.
                </div>

                <div v-for="p in purchases" :key="p.id" class="border-t border-border p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{ p.supplier }}</span>
                                <span class="rounded-sm px-2 py-0.5 text-xs font-medium" :class="statusClasses[p.status]">
                                    {{ statusLabels[p.status] ?? p.status }}
                                </span>
                                <span v-if="p.invoice_number" class="text-xs text-muted-foreground">Facture {{ p.invoice_number }}</span>
                            </div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ p.ordered_at ?? 'non envoyée' }} → {{ p.received_at ?? 'non reçue' }} · {{ p.total }}
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                v-if="p.status === 'brouillon'"
                                type="button"
                                class="flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-sm font-medium"
                                @click="markSent(p)"
                            >
                                <Truck class="h-4 w-4" /> Marquer envoyée
                            </button>
                            <button
                                v-if="canReceive(p)"
                                type="button"
                                class="flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white"
                                @click="openReceive(p)"
                            >
                                <Check class="h-4 w-4" /> Réceptionner
                            </button>
                            <button
                                v-if="['brouillon', 'envoyee'].includes(p.status)"
                                type="button"
                                class="rounded-md border border-border px-3 py-1.5 text-sm text-muted-foreground hover:text-danger"
                                @click="cancel(p)"
                            >
                                Annuler
                            </button>
                        </div>
                    </div>

                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        <span v-for="it in p.items" :key="it.id">
                            {{ it.product }} : {{ it.received }}/{{ it.ordered }}
                        </span>
                    </div>

                    <div v-if="receivingId === p.id" class="mt-3 rounded-md border border-border bg-muted/30 p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-medium">Réception — {{ p.supplier }}</span>
                            <button type="button" class="text-xs font-medium text-primary hover:underline" @click="receiveAll(p)">
                                Tout recevoir
                            </button>
                        </div>
                        <div v-for="line in receipt" :key="line.id" class="flex items-center justify-between gap-3 py-1">
                            <span class="min-w-0 flex-1 truncate text-sm">{{ line.name }}</span>
                            <span class="text-xs text-muted-foreground">/ {{ line.ordered }}</span>
                            <input
                                v-model.number="line.quantity_received"
                                type="number"
                                min="0"
                                :max="line.ordered"
                                class="w-24 rounded-md border border-border bg-input px-2 py-1 text-right text-sm"
                                aria-label="Quantité reçue"
                            />
                        </div>
                        <div class="mt-3 flex justify-end gap-2">
                            <button type="button" class="rounded-md border border-border px-3 py-1.5 text-sm" @click="closeReceive">
                                Annuler
                            </button>
                            <button
                                type="button"
                                class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white"
                                @click="submitReceive(p)"
                            >
                                Valider la réception
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
