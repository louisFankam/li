<script setup lang="ts">
import LocalTicketDialog from '@/components/LocalTicketDialog.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { OfflinePayment, OfflineSale, OfflineSaleItem } from '@/offline/db';
import { loadOfflineCatalog, type OfflineCatalogProduct } from '@/offline/catalog';
import { checkoutOfflineSale, localSales, localTicket, previewOfflineSale, type OfflineCheckoutInput } from '@/offline/sale';
import { refreshSyncState, syncState } from '@/offline/sync';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { useOnline } from '@vueuse/core';
import { AlertCircle, Check, Minus, Plus, Search, ShoppingCart, Trash2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Vente rapide', href: '/vente' }];

type Product = OfflineCatalogProduct;

type CartLine = {
    product_id: number;
    name: string;
    price: number;
    qty: number;
    stock: number;
};

type PaymentLine = { method: string; amount: number; reference: string };

type Preview = {
    subtotal: number;
    discount_percent: number | null;
    discount_amount: number;
    total: number;
    client_part: number;
    insurer_part: number;
};

const props = defineProps<{
    pharmacy: { id: number; name: string; city: string | null };
    products: Product[];
    insurers: { id: number; name: string; code: string }[];
    customers: { id: number; name: string; insurance_company_id: number | null }[];
    discountCap: { percent: number; amount: number };
    openSession: number | null;
    recentSales: { id: number; sale_number: string; total: string; status: string; time: string }[];
}>();

const { can } = usePermissions();
const canSell = computed(() => can('make_sales'));
const isOnline = useOnline();
const isOffline = computed(() => !isOnline.value);
const offlineCatalog = ref<Product[]>([]);
const localSalesRows = ref<OfflineSale[]>([]);
const localTicketData = ref<{
    sale: OfflineSale;
    items: OfflineSaleItem[];
    payments: OfflinePayment[];
} | null>(null);
const clientRequestId = ref(crypto.randomUUID());

const methods = [
    { value: 'especes', label: 'Espèces' },
    { value: 'mobile_money', label: 'Mobile money' },
    { value: 'carte', label: 'Carte bancaire' },
    { value: 'cheque', label: 'Chèque' },
    { value: 'virement', label: 'Virement' },
    { value: 'credit', label: 'Crédit' },
];

const search = ref('');
const activeCategory = ref<string | null>(null);
const cart = ref<CartLine[]>([]);
const customerId = ref<number | null>(null);
const insurerId = ref<number | null>(null);
const discountPercent = ref(0);
const discountReason = ref('');
const payments = ref<PaymentLine[]>([{ method: 'especes', amount: 0, reference: '' }]);
const paymentsTouched = ref(false);
const preview = ref<Preview | null>(null);
const previewing = ref(false);
const processing = ref(false);
const error = ref<string | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);

const money = (value: number) => value.toLocaleString('fr-FR') + ' GNF';

const localStatusLabels: Record<OfflineSale['status'], string> = {
    pending: 'En attente',
    synced: 'Synchronisée',
    failed: 'À retenter',
    conflict: 'À régulariser',
    rejected: 'Rejetée',
};

const localStatusClasses: Record<OfflineSale['status'], string> = {
    pending: 'bg-warning-soft text-gold',
    synced: 'bg-success-soft text-accent',
    failed: 'bg-warning-soft text-gold',
    conflict: 'bg-danger-soft text-danger',
    rejected: 'bg-danger-soft text-danger',
};

const visibleLocalSales = computed(() => (isOffline.value ? localSalesRows.value : localSalesRows.value.filter((sale) => sale.status !== 'synced')));

const catalogProducts = computed<Product[]>(() => (isOffline.value ? offlineCatalog.value : props.products));

