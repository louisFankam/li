<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Assurances', href: '/assurances' }];

defineProps<{
    companies: { name: string; code: string; ventes: string; part: number; dossiers: number; attente: string; color: string }[];
    claims: { id: string; client: string; date: string; total: string; assureur: string; tclient: string; statut: string }[];
    totals: { assured: string; insurer: string; client: string; pending: string; dossiers: string };
}>();
</script>

<template>
    <Head title="Assurances" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-8">
            <div class="grid grid-cols-4 gap-4">
                <div class="bg-card border border-border rounded-lg p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-md bg-secondary text-secondary-foreground flex items-center justify-center"></div>
                    <div><div class="text-xs text-muted-foreground font-medium">Ventes assurées (mois)</div><div class="font-headings font-bold text-xl">{{ totals.assured }}</div><div class="text-xs text-accent font-medium">32 % du CA total</div></div>
                </div>
                <div class="bg-card border border-border rounded-lg p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-md bg-secondary text-secondary-foreground flex items-center justify-center"></div>
                    <div><div class="text-xs text-muted-foreground font-medium">Part assureurs</div><div class="font-headings font-bold text-xl">{{ totals.insurer }}</div><div class="text-xs text-accent font-medium">78,4 % en moyenne</div></div>
                </div>
                <div class="bg-card border border-border rounded-lg p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-md bg-secondary text-secondary-foreground flex items-center justify-center"></div>
                    <div><div class="text-xs text-muted-foreground font-medium">Part clients encaissée</div><div class="font-headings font-bold text-xl">{{ totals.client }}</div><div class="text-xs text-accent font-medium">100 % à la caisse</div></div>
                </div>
                <div class="bg-card border border-border rounded-lg p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-md bg-secondary text-secondary-foreground flex items-center justify-center">⏱</div>
                    <div><div class="text-xs text-muted-foreground font-medium">Remboursements en attente</div><div class="font-headings font-bold text-xl">{{ totals.pending }}</div><div class="text-xs text-gold font-medium">{{ totals.dossiers }}</div></div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2 bg-card border border-border rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div class="font-headings font-bold text-lg">Prises en charge du jour</div>
                        <div class="flex gap-2">
                            <button class="text-sm border border-border rounded-md px-3.5 py-2 font-medium flex items-center gap-2">Toutes compagnies</button>
                            <button class="text-sm bg-primary text-white rounded-md px-4 py-2 font-medium flex items-center gap-2">+ Nouvelle PEC</button>
                        </div>
                    </div>
                    <div class="mt-4 border border-border rounded-md overflow-hidden">
                        <div class="grid grid-cols-[1fr_1.6fr_1fr_1fr_1fr_0.9fr] bg-muted/60 text-xs font-medium text-muted-foreground px-4 py-2.5">
                            <span>N° PEC</span><span>Bénéficiaire</span><span>Total</span><span>Assureur</span><span>Client</span><span class="text-right">Statut</span>
                        </div>
                        <div v-for="c in claims" :key="c.id" class="grid grid-cols-[1fr_1.6fr_1fr_1fr_1fr_0.9fr] px-4 py-3 border-t border-border text-sm items-center">
                            <span class="font-bold">{{ c.id }}<span class="block text-xs font-normal text-muted-foreground">{{ c.date }}</span></span>
                            <span>{{ c.client }}</span>
                            <span class="font-medium">{{ c.total }}</span>
                            <span class="text-accent font-medium">{{ c.assureur }}</span>
                            <span>{{ c.tclient }}</span>
                            <span class="text-right">
                                <span :class="['text-xs font-medium px-2 py-1 rounded-sm', c.statut === 'Remboursé' ? 'bg-success-soft text-accent' : c.statut === 'Rejeté' ? 'bg-danger-soft text-danger' : c.statut === 'Envoyé' ? 'bg-info-soft text-foreground' : 'bg-warning-soft text-gold']">{{ c.statut }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-3 bg-muted/50 border border-border rounded-md px-4 py-3 text-sm">
                        <span class="text-accent">ℹ</span>
                        <span>Le ticket NSIA <b>#TK-90412</b> attend sa facture : part assureur à inclure au bordereau.</span>
                        <a class="ml-auto font-medium text-primary whitespace-nowrap">Générer le bordereau</a>
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <div class="bg-card border border-border rounded-lg p-6">
                        <div class="font-headings font-bold text-lg">Compagnies partenaires</div>
                        <div class="text-sm text-muted-foreground">Couverture et encours par assureur</div>
                        <div class="flex flex-col gap-4 mt-4">
                            <div v-for="c in companies" :key="c.name">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-medium">{{ c.name }}</span>
                                    <span class="font-bold">{{ c.ventes }}</span>
                                </div>
                                <div class="text-xs text-muted-foreground">{{ c.code }} · {{ c.dossiers }} dossiers · attente {{ c.attente }}</div>
                                <div class="h-2 bg-muted rounded-full mt-2 overflow-hidden">
                                    <div :class="['h-full rounded-full', c.color]" :style="{ width: c.part + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-dark text-white rounded-lg p-6">
                        <div class="flex items-center gap-2 font-headings font-bold text-lg"> Bordereau du mois</div>
                        <div class="text-sm opacity-70 mt-1">NSIA · 42 dossiers · 4 120 000 GNF</div>
                        <div class="flex gap-2 mt-4">
                            <button class="flex-1 bg-primary text-white text-sm font-medium rounded-md py-2.5">Exporter PDF</button>
                            <button class="flex-1 bg-white/10 text-white text-sm font-medium rounded-md py-2.5">Marquer envoyé</button>
                        </div>
                        <div class="text-xs opacity-60 mt-3">Délai moyen de remboursement NSIA : 11 jours · Taux de rejet 2,1 %</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
