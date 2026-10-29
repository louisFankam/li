<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Caisses & Finances', href: '/caisses' }];

defineProps<{
    pharmacy: { id: number; name: string; city: string };
    sessions: { caisse: string; user: string; espece: string; mobile: string; ecart: string; ok: boolean }[];
    totals: { encaisse: string; depenses: string; fond: string; resultat: string };
}>();
</script>

<template>
    <Head title="Caisses & Finances" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-8">
            <div class="grid grid-cols-4 gap-4">
                <div class="bg-card border border-border rounded-lg p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-md bg-secondary text-secondary-foreground flex items-center justify-center"></div>
                    <div><div class="text-xs text-muted-foreground font-medium">Encaissé aujourd’hui</div><div class="font-headings font-bold text-xl">{{ totals.encaisse }}</div><div class="text-xs text-accent font-medium">Espèces 62 % · Mobile 38 %</div></div>
                </div>
                <div class="bg-card border border-border rounded-lg p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-md bg-secondary text-secondary-foreground flex items-center justify-center"></div>
                    <div><div class="text-xs text-muted-foreground font-medium">Dépenses du jour</div><div class="font-headings font-bold text-xl">{{ totals.depenses }}</div><div class="text-xs text-gold font-medium">7 pièces · 1 en attente</div></div>
                </div>
                <div class="bg-card border border-border rounded-lg p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-md bg-secondary text-secondary-foreground flex items-center justify-center"></div>
                    <div><div class="text-xs text-muted-foreground font-medium">Fond de caisse total</div><div class="font-headings font-bold text-xl">{{ totals.fond }}</div><div class="text-xs text-accent font-medium">3 caisses ouvertes</div></div>
                </div>
                <div class="bg-card border border-border rounded-lg p-5 flex items-center gap-4">
                    <div class="w-11 h-11 rounded-md bg-secondary text-secondary-foreground flex items-center justify-center"></div>
                    <div><div class="text-xs text-muted-foreground font-medium">Résultat net estimé</div><div class="font-headings font-bold text-xl">{{ totals.resultat }}</div><div class="text-xs text-accent font-medium">Marge 28 % après charges</div></div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="bg-card border border-border rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div class="font-headings font-bold text-lg">État des caisses — en direct</div>
                        <button class="text-sm font-medium bg-dark text-white rounded-md px-4 py-2 flex items-center gap-2"> Clôturer ma caisse</button>
                    </div>
                    <div class="flex flex-col gap-3 mt-4">
                        <div v-for="c in sessions" :key="c.caisse" class="border border-border rounded-md p-4">
                            <div class="flex items-center justify-between">
                                <div class="font-medium text-sm">{{ c.caisse }}</div>
                                <span :class="['text-xs font-medium px-2 py-1 rounded-sm', c.ok ? 'bg-success-soft text-accent' : 'bg-warning-soft text-gold']">Écart {{ c.ecart }}</span>
                            </div>
                            <div class="text-xs text-muted-foreground">{{ c.user }}</div>
                            <div class="grid grid-cols-3 gap-2 mt-3 text-sm">
                                <div class="bg-muted/60 rounded-sm px-3 py-2"><div class="text-xs text-muted-foreground">Espèces</div><div class="font-bold">{{ c.espece }}</div></div>
                                <div class="bg-muted/60 rounded-sm px-3 py-2"><div class="text-xs text-muted-foreground">Mobile Money</div><div class="font-bold">{{ c.mobile }}</div></div>
                                <div class="border border-dashed border-border rounded-sm px-3 py-2 flex items-center justify-center gap-2 text-accent font-medium text-[13px]"> Z de caisse</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <div class="bg-card border border-border rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <div class="font-headings font-bold text-lg">Répartition des paiements</div>
                            <span class="text-xs text-muted-foreground">7 derniers jours</span>
                        </div>
                        <div class="flex h-4 rounded-full overflow-hidden mt-4">
                            <div class="bg-primary" style="width:52%"></div>
                            <div class="bg-dark" style="width:24%"></div>
                            <div class="bg-info" style="width:16%"></div>
                            <div class="bg-warning" style="width:8%"></div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-4 text-sm">
                            <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm bg-primary" /> Espèces — 52 %</span>
                            <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm bg-dark" /> Assurances — 24 %</span>
                            <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm bg-info" /> Orange / MTN MoMo — 16 %</span>
                            <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm bg-warning" /> Carte / virement — 8 %</span>
                        </div>
                        <div class="mt-4 border-t border-border pt-4 flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">Clôture d’hier validée · écarts 0</span>
                            <a class="font-medium text-primary">Voir le Z</a>
                        </div>
                    </div>

                    <div class="bg-card border border-border rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <div class="font-headings font-bold text-lg">Dépenses récentes</div>
                            <button class="text-sm font-medium border border-border rounded-md px-3.5 py-2 flex items-center gap-2">+ Saisir dépense</button>
                        </div>
                        <div class="flex flex-col gap-2.5 mt-4">
                            <div v-for="e in [{label:'Loyer octobre — local Ratoma',cat:'Exploitation',montant:'8 500 000 GNF',by:'Dr. Touré · 26 oct',statut:'Validée'},{label:'Carburant groupe électrogène',cat:'Logistique',montant:'1 200 000 GNF',by:'S. Keïta · 27 oct',statut:'En attente'},{label:'Prime gardiennage nuit',cat:'Personnel',montant:'650 000 GNF',by:'A. Diallo · 28 oct',statut:'Validée'}]" :key="e.label" class="flex items-center gap-3 border border-border rounded-md px-3.5 py-3">
                                <div class="w-9 h-9 rounded-md bg-muted flex items-center justify-center"></div>
                                <div class="flex-1">
                                    <div class="text-sm font-medium">{{ e.label }}</div>
                                    <div class="text-xs text-muted-foreground">{{ e.cat }} · {{ e.by }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-sm">{{ e.montant }}</div>
                                    <div :class="['text-xs font-medium', e.statut === 'Validée' ? 'text-accent' : 'text-gold']">{{ e.statut }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mt-4">
                            <button v-for="r in ['Journal de caisse','Rapport marge','Bilan mensuel']" :key="r" class="border border-border rounded-md py-2.5 text-sm font-medium flex items-center justify-center gap-2"> {{ r }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