const categories = computed(() => {
    const set = new Set<string>();
    catalogProducts.value.forEach((p) => p.category && set.add(p.category));
    return Array.from(set).sort();
});

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return catalogProducts.value.filter((p) => {
        if (activeCategory.value && p.category !== activeCategory.value) return false;
        if (!q) return true;
        return p.name.toLowerCase().includes(q) || (p.generic_name ?? '').toLowerCase().includes(q) || (p.barcode ?? '').toLowerCase().includes(q);
    });
});

const cartCount = computed(() => cart.value.reduce((s, c) => s + c.qty, 0));
const localSubtotal = computed(() => cart.value.reduce((s, c) => s + c.price * c.qty, 0));

/** Totaux : on privilégie l'aperçu serveur (règles d'assurance autoritaires). */
const subtotal = computed(() => preview.value?.subtotal ?? localSubtotal.value);
const discountAmount = computed(() => preview.value?.discount_amount ?? 0);
const total = computed(() => preview.value?.total ?? localSubtotal.value);
const insurerPart = computed(() => preview.value?.insurer_part ?? 0);
const clientPart = computed(() => preview.value?.client_part ?? localSubtotal.value);
const paid = computed(() => payments.value.reduce((s, p) => s + (Number(p.amount) || 0), 0));
const remaining = computed(() => clientPart.value - paid.value);

const needsReason = computed(() => discountPercent.value > props.discountCap.percent || discountAmount.value > props.discountCap.amount);

async function loadOfflineData() {
    try {
        offlineCatalog.value = await loadOfflineCatalog(props.pharmacy.id);
        localSalesRows.value = await localSales(props.pharmacy.id);
    } catch {
        offlineCatalog.value = [];
        localSalesRows.value = [];
    }
}

watch(
    [isOffline, () => syncState.userId, () => syncState.pending],
    ([, userId]) => {
        if (userId !== null) {
            void loadOfflineData();
        }
    },
    { immediate: true },
);

function add(product: Product) {
    if (product.stock <= 0) return;
    const existing = cart.value.find((c) => c.product_id === product.id);
    if (existing) {
        if (existing.qty >= existing.stock) return;
        existing.qty++;
    } else {
        cart.value.push({
            product_id: product.id,
            name: product.name,
            price: product.price_raw,
            qty: 1,
            stock: product.stock,
        });
    }
    error.value = null;
}

function decrement(line: CartLine) {
    line.qty = Math.max(1, line.qty - 1);
}

function increment(line: CartLine) {
    if (line.qty >= line.stock) return;
    line.qty++;
}

function removeLine(index: number) {
    cart.value.splice(index, 1);
}

function clearCart() {
    cart.value = [];
    payments.value = [{ method: 'especes', amount: 0, reference: '' }];
    paymentsTouched.value = false;
    preview.value = null;
    clientRequestId.value = crypto.randomUUID();
}

function onCustomerChange() {
    const customer = props.customers.find((c) => c.id === customerId.value);
    if (customer?.insurance_company_id) {
        insurerId.value = customer.insurance_company_id;
    }
}

function addPayment() {
    payments.value.push({ method: 'especes', amount: 0, reference: '' });
}

function removePayment(index: number) {
    payments.value.splice(index, 1);
    if (payments.value.length === 0) {
        payments.value.push({ method: 'especes', amount: 0, reference: '' });
    }
    paymentsTouched.value = true;
}

function fillRemaining(index: number) {
    const others = payments.value.reduce((s, p, i) => (i === index ? s : s + (Number(p.amount) || 0)), 0);
    payments.value[index].amount = Math.max(0, clientPart.value - others);
    paymentsTouched.value = true;
}

function offlineCheckoutInput(): OfflineCheckoutInput {
    return {
        clientRequestId: clientRequestId.value,
        pharmacyId: props.pharmacy.id,
        userId: syncState.userId ?? 0,
        customerId: customerId.value,
        insuranceCompanyId: insurerId.value,
        discountPercent: discountPercent.value,
        discountAmount: 0,
        discountReason: discountReason.value,
        discountCap: props.discountCap,
        items: cart.value.map((line) => ({ product_id: line.product_id, quantity: line.qty })),
        payments: payments.value
            .filter((payment) => Number(payment.amount) > 0)
            .map((payment) => ({
                method: payment.method,
                amount: Number(payment.amount),
                reference: payment.reference || null,
            })),
    };
}

