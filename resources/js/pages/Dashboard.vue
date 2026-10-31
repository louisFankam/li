<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tableau de bord', href: '/dashboard' }];

const props = defineProps<{
    stats: { ca: string; tickets: string; lowStock: string; pendingInsurance: string; caDelta: number | null; ticketsDelta: number | null; claimsCount: number; cashOpen: boolean };
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
                <div class="flex-1 bg-card border border-border rounded-lg p-5">
                    <div class="text-xs text-muted-foreground font-medium">Chiffre d'affaires du jour</div>
                    <div class="font-headings font-bold text-2xl mt-1">{{ stats.ca }}</div>
                    <div class="text-xs text-muted-foreground font-medium mt-1">{{ stats.caDelta !== null ? (stats.caDelta >= 0 ? '+' : '') + stats.caDelta + ' % vs hier' : '—' }}</div>
                </div>
                <div class="flex-1 bg-card border border-border rounded-lg p-5">
                    <div class="text-xs text-muted-foreground font-medium">Tickets encaissés</div>
                    <div class="font-headings font-bold text-2xl mt-1">{{ stats.tickets }}</div>
                    <div class="text-xs text-muted-foreground font-medium mt-1">{{ ticketsDeltaLabel }}</div>
                </div>
                <div class="flex-1 bg-card border border-border rounded-lg p-5">
                    <div class="text-xs text-muted-foreground font-medium">Alertes stock faible</div>
                    <div class="font-headings font-bold text-2xl mt-1">{{ stats.lowStock }}</div>
                    <div class="text-xs text-gold font-medium mt-1">Seuil atteint ou rupture</div>
                </div>
                <div class="flex-1 bg-card border border-border rounded-lg p-5">
                    <div class="text-xs text-muted-foreground font-medium">Remboursements en attente</div>
                    <div class="font-headings font-bold text-2xl mt-1">{{ stats.pendingInsurance }}</div>
                    <div class="text-xs text-gold font-medium mt-1">{{ stats.claimsCount }} dossier(s) · caisse {{ stats.cashOpen ? 'ouverte' : 'fermée' }}</div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2 bg-card border border-border rounded-lg p-6">
                    <div class="font-headings font-bold text-lg">Ventes — 7 derniers jours</div>
                    <div class="text-sm text-muted-foreground">Vue consolidée</div>
                    <div class="mt-6 h-48 flex items-end gap-2">
                        <div v-for="c in chart" :key="c.date" class="flex-1 flex flex-col items-center gap-1">
                            <div class="w-full bg-primary/70 rounded-t" :style="{ height: maxChart ? (c.total / maxChart) * 100 + '%' : '4%' }" />
                            <span class="text-[10px] text-muted-foreground">{{ c.label }}</span>
                        </div>
                    </div>
                </div>
                <div class="bg-card border border-border rounded-lg p-6 flex flex-col">
                    <div class="flex items-center justify-between">
                        <div class="font-headings font-bold text-lg">Stocks faibles</div>
                        <Link href="/stocks" class="text-sm font-medium text-primary hover:underline">Voir tout</Link>
                    </div>
                    <div class="text-sm text-muted-foreground">Blocage auto des ventes à 0</div>
                    <div class="flex flex-col gap-3 mt-4 flex-1">
                        <div v-if="lowStock.length === 0" class="text-sm text-muted-foreground">Aucun produit sous le seuil.</div>
                        <div v-for="p in lowStock" :key="p.name + p.pharma" class="flex items-center gap-3 border border-border rounded-md px-3 py-2.5">
                            <span :class="['w-2 h-2 rounded-full', p.dot]" />
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium truncate">{{ p.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ p.pharma }} · Reste {{ p.stock }} / seuil {{ p.seuil }}</div>
                            </div>
                            <Link href="/achats" class="text-xs font-medium border border-border rounded-sm px-2 py-1.5 hover:bg-muted">Réassort</Link>
                        </div>
                    </div>
                    <Link href="/achats" class="mt-4 w-full bg-dark text-white text-sm font-medium rounded-md py-2.5 flex items-center justify-center gap-2 hover:bg-dark/90">
                        Commander aux fournisseurs
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2 bg-card border border-border rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div class="font-headings font-bold text-lg">Dernières ventes — temps réel</div>
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <span class="w-2 h-2 rounded-full bg-primary" /> Serveur local · Synchro auto
                        </div>
                    </div>
                    <div class="mt-4 border border-border rounded-md overflow-hidden">
                        <div class="grid grid-cols-6 bg-muted/60 text-xs font-medium text-muted-foreground px-4 py-2.5">
                            <span>Ticket</span><span>Client</span><span>Articles</span><span>Paiement</span><span>Total</span><span class="text-right">Statut</span>
                        </div>
                        <div v-for="s in lastSales" :key="s.id" class="grid grid-cols-6 px-4 py-3 border-t border-border text-sm items-center">
                            <span class="font-medium">{{ s.id }}<span class="block text-xs text-muted-foreground font-normal">{{ s.heure }}</span></span>
                            <span class="text-foreground">{{ s.client }}</span>
                            <span>{{ s.items }} articles</span>
                            <span class="text-muted-foreground">{{ s.mode }}</span>
                            <span class="font-bold">{{ s.total }}</span>
                            <span class="text-right">
                                <span :class="['text-xs font-medium px-2 py-1 rounded-sm', s.status === 'Payé' ? 'bg-success-soft text-accent' : 'bg-warning-soft text-gold']">{{ s.status }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="bg-card border border-border rounded-lg p-6">
                    <div class="font-headings font-bold text-lg">Performance par pharmacie</div>
                    <div class="text-sm text-muted-foreground">Comparaison du jour · en GNF</div>
                    <div class="flex flex-col gap-4 mt-5">
                        <div v-for="p in pharmacies" :key="p.name" class="border border-border rounded-md p-4">
                            <div class="flex items-center justify-between">
                                <div class="font-medium text-sm">{{ p.name }}</div>
                                <span v-if="p.badge" class="text-xs font-bold bg-dark text-white px-2 py-0.5 rounded-sm">{{ p.badge }}</span>
                            </div>
                            <div class="text-xs text-muted-foreground">{{ p.ville }}</div>
                            <div class="font-headings font-bold text-xl mt-2">{{ p.ca }} <span class="text-xs font-body font-normal text-muted-foreground">GNF</span></div>
                            <div class="h-2 rounded-full bg-muted mt-2 overflow-hidden">
                                <div class="h-full bg-primary rounded-full" :style="{ width: p.progress + '%' }"></div>
                            </div>
                            <div class="flex justify-between text-xs text-muted-foreground mt-2">
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
