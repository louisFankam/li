<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tableau de bord', href: '/dashboard' }];

const props = defineProps<{
    stats: {
        ca: string;
        tickets: string;
        lowStock: string;
        pendingInsurance: string;
        caDelta: number | null;
        ticketsDelta: number | null;
        claimsCount: number;
        cashOpen: boolean;
    };
    pharmacies: { id: number; name: string; ville: string; ca: string; tickets: number; panier: string; progress: number; badge: string | null }[];
    lowStock: { name: string; pharma: string; stock: number; seuil: number; dot: string }[];
    lastSales: { id: string; heure: string; client: string; items: number; total: string; mode: string; status: string }[];
    chart: { label: string; date: string; total: number }[];
}>();

const maxChart = computed(() => Math.max(1, ...props.chart.map((c) => c.total)));

const ticketsDeltaLabel = computed(() => {
    if (props.stats.ticketsDelta === null) return '—';
    const delta = props.stats.ticketsDelta;
    return `${delta >= 0 ? '+' : ''}${delta} % vs hier`;
});
</script>

<template>
    <Head title="Tableau de bord" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-8">
            <div class="flex gap-4">
                <div class="flex-1 rounded-lg border border-border bg-card p-5">
                    <div class="text-xs font-medium text-muted-foreground">Chiffre d'affaires du jour</div>
                    <div class="mt-1 font-headings text-2xl font-bold">{{ stats.ca }}</div>
                    <div class="mt-1 text-xs font-medium text-muted-foreground">
                        {{ stats.caDelta !== null ? (stats.caDelta >= 0 ? '+' : '') + stats.caDelta + ' % vs hier' : '—' }}
                    </div>
                </div>
                <div class="flex-1 rounded-lg border border-border bg-card p-5">
                    <div class="text-xs font-medium text-muted-foreground">Tickets encaissés</div>
                    <div class="mt-1 font-headings text-2xl font-bold">{{ stats.tickets }}</div>
                    <div class="mt-1 text-xs font-medium text-muted-foreground">{{ ticketsDeltaLabel }}</div>
                </div>
                <div class="flex-1 rounded-lg border border-border bg-card p-5">
                    <div class="text-xs font-medium text-muted-foreground">Alertes stock faible</div>
                    <div class="mt-1 font-headings text-2xl font-bold">{{ stats.lowStock }}</div>
                    <div class="mt-1 text-xs font-medium text-gold">Seuil atteint ou rupture</div>
                </div>
                <div class="flex-1 rounded-lg border border-border bg-card p-5">
                    <div class="text-xs font-medium text-muted-foreground">Remboursements en attente</div>
                    <div class="mt-1 font-headings text-2xl font-bold">{{ stats.pendingInsurance }}</div>
                    <div class="mt-1 text-xs font-medium text-gold">
                        {{ stats.claimsCount }} dossier(s) · caisse {{ stats.cashOpen ? 'ouverte' : 'fermée' }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2 rounded-lg border border-border bg-card p-6">
                    <div class="font-headings text-lg font-bold">Ventes — 7 derniers jours</div>
                    <div class="text-sm text-muted-foreground">Vue consolidée</div>
                    <div class="mt-6 flex h-48 items-end gap-2">
                        <div v-for="c in chart" :key="c.date" class="flex flex-1 flex-col items-center gap-1">
                            <div class="w-full rounded-t bg-primary/70" :style="{ height: maxChart ? (c.total / maxChart) * 100 + '%' : '4%' }" />
                            <span class="text-[10px] text-muted-foreground">{{ c.label }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col rounded-lg border border-border bg-card p-6">
                    <div class="flex items-center justify-between">
                        <div class="font-headings text-lg font-bold">Stocks faibles</div>
                        <Link href="/stocks" class="text-sm font-medium text-primary hover:underline">Voir tout</Link>
                    </div>
                    <div class="text-sm text-muted-foreground">Blocage auto des ventes à 0</div>
                    <div class="mt-4 flex flex-1 flex-col gap-3">
                        <div v-if="lowStock.length === 0" class="text-sm text-muted-foreground">Aucun produit sous le seuil.</div>
                        <div
                            v-for="p in lowStock"
                            :key="p.name + p.pharma"
                            class="flex items-center gap-3 rounded-md border border-border px-3 py-2.5"
                        >
                            <span :class="['h-2 w-2 rounded-full', p.dot]" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium">{{ p.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ p.pharma }} · Reste {{ p.stock }} / seuil {{ p.seuil }}</div>
                            </div>
                            <Link href="/achats" class="rounded-sm border border-border px-2 py-1.5 text-xs font-medium hover:bg-muted"
                                >Réassort</Link
                            >
                        </div>
                    </div>
                    <Link
                        href="/achats"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-md bg-dark py-2.5 text-sm font-medium text-white hover:bg-dark/90"
                    >
                        Commander aux fournisseurs
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2 rounded-lg border border-border bg-card p-6">
                    <div class="flex items-center justify-between">
                        <div class="font-headings text-lg font-bold">Dernières ventes — temps réel</div>
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <span class="h-2 w-2 rounded-full bg-primary" /> Serveur local · Synchro auto
                        </div>
                    </div>
                    <div class="mt-4 overflow-hidden rounded-md border border-border">
                        <div class="grid grid-cols-6 bg-muted/60 px-4 py-2.5 text-xs font-medium text-muted-foreground">
                            <span>Ticket</span><span>Client</span><span>Articles</span><span>Paiement</span><span>Total</span
                            ><span class="text-right">Statut</span>
                        </div>
                        <div v-for="s in lastSales" :key="s.id" class="grid grid-cols-6 items-center border-t border-border px-4 py-3 text-sm">
                            <span class="font-medium"
                                >{{ s.id }}<span class="block text-xs font-normal text-muted-foreground">{{ s.heure }}</span></span
                            >
                            <span class="text-foreground">{{ s.client }}</span>
                            <span>{{ s.items }} articles</span>
                            <span class="text-muted-foreground">{{ s.mode }}</span>
                            <span class="font-bold">{{ s.total }}</span>
                            <span class="text-right">
                                <span
                                    :class="[
                                        'rounded-sm px-2 py-1 text-xs font-medium',
                                        s.status === 'Payé' ? 'bg-success-soft text-accent' : 'bg-warning-soft text-gold',
                                    ]"
                                    >{{ s.status }}</span
                                >
                            </span>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-card p-6">
                    <div class="font-headings text-lg font-bold">Performance par pharmacie</div>
                    <div class="text-sm text-muted-foreground">Comparaison du jour · en GNF</div>
                    <div class="mt-5 flex flex-col gap-4">
                        <div v-for="p in pharmacies" :key="p.name" class="rounded-md border border-border p-4">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-medium">{{ p.name }}</div>
                                <span v-if="p.badge" class="rounded-sm bg-dark px-2 py-0.5 text-xs font-bold text-white">{{ p.badge }}</span>
                            </div>
                            <div class="text-xs text-muted-foreground">{{ p.ville }}</div>
                            <div class="mt-2 font-headings text-xl font-bold">
                                {{ p.ca }} <span class="font-body text-xs font-normal text-muted-foreground">GNF</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full bg-primary" :style="{ width: p.progress + '%' }"></div>
                            </div>
                            <div class="mt-2 flex justify-between text-xs text-muted-foreground">
                                <span>{{ p.tickets }} tickets</span>
                                <span>Panier {{ p.panier }} GNF</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