function resetCheckout() {
    cart.value = [];
    customerId.value = null;
    insurerId.value = null;
    discountPercent.value = 0;
    discountReason.value = '';
    payments.value = [{ method: 'especes', amount: 0, reference: '' }];
    paymentsTouched.value = false;
    preview.value = null;
    clientRequestId.value = crypto.randomUUID();
}

async function openLocalTicket(clientRequest: string) {
    const ticket = await localTicket(clientRequest);
    if (ticket.sale) {
        localTicketData.value = { sale: ticket.sale, items: ticket.items, payments: ticket.payments };
    }
}

let previewTimer: ReturnType<typeof setTimeout> | undefined;

async function fetchPreview() {
    if (cart.value.length === 0) {
        preview.value = null;
        return;
    }

    previewing.value = true;

    if (isOffline.value) {
        try {
            preview.value = await previewOfflineSale(offlineCheckoutInput());
            error.value = null;

            if (!paymentsTouched.value && payments.value.length === 1) {
                payments.value[0].amount = preview.value.client_part;
            }
        } catch (previewError) {
            preview.value = null;
            error.value = previewError instanceof Error ? previewError.message : 'Calcul hors ligne impossible.';
        } finally {
            previewing.value = false;
        }

        return;
    }

    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

    try {
        const response = await fetch('/vente/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                items: cart.value.map((c) => ({ product_id: c.product_id, quantity: c.qty })),
                insurance_company_id: insurerId.value,
                discount_percent: discountPercent.value || null,
            }),
        });

        if (!response.ok) {
            preview.value = null;
            return;
        }

        preview.value = (await response.json()) as Preview;

        if (!paymentsTouched.value && payments.value.length === 1) {
            payments.value[0].amount = preview.value.client_part;
        }
    } catch {
        preview.value = null;
    } finally {
        previewing.value = false;
    }
}

watch(
    [cart, insurerId, discountPercent],
    () => {
        if (previewTimer) clearTimeout(previewTimer);
        previewTimer = setTimeout(fetchPreview, 250);
    },
    { deep: true },
);

