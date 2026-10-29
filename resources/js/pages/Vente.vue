<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Vente rapide', href: '/vente' }];

const props = defineProps<{
    pharmacy: { id: number; name: string; city: string };
    products: { id: number; name: string; labo: string; price: string; price_raw: number; stock: number; tag: string | null }[];
    insurers: { id: number; name: string; code: string }[];
    customers: { id: number; name: string }[];
}>();

const search = ref('');
const cart = ref<{ product_id: number; name: string; price: number; qty: number; stock: number }[]>([]);
const selectedInsurer = ref<number | null>(null);
const discountPercent = ref(0);

const filtered = computed(() => {
    const q = search.value.toLowerCase();
    if (!q) return props.products;
    return props.products.filter((p) => p.name.toLowerCase().includes(q));
});

function add(p: any) {
    if (p.stock === 0) return;
    const existing = cart.value.find((c) => c.product_id === p.id);
    if (existing) existing.qty++;
    else cart.value.push({ product_id: p.id, name: p.name, price: p.price_raw, qty: 1, stock: p.stock });
}

function remove(idx: number) {
    cart.value.splice(idx, 1);
}

const subtotal = computed(() => cart.value.reduce((s, c) => s + c.price * c.qty, 0));
const discountAmount = computed(() => Math.round((subtotal.value * discountPercent.value) / 100));
const total = computed(() => subtotal.value - discountAmount.value);
const insurerPart = computed(() => selectedInsurer.value ? Math.round(total.value * 0.8) : 0);
const clientPart = computed(() => total.value - insurerPart.value);

function checkout() {
    if (cart.value.length === 0) return;
    router.post('/vente', {
        pharmacy_id: props.pharmacy.id,
        items: cart.value.map((c) => ({ product_id: c.product_id, quantity: c.qty, unit_price: c.price })),
        payments: [{ method: selectedInsurer.value ? 'assurance' : 'especes', amount: clientPart.value }],
        insurance_company_id: selectedInsurer.value,
        discount_amount: discountAmount.value,
        discount_percent: discountPercent.value,
    }, {
        onSuccess: () => { cart.value = []; selectedInsurer.value = null; discountPercent.value = 0; },
    });
}
</script>

