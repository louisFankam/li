<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Produits & Stocks', href: '/stocks' }];

const props = defineProps<{
    pharmacy: { id: number; name: string; city: string };
    products: { id: number; name: string; dci: string | null; labo: string; lot: string; stock: number; seuil: number; prix: string; marge: string; statut: string; code: string; category_id: number | null; sale_price: number; purchase_price: number; alert_threshold: number; is_active: boolean }[];
    categories: { id: number; name: string }[];
    stats: { value: string; ruptures: string; alerts: string; fiability: string };
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

const submit = () => form.post(route('stocks.store'), { onSuccess: () => form.reset() });

function destroy(id: number) {
    if (confirm('Désactiver ce produit ?')) router.delete(route('stocks.destroy', id));
}
</script>

<template>
    <Head title="Produits & Stocks" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-8">
            <div class="grid grid-cols-4 gap-4">
                <div class="bg-card border border-border rounded-lg p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-md bg-secondary text-secondary-foreground flex items-center justify-center"></div>
                    <div><div class="text-xs text-muted-foreground font-medium">Valeur du stock</div><div class="font-headings font-bold text-xl">{{ stats.value }}</div><div class="text-xs text-accent font-medium">+18 M entrées semaine</div></div>
                </div>
                <div class="bg-card border border-border rounded-lg p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-md bg-secondary text-secondary-foreground flex items-center justify-center"></div>
                    <div><div class="text-xs text-muted-foreground font-medium">Ruptures (vente bloquée)</div><div class="font-headings font-bold text-xl">{{ stats.ruptures }}</div><div class="text-xs text-gold font-medium">2 commandes urgentes</div></div>
                </div>
                <div class="bg-card border border-border rounded-lg p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-md bg-secondary text-secondary-foreground flex items-center justify-center">️</div>
                    <div><div class="text-xs text-muted-foreground font-medium">Alertes seuil atteint</div><div class="font-headings font-bold text-xl">{{ stats.alerts }}</div><div class="text-xs text-accent font-medium">Seuil auto par rotation</div></div>
                </div>
                <div class="bg-card border border-border rounded-lg p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-md bg-secondary text-secondary-foreground flex items-center justify-center"></div>
                    <div><div class="text-xs text-muted-foreground font-medium">Inventaire tournant</div><div class="font-headings font-bold text-xl">{{ stats.fiability }}</div><div class="text-xs text-accent font-medium">Dernier : rayon B · hier</div></div>
                </div>
            </div>

            <div class="bg-card border border-border rounded-lg p-5">
                <div class="font-headings font-bold text-lg mb-3">Nouveau produit</div>
                <form @submit.prevent="submit" class="grid grid-cols-4 gap-3">
                    <input v-model="form.code" placeholder="Code" class="border border-border rounded-md px-3 py-2 text-sm" />
                    <input v-model="form.name" placeholder="Nom" class="border border-border rounded-md px-3 py-2 text-sm" />
                    <input v-model="form.generic_name" placeholder="DCI" class="border border-border rounded-md px-3 py-2 text-sm" />
                    <input v-model="form.laboratory" placeholder="Laboratoire" class="border border-border rounded-md px-3 py-2 text-sm" />
                    <select v-model="form.category_id" class="border border-border rounded-md px-3 py-2 text-sm">
                        <option :value="null">Catégorie</option>
                        <option v-for="c in props.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <input v-model.number="form.sale_price" type="number" placeholder="Prix vente" class="border border-border rounded-md px-3 py-2 text-sm" />
                    <input v-model.number="form.purchase_price" type="number" placeholder="Prix achat" class="border border-border rounded-md px-3 py-2 text-sm" />
                    <input v-model.number="form.alert_threshold" type="number" placeholder="Seuil" class="border border-border rounded-md px-3 py-2 text-sm" />
                    <button type="submit" class="col-span-4 bg-primary text-white rounded-md py-2 text-sm font-medium">Créer le produit</button>
                </form>
            </div>

            <div class="bg-card border border-border rounded-lg">
                <div class="p-5 flex items-center gap-3 border-b border-border">
                    <div class="flex-1 flex items-center gap-3 border border-border rounded-md px-4 py-2.5 bg-input max-w-[420px]">
                        <span class="text-muted-foreground"></span>
                        <span class="text-sm text-muted-foreground">Rechercher produit, DCI, lot, fournisseur…</span>
                    </div>
                    <a v-for="(f,i) in ['Tous statuts','OK','Faible','Rupture']" :key="f" :class="['text-sm font-medium px-3 py-2 rounded-md border', i===0 ? 'bg-dark text-white border-dark' : 'border-border text-muted-foreground']">{{ f }}</a>
                    <div class="flex-1" />
                    <button class="text-sm font-medium border border-border rounded-md px-4 py-2.5 flex items-center gap-2">Exporter</button>
                    <button class="text-sm font-medium bg-primary text-white rounded-md px-4 py-2.5 flex items-center gap-2">+ Nouveau produit</button>
                </div>
                <div class="grid grid-cols-[2.2fr_1fr_1fr_0.9fr_0.7fr_0.7fr_0.9fr] text-xs font-medium text-muted-foreground px-5 py-3 bg-muted/50">
                    <span>Produit / DCI</span><span>Laboratoire · Lot</span><span>Stock KM5</span><span>Prix vente</span><span>Marge</span><span>Statut</span><span class="text-right">Action</span>
                </div>
                <div v-for="r in products" :key="r.id" class="grid grid-cols-[2.2fr_1fr_1fr_0.9fr_0.7fr_0.7fr_0.9fr] px-5 py-3.5 border-t border-border items-center text-sm">
                    <div>
                        <div class="font-medium">{{ r.name }}</div>
                        <div class="text-xs text-muted-foreground">DCI : {{ r.dci }}</div>
                    </div>
                    <div class="text-muted-foreground text-[13px]">{{ r.labo }}<br />{{ r.lot }}</div>
                    <div>
                        <div class="font-bold">{{ r.stock }} btes <span class="font-normal text-xs text-muted-foreground">/ seuil {{ r.seuil }}</span></div>
                        <div class="h-1.5 w-28 bg-muted rounded-full mt-1.5 overflow-hidden">
                            <div :class="['h-full rounded-full', r.stock === 0 ? 'bg-danger' : r.stock < r.seuil ? 'bg-warning' : 'bg-primary']" :style="{ width: Math.min(100, (r.stock / Math.max(1, r.seuil * 2.5)) * 100) + '%' }"></div>
                        </div>
                    </div>
                    <div class="font-medium">{{ r.prix }} GNF</div>
                    <div class="text-muted-foreground">{{ r.marge }}</div>
                    <div>
                        <span :class="['text-xs font-medium px-2 py-1 rounded-sm', r.statut === 'OK' ? 'bg-success-soft text-accent' : r.statut === 'Rupture' ? 'bg-danger-soft text-danger' : r.statut === 'Critique' ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-gold']">{{ r.statut }}</span>
                    </div>
                    <div class="text-right flex justify-end gap-1.5">
                        <button @click="destroy(r.id)" class="border border-danger text-danger rounded-sm px-2.5 py-1.5 text-xs font-medium">Désactiver</button>
                    </div>
                </div>
                <div class="px-5 py-3.5 border-t border-border flex items-center justify-between text-sm text-muted-foreground">
                    <span>Affichage {{ products.length }} produits · Données isolées par pharmacie</span>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2 bg-card border border-border rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div class="font-headings font-bold text-lg">Achats & fournisseurs récents</div>
                        <button class="text-sm font-medium bg-dark text-white rounded-md px-4 py-2 flex items-center gap-2">+ Bon de commande</button>
                    </div>
                    <div class="grid grid-cols-3 gap-3 mt-4">
                        <div v-for="a in [{f:'Laborex Guinée',bc:'BC-1882 · 42 lignes',montant:'86 400 000 GNF',etat:'Reçu partiel',cls:'bg-warning-soft text-gold'},{f:'Cophamed SA',bc:'BC-1881 · 18 lignes',montant:'34 200 000 GNF',etat:'En préparation',cls:'bg-info-soft text-foreground'},{f:'Ubipharm',bc:'BC-1880 · 55 lignes',montant:'112 750 000 GNF',etat:'Réceptionné',cls:'bg-success-soft text-accent'}]" :key="a.bc" class="border border-border rounded-md p-4">
                            <div class="font-medium text-sm">{{ a.f }}</div>
                            <div class="text-xs text-muted-foreground">{{ a.bc }}</div>
                            <div class="font-bold mt-2">{{ a.montant }}</div>
                            <span :class="['inline-block mt-2 text-xs font-medium px-2 py-1 rounded-sm', a.cls]">{{ a.etat }}</span>
                        </div>
                    </div>
                </div>
                <div class="bg-card border border-border rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div class="font-headings font-bold text-lg">Transferts inter-pharmacies</div>
                        <span class="text-muted-foreground">↔</span>
                    </div>
                    <div class="flex flex-col gap-3 mt-4">
                        <div class="border border-border rounded-md p-3.5">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-bold">TRF-231</span>
                                <span class="text-xs font-medium px-2 py-0.5 rounded-sm bg-info-soft text-foreground">En transit</span>
                            </div>
                            <div class="text-sm mt-1">KM5 → Ratoma</div>
                            <div class="text-xs text-muted-foreground">Amoxicilline 500mg ×20 boîtes · 28 oct · 08:15</div>
                        </div>
                    </div>
                    <button class="mt-4 w-full border border-primary text-accent text-sm font-medium rounded-md py-2.5">Nouveau transfert</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