async function checkout() {
    error.value = null;

    if (cart.value.length === 0) {
        error.value = 'Le panier est vide.';
        return;
    }

    if (paid.value !== clientPart.value) {
        error.value = `Le total des règlements (${money(paid.value)}) doit égaler la part client (${money(clientPart.value)}).`;
        return;
    }

    if (isOffline.value) {
        processing.value = true;

        try {
            const { sale } = await checkoutOfflineSale(offlineCheckoutInput());
            await refreshSyncState();
            await loadOfflineData();
            await openLocalTicket(sale.client_request_id);
            resetCheckout();
        } catch (checkoutError) {
            error.value = checkoutError instanceof Error ? checkoutError.message : 'La vente hors ligne a échoué.';
        } finally {
            processing.value = false;
        }

        return;
    }

    processing.value = true;

    router.post(
        '/vente',
        {
            pharmacy_id: props.pharmacy.id,
            client_request_id: clientRequestId.value,
            items: cart.value.map((c) => ({ product_id: c.product_id, quantity: c.qty })),
            payments: payments.value
                .filter((p) => Number(p.amount) > 0)
                .map((p) => ({
                    method: p.method,
                    amount: Number(p.amount),
                    reference: p.reference || null,
                })),
            customer_id: customerId.value,
            insurance_company_id: insurerId.value,
            discount_percent: discountPercent.value || null,
            discount_amount: discountAmount.value || null,
            discount_reason: discountReason.value || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                resetCheckout();
            },
            onError: (errors) => {
                error.value = Object.values(errors)[0] ?? 'La vente n’a pas pu être enregistrée.';
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'F2') {
        event.preventDefault();
        searchInput.value?.focus();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    if (previewTimer) clearTimeout(previewTimer);
});
</script>

<template>
    <Head title="Vente rapide" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col">
            <div class="no-print flex flex-wrap items-center gap-3 border-b border-border bg-card px-4 py-3 lg:px-8">
                <span class="flex items-center gap-2 text-sm font-medium">
                    <span class="h-2.5 w-2.5 rounded-full" :class="openSession ? 'bg-primary' : 'bg-warning'" />
                    {{ pharmacy.name }}
                </span>
                <span
                    class="rounded-sm px-2 py-0.5 text-xs font-medium"
                    :class="openSession ? 'bg-success-soft text-accent' : 'bg-warning-soft text-gold'"
                >
                    {{ openSession ? 'Caisse ouverte' : 'Aucune caisse ouverte' }}
                </span>
                <span v-if="isOffline" class="rounded-sm bg-danger-soft px-2 py-0.5 text-xs font-medium text-danger">Hors ligne</span>
                <div class="flex-1" />
                <span v-if="canSell" class="text-sm text-muted-foreground">Panier : {{ cartCount }} article(s)</span>
            </div>

            <div v-if="canSell && isOffline" class="mx-4 mt-4 rounded-lg border border-warning bg-warning-soft p-4 text-sm lg:mx-8">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <strong>Mode hors ligne</strong>
                    <span>{{ syncState.pending }} opération(s) en attente de synchronisation</span>
                </div>
                <p class="mt-1 text-muted-foreground">
                    Le catalogue local et le stock connu au moment de la dernière synchro sont utilisés. Le serveur reste autoritaire au retour
                    réseau.
                </p>
            </div>

            <div v-if="canSell && visibleLocalSales.length > 0" class="mx-4 mt-4 overflow-hidden rounded-lg border border-border bg-card lg:mx-8">
                <div class="p-4 font-headings text-lg font-bold">Ventes locales à suivre</div>
                <div
                    v-for="sale in visibleLocalSales"
                    :key="sale.client_request_id"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3 text-sm"
                >
                    <div>
                        <div class="font-medium">{{ sale.sale_number }}</div>
                        <div class="text-xs text-muted-foreground">{{ new Date(sale.created_at).toLocaleString('fr-FR') }}</div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="rounded-sm px-2 py-1 text-xs font-medium" :class="localStatusClasses[sale.status]">
                            {{ localStatusLabels[sale.status] }}
                        </span>
                        <span class="font-bold">{{ money(sale.total) }}</span>
                        <button type="button" class="text-primary hover:underline" @click="openLocalTicket(sale.client_request_id)">Ticket</button>
                    </div>
                </div>
            </div>

            <div v-if="!canSell" class="flex flex-col gap-5 p-4 lg:p-8">
                <div class="rounded-lg border border-border bg-warning-soft p-5 text-sm">
                    <div class="font-headings text-lg font-bold text-foreground">Mode consultation</div>
                    <p class="mt-1 text-muted-foreground">
                        Votre rôle permet de consulter les ventes, mais pas d’en créer. Utilisez les rapports pour l’analyse.
                    </p>
                    <Link href="/rapports" class="mt-3 inline-block font-medium text-accent hover:underline">Accéder aux rapports</Link>
                </div>

                <div class="overflow-hidden rounded-lg border border-border bg-card">
                    <div class="p-5 font-headings text-lg font-bold">Ventes récentes</div>
                    <div v-if="recentSales.length === 0" class="px-5 pb-6 text-sm text-muted-foreground">Aucune vente récente.</div>
                    <div
                        v-for="sale in recentSales"
                        :key="sale.id"
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 text-sm"
                    >
                        <div>
                            <div class="font-medium">{{ sale.sale_number }}</div>
                            <div class="text-xs text-muted-foreground">{{ sale.time }} · {{ sale.status }}</div>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="font-bold">{{ sale.total }}</span>
                            <Link :href="route('vente.ticket', sale.id)" class="text-sm font-medium text-primary hover:underline">
                                Voir le ticket
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="flex flex-1 flex-col gap-5 p-4 lg:flex-row lg:p-6">
                <!-- Catalogue -->
                <div class="flex min-w-0 flex-1 flex-col gap-4">
                    <div class="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-card p-4">
                        <div class="flex flex-1 items-center gap-3 rounded-md border-2 border-primary bg-input px-4 py-2.5">
                            <Search class="h-4 w-4 shrink-0 text-muted-foreground" />
                            <input
                                ref="searchInput"
                                v-model="search"
                                type="search"
                                class="flex-1 bg-transparent text-base outline-none"
                                placeholder="Rechercher un médicament, DCI, code-barres… (F2)"
                                aria-label="Rechercher un produit"
                            />
                            <button
                                v-if="search"
                                type="button"
                                class="text-muted-foreground hover:text-foreground"
                                aria-label="Effacer la recherche"
                                @click="search = ''"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                        <span class="text-xs text-muted-foreground"> Vente bloquée si stock = 0 </span>
                    </div>

                    <div class="no-print flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-md border px-3.5 py-2 text-sm font-medium"
                            :class="
                                activeCategory === null
                                    ? 'border-dark bg-dark text-white'
                                    : 'border-border bg-card text-muted-foreground hover:text-foreground'
                            "
                            @click="activeCategory = null"
                        >
                            Toutes
                        </button>
                        <button
                            v-for="c in categories"
                            :key="c"
                            type="button"
                            class="rounded-md border px-3.5 py-2 text-sm font-medium"
                            :class="
                                activeCategory === c
                                    ? 'border-dark bg-dark text-white'
                                    : 'border-border bg-card text-muted-foreground hover:text-foreground'
                            "
                            @click="activeCategory = c"
                        >
                            {{ c }}
                        </button>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-border bg-card">
                        <div class="flex items-center justify-between border-b border-border px-5 py-3.5">
                            <span class="font-headings font-bold">Résultats — {{ filtered.length }} produits</span>
                            <span class="text-xs text-muted-foreground">
                                {{ isOffline ? 'Catalogue local · stock de la dernière synchro' : 'Prix serveur · stock temps réel' }}
                            </span>
                        </div>

                        <div v-if="filtered.length === 0" class="px-5 py-10 text-center text-sm text-muted-foreground">
                            Aucun produit ne correspond à la recherche.
                        </div>

                        <div
                            v-for="p in filtered"
                            :key="p.id"
                            class="flex items-center gap-4 border-b border-border px-5 py-3.5 last:border-0"
                            :class="p.stock === 0 ? 'bg-muted/40 opacity-70' : ''"
                        >
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-secondary text-xs font-bold text-secondary-foreground"
                            >
                                {{ (p.name ?? '?').slice(0, 2).toUpperCase() }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="truncate text-[15px] font-medium">{{ p.name }}</span>
                                    <span
                                        v-if="p.tag"
                                        class="rounded-sm px-2 py-0.5 text-xs font-medium"
                                        :class="p.stock === 0 ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-gold'"
                                    >
                                        {{ p.tag }}
                                    </span>
                                </div>
                                <div class="truncate text-sm text-muted-foreground">
                                    {{ p.labo }}
                                    <span v-if="p.category"> · {{ p.category }}</span>
                                    · Stock :
                                    <span
                                        :class="
                                            p.stock === 0 ? 'font-medium text-danger' : p.stock < p.alert_threshold ? 'font-medium text-gold' : ''
                                        "
                                    >
                                        {{ p.stock }}
                                    </span>
                                </div>
                            </div>
                            <div class="shrink-0 font-bold">{{ p.price }}</div>
                            <button
                                type="button"
                                :disabled="p.stock === 0"
                                class="shrink-0 rounded-md px-4 py-2 text-sm font-medium"
                                :class="
                                    p.stock === 0 ? 'cursor-not-allowed bg-muted text-muted-foreground' : 'bg-primary text-white hover:bg-primary/90'
                                "
                                @click="add(p)"
                            >
                                {{ p.stock === 0 ? 'Bloqué' : '+ Ajouter' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Panier -->
                <div class="flex w-full shrink-0 flex-col overflow-hidden rounded-lg border border-border bg-card lg:w-[420px]">
                    <div class="border-b border-border p-5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 font-headings text-lg font-bold">
                                <ShoppingCart class="h-4 w-4" /> Panier · {{ cartCount }}
                            </div>
                            <button
                                v-if="cart.length"
                                type="button"
                                class="flex items-center gap-1 text-sm font-medium text-danger hover:underline"
                                @click="clearCart"
                            >
                                <Trash2 class="h-3.5 w-3.5" /> Vider
                            </button>
                        </div>

                        <div class="mt-3 grid gap-2">
                            <select
                                v-model="customerId"
                                class="w-full rounded-md border border-border bg-input px-3 py-2 text-sm"
                                aria-label="Client"
                                @change="onCustomerChange"
                            >
                                <option :value="null">Client comptoir</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <select
                                v-model="insurerId"
                                class="w-full rounded-md border border-border bg-input px-3 py-2 text-sm"
                                aria-label="Assurance"
                            >
                                <option :value="null">Aucune assurance</option>
                                <option v-for="i in insurers" :key="i.id" :value="i.id">
                                    {{ i.name }}<span v-if="i.code"> · {{ i.code }}</span>
                                </option>
                            </select>
                        </div>

                        <div class="mt-2 grid grid-cols-[auto_1fr] items-center gap-2">
                            <label for="discount" class="text-xs text-muted-foreground">Remise</label>
                            <div class="flex items-center gap-2">
                                <input
                                    id="discount"
                                    v-model.number="discountPercent"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-20 rounded-md border border-border bg-input px-2 py-1.5 text-sm"
                                />
                                <span class="text-xs text-muted-foreground">
                                    % · plafond {{ discountCap.percent }} % / {{ money(discountCap.amount) }}
                                </span>
                            </div>
                            <label v-if="needsReason" for="discount-reason" class="text-xs text-muted-foreground"> Motif </label>
                            <input
                                v-if="needsReason"
                                id="discount-reason"
                                v-model="discountReason"
                                type="text"
                                maxlength="255"
                                class="w-full rounded-md border border-border bg-input px-2 py-1.5 text-sm"
                                placeholder="Motif de la remise exceptionnelle"
                            />
                        </div>
                    </div>

                    <div class="flex max-h-[45vh] flex-1 flex-col gap-3 overflow-auto p-4">
                        <div v-if="cart.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                            Ajoutez des produits pour démarrer un ticket.
                        </div>

                        <div v-for="(line, index) in cart" :key="line.product_id" class="rounded-md border border-border p-3.5">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1 text-sm font-medium">{{ line.name }}</div>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-danger"
                                    aria-label="Retirer du panier"
                                    @click="removeLine(index)"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                            <div class="mt-0.5 text-xs text-muted-foreground">{{ line.qty }} × {{ money(line.price) }} · stock {{ line.stock }}</div>
                            <div class="mt-2.5 flex items-center justify-between">
                                <div class="flex items-center rounded-sm border border-border">
                                    <button
                                        type="button"
                                        class="px-2.5 py-1 text-muted-foreground hover:text-foreground"
                                        aria-label="Diminuer la quantité"
                                        @click="decrement(line)"
                                    >
                                        <Minus class="h-3 w-3" />
                                    </button>
                                    <span class="px-2 text-sm font-bold">{{ line.qty }}</span>
                                    <button
                                        type="button"
                                        class="px-2.5 py-1 text-muted-foreground hover:text-foreground disabled:opacity-40"
                                        :disabled="line.qty >= line.stock"
                                        aria-label="Augmenter la quantité"
                                        @click="increment(line)"
                                    >
                                        <Plus class="h-3 w-3" />
                                    </button>
                                </div>
                                <span class="text-sm font-bold">{{ money(line.price * line.qty) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-border bg-muted/30 p-5">
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Sous-total</span>
                                <span class="font-medium">{{ money(subtotal) }}</span>
                            </div>
                            <div v-if="discountAmount > 0" class="flex justify-between">
                                <span class="text-muted-foreground">Remise</span>
                                <span class="font-medium text-accent">−{{ money(discountAmount) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-border pt-1">
                                <span class="font-medium">Total</span>
                                <span class="font-bold">{{ money(total) }}</span>
                            </div>
                            <template v-if="insurerPart > 0">
                                <div class="flex justify-between text-muted-foreground">
                                    <span>Part assureur</span>
                                    <span>{{ money(insurerPart) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Part client</span>
                                    <span class="font-bold">{{ money(clientPart) }}</span>
                                </div>
                            </template>
                        </div>

                        <div class="mt-4 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Règlements</span>
                                <button type="button" class="text-xs font-medium text-primary hover:underline" @click="addPayment">+ Ajouter</button>
                            </div>

                            <div v-for="(p, index) in payments" :key="index" class="flex items-center gap-2">
                                <select
                                    v-model="p.method"
                                    class="min-w-0 flex-1 rounded-md border border-border bg-input px-2 py-1.5 text-sm"
                                    aria-label="Moyen de paiement"
                                >
                                    <option v-for="m in methods" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                                <input
                                    v-model.number="p.amount"
                                    type="number"
                                    min="0"
                                    class="w-28 rounded-md border border-border bg-input px-2 py-1.5 text-right text-sm"
                                    aria-label="Montant"
                                    @input="paymentsTouched = true"
                                />
                                <button
                                    type="button"
                                    class="rounded p-1 text-muted-foreground hover:text-primary"
                                    title="Compléter le reste"
                                    aria-label="Compléter le reste"
                                    @click="fillRemaining(index)"
                                >
                                    <Check class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="payments.length > 1"
                                    type="button"
                                    class="rounded p-1 text-muted-foreground hover:text-danger"
                                    aria-label="Supprimer le règlement"
                                    @click="removePayment(index)"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">
                                    Reste à encaisser
                                    <span v-if="previewing" class="text-xs">(calcul…)</span>
                                </span>
                                <span class="font-medium" :class="remaining === 0 ? 'text-accent' : remaining < 0 ? 'text-danger' : 'text-gold'">
                                    {{ money(remaining) }}
                                </span>
                            </div>
                        </div>

                        <p v-if="error" class="mt-3 flex items-start gap-2 rounded-md bg-danger-soft px-3 py-2 text-sm text-danger">
                            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
                            <span>{{ error }}</span>
                        </p>

                        <button
                            type="button"
                            class="mt-3 flex w-full items-center justify-center gap-2 rounded-md bg-primary py-3.5 text-base font-medium text-white hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="processing || cart.length === 0 || remaining !== 0 || previewing"
                            @click="checkout"
                        >
                            {{ processing ? 'Enregistrement…' : isOffline ? 'Enregistrer hors ligne' : 'Encaisser · Imprimer ticket' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <LocalTicketDialog
            v-if="localTicketData"
            :sale="localTicketData.sale"
            :items="localTicketData.items"
            :payments="localTicketData.payments"
            @close="localTicketData = null"
        />
    </AppLayout>
</template>
