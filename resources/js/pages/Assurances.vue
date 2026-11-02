<script setup lang="ts">
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Building2, Download, Filter } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Assurances', href: '/assurances' }];

type Company = {
    id: number;
    name: string;
    code: string;
    ventes: string;
    ventes_raw: number;
    part: number;
    dossiers: number;
    attente: string;
    attente_raw: number;
    rembourse: string;
    solde: string;
    solde_raw: number;
};

type Claim = {
    id: string;
    claim_id: number;
    client: string;
    assureur_nom: string;
    taux: number;
    date: string;
    total: string;
    assureur: string;
    tclient: string;
    rembourse: string;
    reference: string | null;
    statut: string;
    statut_label: string;
    transitions: string[];
};

const props = defineProps<{
    companies: Company[];
    claims: Claim[];
    totals: {
        assured: string;
        insurer: string;
        client: string;
        pending: string;
        reimbursed: string;
        dossiers: string;
        insurer_share: number;
        client_share: number;
    };
    bordereau: { id: number; name: string; dossiers: number; montant: string; montant_raw: number }[];
    filters: { from: string | null; to: string | null; status: string | null };
}>();

const { can } = usePermissions();
const canExport = computed(() => can('export_data'));

const form = reactive({
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    status: props.filters.status ?? '',
});

const transitionLabels: Record<string, string> = {
    a_facturer: 'À facturer',
    envoye: 'Marquer envoyé',
    rembourse: 'Marquer remboursé',
    rejete: 'Rejeter',
    annule: 'Annuler',
};

const statusClasses: Record<string, string> = {
    a_facturer: 'bg-warning-soft text-gold',
    envoye: 'bg-info-soft text-info',
    rembourse: 'bg-success-soft text-accent',
    rejete: 'bg-danger-soft text-danger',
    annule: 'bg-muted text-muted-foreground',
};