<template>
    <Head title="Vente rapide" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col h-full">
            <div class="bg-card border-b border-border px-8 py-3.5 flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-primary" />
                    <span class="text-sm font-medium">Caisse 01 · {{ pharmacy.name }}</span>
                    <span class="text-xs bg-success-soft text-accent px-2 py-0.5 rounded-sm font-medium">Hors-ligne prêt · serveur local</span>
                </div>
                <div class="flex-1" />
                <span class="text-sm text-muted-foreground">Ticket en cours</span>
                <div class="w-9 h-9 rounded-full bg-secondary flex items-center justify-center text-sm font-bold text-secondary-foreground">AD</div>
            </div>

            <div class="flex gap-5 p-6 flex-1">
                <div class="flex-1 flex flex-col gap-4">
                    <div class="bg-card border border-border rounded-lg p-4 flex gap-3">
                        <div class="flex-1 flex items-center gap-3 border-2 border-primary rounded-md px-4 py-3 bg-input">
                            <input v-model="search" class="flex-1 bg-transparent outline-none text-base" placeholder="Rechercher un médicament, DCI, code-barres… (F2)" />
                        </div>
                        <button class="bg-dark text-white rounded-md px-5 flex items-center gap-2 text-sm font-medium">Scanner</button>
                    </div>

                    <div class="flex gap-2">
                        <a v-for="(c,i) in ['Tous','Antalgiques','Antibiotiques','Antipaludéens','Vitamines','Dispositifs']" :key="c" :class="['px-3.5 py-2 rounded-md text-sm font-medium border', i===0 ? 'bg-dark text-white border-dark' : 'bg-card border-border text-muted-foreground']">{{ c }}</a>
                    </div>

                    <div class="bg-card border border-border rounded-lg overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-border flex items-center justify-between">
                            <span class="font-headings font-bold">Résultats — {{ filtered.length }} produits</span>
                            <span class="text-xs text-muted-foreground">Stock temps réel · Vente bloquée si stock = 0</span>
                        </div>
                        <div v-for="r in filtered" :key="r.id" :class="['flex items-center gap-4 px-5 py-3.5 border-b border-border last:border-0', r.stock === 0 ? 'opacity-70 bg-muted/40' : '']">
                            <div class="w-11 h-11 rounded-md bg-secondary flex items-center justify-center text-secondary-foreground"></div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-[15px]">{{ r.name }}</span>
                                    <span v-if="r.tag" :class="['text-xs font-medium px-2 py-0.5 rounded-sm', r.stock === 0 ? 'bg-danger-soft text-danger' : r.stock < 15 ? 'bg-warning-soft text-gold' : 'bg-success-soft text-accent']">{{ r.tag }}</span>
                                </div>
                                <div class="text-sm text-muted-foreground">{{ r.labo }} · Stock : <span :class="r.stock === 0 ? 'text-danger font-medium' : r.stock < 15 ? 'text-gold font-medium' : ''">{{ r.stock }} boîtes</span></div>
                            </div>
                            <div class="font-bold">{{ r.price }}</div>
                            <button :disabled="r.stock === 0" @click="add(r)" :class="['text-sm font-medium rounded-md px-4 py-2', r.stock === 0 ? 'bg-muted text-muted-foreground' : 'bg-primary text-white']">{{ r.stock === 0 ? 'Bloqué' : '+ Ajouter' }}</button>
                        </div>
                    </div>
                </div>

                <div class="w-[400px] shrink-0 bg-card border border-border rounded-lg flex flex-col overflow-hidden">
                    <div class="p-5 border-b border-border">
                        <div class="flex items-center justify-between">
                            <div class="font-headings font-bold text-lg">Panier · {{ cart.length }} articles</div>
                            <a class="text-sm text-danger font-medium" @click="cart=[]">Vider</a>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <select v-model="selectedInsurer" class="flex-1 border-2 border-primary rounded-md px-3 py-2 bg-success-soft/30 text-sm font-medium">
                                <option :value="null">Aucune assurance</option>
                                <option v-for="i in insurers" :key="i.id" :value="i.id">{{ i.name }} · {{ i.code }}</option>
                            </select>
                            <div class="border border-border rounded-md px-3 py-2 text-sm text-muted-foreground flex items-center gap-2">-{{ discountPercent }}%</div>
                        </div>
                        <div class="mt-2 text-xs bg-info-soft text-foreground rounded-sm px-2.5 py-1.5">Réduction <input v-model.number="discountPercent" type="number" min="0" max="100" class="w-12 border rounded px-1" /> % · visa pharmacien au-delà de 10%</div>
                    </div>

                    <div class="flex-1 p-4 flex flex-col gap-3 overflow-auto">
                        <div v-for="(c, idx) in cart" :key="c.product_id" class="border border-border rounded-md p-3.5">
                            <div class="flex items-start justify-between gap-2">
                                <div class="font-medium text-sm">{{ c.name }}</div>
                                <span class="text-muted-foreground cursor-pointer" @click="remove(idx)"></span>
                            </div>
                            <div class="text-xs text-muted-foreground mt-0.5">{{ c.qty }} × {{ c.price.toLocaleString('fr-FR') }}</div>
                            <div class="flex items-center justify-between mt-2.5">
                                <div class="flex items-center border border-border rounded-sm">
                                    <span class="px-2.5 py-1 text-muted-foreground cursor-pointer" @click="c.qty = Math.max(1, c.qty-1)">−</span>
                                    <span class="px-2 text-sm font-bold">{{ c.qty }}</span>
                                    <span class="px-2.5 py-1 text-muted-foreground cursor-pointer" @click="c.qty++">+</span>
                                </div>
                                <span class="text-xs text-accent font-medium">Stock OK</span>
                                <span class="font-bold text-sm">{{ (c.price*c.qty).toLocaleString('fr-FR') }} GNF</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 border-t border-border bg-muted/30">
                        <div class="flex justify-between text-sm"><span class="text-muted-foreground">Sous-total</span><span class="font-medium">{{ subtotal.toLocaleString('fr-FR') }} GNF</span></div>
                        <div class="flex justify-between text-sm mt-1"><span class="text-muted-foreground">Remise pharmacien (-{{ discountPercent }}%)</span><span class="font-medium text-accent">−{{ discountAmount.toLocaleString('fr-FR') }} GNF</span></div>
                        <div v-if="selectedInsurer" class="mt-2 pt-2 border-t border-dashed border-border">
                            <div class="flex justify-between text-sm"><span>Part assureur (80%)</span><span class="font-medium">{{ insurerPart.toLocaleString('fr-FR') }} GNF</span></div>
                            <div class="flex justify-between text-sm mt-1"><span class="text-muted-foreground">Part client (20%)</span><span class="font-bold">{{ clientPart.toLocaleString('fr-FR') }} GNF</span></div>
                        </div>
                        <div class="flex justify-between items-center mt-3">
                            <span class="font-headings font-bold text-lg">À encaisser</span>
                            <span class="font-headings font-bold text-2xl text-accent">{{ (selectedInsurer ? clientPart : total).toLocaleString('fr-FR') }} GNF</span>
                        </div>
                        <button @click="checkout" class="mt-3 w-full bg-primary text-white font-medium rounded-md py-3.5 text-base flex items-center justify-center gap-2">Encaisser · Imprimer ticket</button>
                        <div class="flex gap-2 mt-2">
                            <button class="flex-1 border border-border bg-card rounded-md py-2.5 text-sm font-medium flex items-center justify-center gap-2">Mettre en attente</button>
                            <button class="flex-1 border border-border bg-card rounded-md py-2.5 text-sm font-medium flex items-center justify-center gap-2">Proforma</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
