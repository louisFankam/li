<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftRight, PackageCheck, PackageMinus, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Transferts', href: '/transfers' }];

type TransferItem = {
    id: number;
    product: string;
    product_id: number;
    requested: number;
    shipped: number;
    received: number;
};

type Transfer = {
    id: number;
    reference: string;
    from: string;
    from_id: number;
    to: string;
    to_id: number;
    status: string;
    items: TransferItem[];
    date: string;
};

defineProps<{
    transfers: Transfer[];
    pharmacies: { id: number; name: string }[];
    products: { id: number; name: string }[];
}>();

const statusLabels: Record<string, string> = {
    valide: 'À expédier',
    partiellement_expedie: 'Partiellement expédié',
    expedie: 'Expédié',
    partiellement_recu: 'Partiellement reçu',
    recu: 'Reçu',
    annule: 'Annulé',
};

const statusClasses: Record<string, string> = {
    valide: 'bg-muted text-muted-foreground',
    partiellement_expedie: 'bg-warning-soft text-gold',
    expedie: 'bg-info-soft text-info',
    partiellement_recu: 'bg-warning-soft text-gold',
    recu: 'bg-success-soft text-accent',
    annule: 'bg-danger-soft text-danger',
};

const form = useForm({
    from_pharmacy_id: null as number | null,
    to_pharmacy_id: null as number | null,
    items: [{ product_id: null as number | null, quantity: 1 }],
    notes: '',
});

function addItem() {
    form.items.push({ product_id: null, quantity: 1 });
}

function removeItem(index: number) {
    if (form.items.length === 1) return;
    form.items.splice(index, 1);
}

function submit() {
    form.post(route('transfers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.items = [{ product_id: null, quantity: 1 }];
        },
    });
}

// --- Panneaux expédition / réception -----------------------------------
const panel = ref<{ id: number; mode: 'ship' | 'receive' } | null>(null);
const lines = ref<{ id: number; name: string; max: number; quantity: number }[]>([]);

function openPanel(transfer: Transfer, mode: 'ship' | 'receive') {
    panel.value = { id: transfer.id, mode };
    lines.value = transfer.items.map((it) => {
        const max = mode === 'ship' ? it.requested - it.shipped : it.shipped - it.received;
        return { id: it.id, name: it.product, max, quantity: max };
    });
}

function closePanel() {
    panel.value = null;
    lines.value = [];
}

function submitPanel(transfer: Transfer, mode: 'ship' | 'receive') {
    const payload = lines.value.map((l) => ({
        id: l.id,
        [mode === 'ship' ? 'quantity_shipped' : 'quantity_received']: l.quantity,
    }));

    const url = mode === 'ship' ? route('transfers.ship', transfer.id) : route('transfers.receive', transfer.id);

    router.post(url, { items: payload }, { preserveScroll: true, onSuccess: () => closePanel() });
}

function cancel(transfer: Transfer) {
    if (!window.confirm('Annuler ce transfert ?')) return;
    router.post(route('transfers.cancel', transfer.id), {}, { preserveScroll: true });
}

function canShip(transfer: Transfer): boolean {
    return ['valide', 'partiellement_expedie'].includes(transfer.status);
}

function canReceive(transfer: Transfer): boolean {
    return ['expedie', 'partiellement_expedie', 'partiellement_recu'].includes(transfer.status);
}

function canCancel(transfer: Transfer): boolean {
    return !['recu', 'annule'].includes(transfer.status);
}
</script>

