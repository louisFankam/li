<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { PackagePlus, Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Produits & Stocks', href: '/stocks' }];

type ProductRow = {
    id: number;
    name: string;
    dci: string | null;
    labo: string | null;
    category: string | null;
    stock: number;
    seuil: number;
    prix: string;
    marge: string;
    statut: string;
    code: string;
    barcode: string | null;
    category_id: number | null;
    sale_price: number;
    purchase_price: number;
    alert_threshold: number;
    is_active: boolean;
};

const props = defineProps<{
    pharmacy: { id: number; name: string; city: string };
    products: ProductRow[];
    categories: { id: number; name: string }[];
    stats: { value: string; ruptures: string; alerts: string; references: string };
}>();

const form = useForm({
    code: '',
    name: '',
    generic_name: '',
    laboratory: '',
    category_id: null as number | null,
    sale_price: 0,
    purchase_price: 0,
    alert_threshold: 0,
});

const editingId = ref<number | null>(null);

const search = ref('');
const statusFilter = ref('all');

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.products.filter((p) => {
        if (statusFilter.value !== 'all' && p.statut !== statusFilter.value) return false;
        if (!q) return true;
        return (
            p.name.toLowerCase().includes(q) ||
            (p.dci ?? '').toLowerCase().includes(q) ||
            p.code.toLowerCase().includes(q) ||
            (p.labo ?? '').toLowerCase().includes(q)
        );
    });
});

const statusOptions = [
    { value: 'all', label: 'Tous statuts' },
    { value: 'OK', label: 'OK' },
    { value: 'Faible', label: 'Faible' },
    { value: 'Rupture', label: 'Rupture' },
];

function resetForm() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function edit(product: ProductRow) {
    editingId.value = product.id;
    form.code = product.code;
    form.name = product.name;
    form.generic_name = product.dci ?? '';
    form.laboratory = product.labo ?? '';
    form.category_id = product.category_id;
    form.sale_price = product.sale_price;
    form.purchase_price = product.purchase_price;
    form.alert_threshold = product.alert_threshold;
    form.clearErrors();
}

function submit() {
    if (editingId.value !== null) {
        form.put(route('stocks.update', editingId.value), { preserveScroll: true, onSuccess: () => resetForm() });
        return;
    }

    form.post(route('stocks.store'), { preserveScroll: true, onSuccess: () => resetForm() });
}

function destroy(id: number) {
    if (!window.confirm('Désactiver ce produit ?')) return;
    router.delete(route('stocks.destroy', id));
}
</script>