function applyFilters() {
    router.get(
        '/assurances',
        { from: form.from || undefined, to: form.to || undefined, status: form.status || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function changeStatus(claim: Claim, status: string) {
    if (!status) return;
    router.post(route('assurances.status', claim.claim_id), { status }, { preserveScroll: true });
}

function exportBordereau() {
    const params = new URLSearchParams({ type: 'assurances' });
    if (form.from) params.set('from', form.from);
    if (form.to) params.set('to', form.to);
    window.location.href = route('rapports.export') + '?' + params.toString();
}
</script>

<template>
    <Head title="Assurances" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 lg:p-8">
            <div class="flex justify-end">
                <Link
                    href="/insurers"
                    class="flex items-center gap-2 rounded-md border border-border bg-card px-4 py-2 text-sm font-medium hover:bg-muted"
                >
                    <Building2 class="h-4 w-4" /> Gérer les assureurs
                </Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-border bg-card p-5">
                    <div class="text-xs font-medium text-muted-foreground">Ventes assurées</div>
                    <div class="font-headings text-xl font-bold">{{ totals.assured }}</div>
                    <div class="text-xs text-muted-foreground">{{ totals.client_share }} % payés par les clients</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-5">
                    <div class="text-xs font-medium text-muted-foreground">Part assureurs</div>
                    <div class="font-headings text-xl font-bold">{{ totals.insurer }}</div>
                    <div class="text-xs text-muted-foreground">{{ totals.insurer_share }} % du total assuré</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-5">
                    <div class="text-xs font-medium text-muted-foreground">Part clients</div>
                    <div class="font-headings text-xl font-bold">{{ totals.client }}</div>
                    <div class="text-xs text-muted-foreground">Encaissée à la caisse</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-5">
                    <div class="text-xs font-medium text-muted-foreground">Remboursements en attente</div>
                    <div class="font-headings text-xl font-bold">{{ totals.pending }}</div>
                    <div class="text-xs font-medium text-gold">{{ totals.dossiers }}</div>
                </div>
            </div>

            <div class="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-card p-4">
                <div class="flex flex-col gap-1">
                    <label for="from" class="text-xs font-medium text-muted-foreground">Du</label>
                    <input id="from" v-model="form.from" type="date" class="rounded-md border border-border bg-input px-3 py-2 text-sm" />
                </div>
                <div class="flex flex-col gap-1">
                    <label for="to" class="text-xs font-medium text-muted-foreground">Au</label>
                    <input id="to" v-model="form.to" type="date" class="rounded-md border border-border bg-input px-3 py-2 text-sm" />
                </div>
                <div class="flex flex-col gap-1">
                    <label for="status" class="text-xs font-medium text-muted-foreground">Statut</label>
                    <select id="status" v-model="form.status" class="rounded-md border border-border bg-input px-3 py-2 text-sm">
                        <option value="">Tous</option>
                        <option value="a_facturer">À facturer</option>
                        <option value="envoye">Envoyé</option>
                        <option value="rembourse">Remboursé</option>
                        <option value="rejete">Rejeté</option>
                        <option value="annule">Annulé</option>
                    </select>
                </div>
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white"
                    @click="applyFilters"
                >
                    <Filter class="h-4 w-4" /> Filtrer
                </button>
            </div>

            <div class="grid gap-4 xl:grid-cols-3">
                <div class="overflow-hidden rounded-lg border border-border bg-card xl:col-span-2">
                    <div class="p-5 font-headings text-lg font-bold">Prises en charge</div>
                    <div class="overflow-x-auto">
                        <div class="min-w-[820px]">
                            <div
                                class="grid grid-cols-[1fr_1.6fr_1fr_1fr_1fr_1fr_1.2fr] bg-muted/60 px-4 py-2.5 text-xs font-medium text-muted-foreground"
                            >
                                <span>N° PEC</span><span>Bénéficiaire</span><span>Total</span><span>Assureur</span><span>Client</span
                                ><span>Remboursé</span><span class="text-right">Statut</span>
                            </div>
                            <div v-if="claims.length === 0" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                Aucune prise en charge sur cette période.
                            </div>
                            <div
                                v-for="c in claims"
                                :key="c.id"
                                class="grid grid-cols-[1fr_1.6fr_1fr_1fr_1fr_1fr_1.2fr] items-center border-t border-border px-4 py-3 text-sm"
                            >
                                <span class="font-bold">
                                    {{ c.id }}
                                    <span class="block text-xs font-normal text-muted-foreground">{{ c.date }}</span>
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate">{{ c.client }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">{{ c.assureur_nom }} · {{ c.taux }} %</span>
                                </span>
                                <span class="font-medium">{{ c.total }}</span>
                                <span class="font-medium text-accent">{{ c.assureur }}</span>
                                <span>{{ c.tclient }}</span>
                                <span>{{ c.rembourse }}</span>
                                <span class="flex flex-col items-end gap-1">
                                    <span class="rounded-sm px-2 py-1 text-xs font-medium" :class="statusClasses[c.statut] ?? 'bg-muted'">
                                        {{ c.statut_label }}
                                    </span>
                                    <select
                                        v-if="c.transitions.length"
                                        class="rounded border border-border px-1 py-0.5 text-xs"
                                        aria-label="Changer le statut"
                                        @change="(e) => changeStatus(c, (e.target as HTMLSelectElement).value)"
                                    >
                                        <option value="">Changer…</option>
                                        <option v-for="t in c.transitions" :key="t" :value="t">{{ transitionLabels[t] ?? t }}</option>
                                    </select>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <div class="rounded-lg border border-border bg-card p-6">
                        <div class="font-headings text-lg font-bold">Compagnies partenaires</div>
                        <div class="text-sm text-muted-foreground">Encours par assureur</div>
                        <div class="mt-4 flex flex-col gap-4">
                            <div v-if="companies.length === 0" class="text-sm text-muted-foreground">Aucun assureur actif.</div>
                            <div v-for="c in companies" :key="c.id">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-medium">{{ c.name }}</span>
                                    <span class="font-bold">{{ c.ventes }}</span>
                                </div>
                                <div class="text-xs text-muted-foreground">{{ c.code }} · {{ c.dossiers }} dossier(s) · encours {{ c.solde }}</div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-primary" :style="{ width: Math.min(100, c.part) + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-dark p-6 text-white">
                        <div class="flex items-center gap-2 font-headings text-lg font-bold">Bordereau</div>
                        <div v-if="bordereau.length === 0" class="mt-1 text-sm opacity-70">Aucun dossier en attente de facturation.</div>
                        <div v-else class="mt-3 space-y-2 text-sm">
                            <div v-for="b in bordereau" :key="b.id" class="flex justify-between gap-3">
                                <span class="opacity-80">{{ b.name }} · {{ b.dossiers }} dossier(s)</span>
                                <span class="font-medium">{{ b.montant }}</span>
                            </div>
                        </div>
                        <button
                            v-if="canExport"
                            type="button"
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-md bg-primary py-2.5 text-sm font-medium text-white disabled:opacity-50"
                            :disabled="bordereau.length === 0"
                            @click="exportBordereau"
                        >
                            <Download class="h-4 w-4" /> Exporter le bordereau
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