<template>
    <Head title="Transferts" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 lg:p-8">
            <div class="rounded-lg border border-border bg-card p-5">
                <div class="mb-3 flex items-center gap-2 font-headings text-lg font-bold">
                    <ArrowLeftRight class="h-5 w-5 text-primary" /> Nouveau transfert
                </div>
                <form class="flex flex-col gap-3" @submit.prevent="submit">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <label for="from" class="text-xs font-medium text-muted-foreground">Pharmacie expéditrice</label>
                            <select id="from" v-model="form.from_pharmacy_id" class="rounded-md border border-border bg-input px-3 py-2 text-sm">
                                <option :value="null">Sélectionner…</option>
                                <option v-for="p in pharmacies" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="to" class="text-xs font-medium text-muted-foreground">Pharmacie destinataire</label>
                            <select id="to" v-model="form.to_pharmacy_id" class="rounded-md border border-border bg-input px-3 py-2 text-sm">
                                <option :value="null">Sélectionner…</option>
                                <option v-for="p in pharmacies" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <div v-for="(it, index) in form.items" :key="index" class="grid grid-cols-1 gap-2 md:grid-cols-[2fr_120px_40px]">
                            <select v-model="it.product_id" class="rounded-md border border-border bg-input px-3 py-2 text-sm" aria-label="Produit">
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
                            <button
                                type="button"
                                class="flex items-center justify-center rounded-md border border-border text-muted-foreground hover:text-danger disabled:opacity-40"
                                :disabled="form.items.length === 1"
                                aria-label="Retirer la ligne"
                                @click="removeItem(index)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm" @click="addItem">
                            <Plus class="h-4 w-4" /> Ajouter une ligne
                        </button>
                        <button
                            type="submit"
                            class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            Créer le transfert
                        </button>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-lg border border-border bg-card">
                <div class="p-5 font-headings text-lg font-bold">Transferts</div>

                <div v-if="transfers.length === 0" class="px-5 py-8 text-center text-sm text-muted-foreground">Aucun transfert enregistré.</div>

                <div v-for="t in transfers" :key="t.id" class="border-t border-border p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-bold">{{ t.reference }}</span>
                                <span class="rounded-sm px-2 py-0.5 text-xs font-medium" :class="statusClasses[t.status]">
                                    {{ statusLabels[t.status] ?? t.status }}
                                </span>
                            </div>
                            <div class="text-xs text-muted-foreground">{{ t.from }} → {{ t.to }} · {{ t.date }}</div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-if="canShip(t)"
                                type="button"
                                class="flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-white"
                                @click="openPanel(t, 'ship')"
                            >
                                <PackageMinus class="h-4 w-4" /> Expédier
                            </button>
                            <button
                                v-if="canReceive(t)"
                                type="button"
                                class="flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-xs font-medium text-white"
                                @click="openPanel(t, 'receive')"
                            >
                                <PackageCheck class="h-4 w-4" /> Réceptionner
                            </button>
                            <button
                                v-if="canCancel(t)"
                                type="button"
                                class="rounded-md border border-danger px-3 py-1.5 text-xs font-medium text-danger"
                                @click="cancel(t)"
                            >
                                Annuler
                            </button>
                        </div>
                    </div>

                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        <span v-for="it in t.items" :key="it.id">
                            {{ it.product }} — demandé {{ it.requested }}, expédié {{ it.shipped }}, reçu {{ it.received }}
                        </span>
                    </div>

                    <div v-if="panel?.id === t.id" class="mt-3 rounded-md border border-border bg-muted/30 p-3">
                        <div class="mb-2 text-sm font-medium">
                            {{ panel.mode === 'ship' ? 'Quantités à expédier' : 'Quantités reçues' }}
                        </div>
                        <div v-for="line in lines" :key="line.id" class="flex items-center justify-between gap-3 py-1">
                            <span class="min-w-0 flex-1 truncate text-sm">{{ line.name }}</span>
                            <span class="text-xs text-muted-foreground">max {{ line.max }}</span>
                            <input
                                v-model.number="line.quantity"
                                type="number"
                                min="0"
                                :max="line.max"
                                class="w-24 rounded-md border border-border bg-input px-2 py-1 text-right text-sm"
                                aria-label="Quantité"
                            />
                        </div>
                        <div class="mt-3 flex justify-end gap-2">
                            <button type="button" class="rounded-md border border-border px-3 py-1.5 text-sm" @click="closePanel">Annuler</button>
                            <button
                                type="button"
                                class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white"
                                @click="submitPanel(t, panel.mode)"
                            >
                                Valider
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