<template>
    <Head title="Produits & Stocks" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 lg:p-8">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="bg-card border border-border rounded-lg p-5">
                    <div class="text-xs text-muted-foreground font-medium">Valeur du stock</div>
                    <div class="font-headings font-bold text-xl">{{ stats.value }}</div>
                </div>
                <div class="bg-card border border-border rounded-lg p-5">
                    <div class="text-xs text-muted-foreground font-medium">Ruptures (vente bloquée)</div>
                    <div class="font-headings font-bold text-xl">{{ stats.ruptures }}</div>
                </div>
                <div class="bg-card border border-border rounded-lg p-5">
                    <div class="text-xs text-muted-foreground font-medium">Alertes seuil atteint</div>
                    <div class="font-headings font-bold text-xl">{{ stats.alerts }}</div>
                </div>
                <div class="bg-card border border-border rounded-lg p-5">
                    <div class="text-xs text-muted-foreground font-medium">Références actives</div>
                    <div class="font-headings font-bold text-xl">{{ stats.references }}</div>
                </div>
            </div>

            <div class="bg-card border border-border rounded-lg p-5">
                <div class="font-headings font-bold text-lg mb-3">{{ editingId ? 'Modifier le produit' : 'Nouveau produit' }}</div>
                <form @submit.prevent="submit" class="grid gap-3 md:grid-cols-4">
                    <input v-model="form.code" placeholder="Code" class="border border-border rounded-md px-3 py-2 text-sm" />
                    <input v-model="form.name" placeholder="Nom" class="border border-border rounded-md px-3 py-2 text-sm" />
                    <input v-model="form.generic_name" placeholder="DCI" class="border border-border rounded-md px-3 py-2 text-sm" />
                    <input v-model="form.laboratory" placeholder="Laboratoire" class="border border-border rounded-md px-3 py-2 text-sm" />
                    <select v-model="form.category_id" class="border border-border rounded-md px-3 py-2 text-sm">
                        <option :value="null">Catégorie</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <input v-model.number="form.sale_price" type="number" placeholder="Prix vente" class="border border-border rounded-md px-3 py-2 text-sm" />
                    <input v-model.number="form.purchase_price" type="number" placeholder="Prix achat" class="border border-border rounded-md px-3 py-2 text-sm" />
                    <input v-model.number="form.alert_threshold" type="number" placeholder="Seuil" class="border border-border rounded-md px-3 py-2 text-sm" />
                    <button type="submit" class="bg-primary text-white rounded-md py-2 text-sm font-medium disabled:opacity-60">
                        {{ editingId ? 'Enregistrer' : 'Créer le produit' }}
                    </button>
                </form>
            </div>

            <div class="bg-card border border-border rounded-lg">
                <div class="p-5 flex flex-wrap items-center gap-3 border-b border-border">
                    <div class="flex flex-1 items-center gap-3 border border-border rounded-md px-4 py-2.5 bg-input max-w-[420px]">
                        <Search class="h-4 w-4 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Rechercher produit, DCI, code…"
                            class="w-full bg-transparent text-sm outline-none"
                            aria-label="Rechercher un produit"
                        />
                        <button v-if="search" type="button" class="text-muted-foreground hover:text-foreground" aria-label="Effacer" @click="search = ''">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            v-for="o in statusOptions"
                            :key="o.value"
                            type="button"
                            class="text-sm font-medium px-3 py-2 rounded-md border"
                            :class="statusFilter === o.value ? 'bg-dark text-white border-dark' : 'border-border text-muted-foreground'"
                            @click="statusFilter = o.value"
                        >
                            {{ o.label }}
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <div class="min-w-[900px]">
                        <div class="grid grid-cols-[2fr_1fr_1fr_0.7fr_0.7fr_0.9fr] text-xs font-medium text-muted-foreground px-5 py-3 bg-muted/50">
                            <span>Produit / DCI</span><span>Laboratoire</span><span>Stock</span><span>Prix vente</span><span>Marge</span><span class="text-right">Statut</span>
                        </div>
                        <div
                            v-for="r in filtered"
                            :key="r.id"
                            class="grid grid-cols-[2fr_1fr_1fr_0.7fr_0.7fr_0.9fr] px-5 py-3.5 border-t border-border items-center text-sm"
                        >
                            <div>
                                <div class="font-medium">{{ r.name }}</div>
                                <div class="text-xs text-muted-foreground">DCI : {{ r.dci ?? '—' }}</div>
                            </div>
                            <div class="text-muted-foreground text-[13px]">{{ r.labo ?? '—' }}</div>
                            <div>
                                <div class="font-bold">{{ r.stock }}</div>
                                <div class="h-1.5 w-28 bg-muted rounded-full mt-1.5 overflow-hidden">
                                    <div
                                        :class="['h-full rounded-full', r.statut === 'Rupture' ? 'bg-danger' : r.statut === 'Faible' ? 'bg-warning' : 'bg-primary']"
                                        :style="{ width: Math.min(100, (r.stock / Math.max(1, r.seuil * 2.5)) * 100) + '%' }"
                                    ></div>
                                </div>
                            </div>
                            <div class="font-medium">{{ r.prix }} GNF</div>
                            <div class="text-muted-foreground">{{ r.marge }}</div>
                            <div class="text-right">
                                <span
                                    :class="[
                                        'text-xs font-medium px-2 py-1 rounded-sm',
                                        r.statut === 'OK' ? 'bg-success-soft text-accent' : r.statut === 'Rupture' ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-gold',
                                    ]"
                                >{{ r.statut }}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between px-5 py-3.5 border-t border-border text-sm text-muted-foreground">
                            <span>Affichage {{ filtered.length }} produit(s) · Données isolées par pharmacie</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="bg-card border border-border rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div class="font-headings font-bold text-lg">Achats & fournisseurs</div>
                        <Link href="/achats" class="text-sm font-medium bg-dark text-white px-4 py-2 rounded-md">+ Bon de commande</Link>
                    </div>
                    <div class="text-sm text-muted-foreground mt-2">Les réceptions sont enregistrées pharmacie par pharmacie.</div>
                </div>
                <div class="bg-card border border-border rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div class="font-headings font-bold text-lg">Transferts inter-pharmacies</div>
                        <span class="text-muted-foreground">↔</span>
                    </div>
                    <div class="text-sm text-muted-foreground mt-2">Expédition et réception partielles supportées.</div>
                    <Link href="/transfers" class="mt-4 inline-block text-sm font-medium text-accent">Voir les transferts</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
