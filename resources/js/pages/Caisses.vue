<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle, LockKeyhole, UnlockKeyhole } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Caisses & Dépenses', href: '/caisses' }];

type Session = {
    id: number;
    caisse: string;
    user: string;
    status: string;
    opening: string;
    closing: string;
    expected: string;
    difference: string;
    difference_raw: number | null;
    ok: boolean;
    opened_at: string | null;
    closed_at: string | null;
};

type Movement = {
    id: number;
    type: string;
    amount: string;
    amount_raw: number;
    reason: string | null;
    user: string | null;
    at: string;
};

const props = defineProps<{
    pharmacy: { id: number; name: string };
    sessions: Session[];
    totals: { encaisse: string; fond: string; attendu: string };
    openSession: number | null;
    openSessionInfo: {
        id: number;
        opening: number;
        opened_at: string | null;
        expected: number | null;
        movements: Movement[];
    } | null;
}>();

const money = (value: number) => value.toLocaleString('fr-FR') + ' GNF';

const openForm = useForm({ opening_amount: 0 });
const closeForm = useForm({ closing_amount: 0, notes: '' });
const moveForm = useForm({ type: 'entree', amount: 0, reason: '' });

function openCash() {
    openForm.post(route('caisses.open'), { preserveScroll: true, onSuccess: () => openForm.reset() });
}

function closeSession(id: number) {
    closeForm.post(route('caisses.close', id), { preserveScroll: true, onSuccess: () => closeForm.reset() });
}

function movement(id: number) {
    moveForm.post(route('caisses.movement', id), {
        preserveScroll: true,
        onSuccess: () => moveForm.reset('amount', 'reason'),
    });
}

function fillExpected() {
    if (props.openSessionInfo?.expected != null) {
        closeForm.closing_amount = props.openSessionInfo.expected;
    }
}
</script>

<template>
    <Head title="Caisses" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 lg:p-8">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-border bg-card p-5">
                    <div class="text-xs font-medium text-muted-foreground">Encaissé aujourd'hui (espèces)</div>
                    <div class="font-headings text-xl font-bold">{{ totals.encaisse }}</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-5">
                    <div class="text-xs font-medium text-muted-foreground">Fond de caisse</div>
                    <div class="font-headings text-xl font-bold">{{ totals.fond }}</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-5">
                    <div class="text-xs font-medium text-muted-foreground">Montant attendu</div>
                    <div class="font-headings text-xl font-bold">{{ totals.attendu }}</div>
                </div>
            </div>

            <div v-if="!openSessionInfo" class="rounded-lg border border-border bg-card p-5">
                <div class="mb-3 flex items-center gap-2 font-headings text-lg font-bold">
                    <UnlockKeyhole class="h-5 w-5 text-primary" /> Ouvrir une session de caisse
                </div>
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="openCash">
                    <div class="flex flex-col gap-1">
                        <label for="opening" class="text-xs text-muted-foreground">Fond initial (GNF)</label>
                        <input
                            id="opening"
                            v-model.number="openForm.opening_amount"
                            type="number"
                            min="0"
                            class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                        />
                    </div>
                    <button
                        type="submit"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                        :disabled="openForm.processing"
                    >
                        Ouvrir la caisse
                    </button>
                </form>
            </div>

            <div v-else class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-lg border border-border bg-card p-5 lg:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2 font-headings text-lg font-bold">
                            <LockKeyhole class="h-5 w-5 text-primary" /> Caisse ouverte
                        </div>
                        <span class="text-xs text-muted-foreground">Depuis {{ openSessionInfo.opened_at }}</span>
                    </div>
                    <div class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                        <div>
                            <div class="text-xs text-muted-foreground">Fond initial</div>
                            <div class="font-medium">{{ money(openSessionInfo.opening) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Mouvements</div>
                            <div class="font-medium">
                                {{ money(openSessionInfo.movements.reduce((s, m) => s + (m.type === 'entree' ? m.amount_raw : -m.amount_raw), 0)) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Attendu en caisse</div>
                            <div class="font-bold text-accent">{{ openSessionInfo.expected != null ? money(openSessionInfo.expected) : '—' }}</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="mb-2 text-sm font-medium">Mouvements de la session</div>
                        <div
                            v-if="openSessionInfo.movements.length === 0"
                            class="rounded-md border border-dashed border-border px-3 py-4 text-center text-sm text-muted-foreground"
                        >
                            Aucun mouvement pour l'instant.
                        </div>
                        <div
                            v-for="m in openSessionInfo.movements"
                            :key="m.id"
                            class="flex items-center justify-between border-b border-border py-2 text-sm last:border-0"
                        >
                            <span class="flex items-center gap-2">
                                <ArrowUpCircle v-if="m.type === 'entree'" class="h-4 w-4 text-accent" />
                                <ArrowDownCircle v-else class="h-4 w-4 text-danger" />
                                {{ m.reason || (m.type === 'entree' ? 'Entrée' : 'Retrait') }}
                                <span class="text-xs text-muted-foreground">· {{ m.user }} · {{ m.at }}</span>
                            </span>
                            <span :class="m.type === 'entree' ? 'text-accent' : 'text-danger'">
                                {{ m.type === 'entree' ? '+' : '−' }}{{ money(m.amount_raw) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <div class="rounded-lg border border-border bg-card p-5">
                        <div class="mb-3 text-sm font-medium">Nouveau mouvement</div>
                        <form class="flex flex-col gap-2" @submit.prevent="movement(openSessionInfo.id)">
                            <select
                                v-model="moveForm.type"
                                class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                                aria-label="Type de mouvement"
                            >
                                <option value="entree">Entrée de caisse</option>
                                <option value="retrait">Retrait / dépense</option>
                            </select>
                            <input
                                v-model.number="moveForm.amount"
                                type="number"
                                min="1"
                                placeholder="Montant (GNF)"
                                class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                                aria-label="Montant"
                            />
                            <input
                                v-model="moveForm.reason"
                                type="text"
                                maxlength="500"
                                placeholder="Motif"
                                class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                                aria-label="Motif"
                            />
                            <button
                                type="submit"
                                class="rounded-md bg-secondary px-3 py-2 text-sm font-medium text-secondary-foreground disabled:opacity-60"
                                :disabled="moveForm.processing"
                            >
                                Enregistrer le mouvement
                            </button>
                        </form>
                    </div>

                    <div class="rounded-lg border border-border bg-card p-5">
                        <div class="mb-3 text-sm font-medium">Clôturer la caisse</div>
                        <form class="flex flex-col gap-2" @submit.prevent="closeSession(openSessionInfo.id)">
                            <div class="flex gap-2">
                                <input
                                    v-model.number="closeForm.closing_amount"
                                    type="number"
                                    min="0"
                                    placeholder="Montant compté"
                                    class="min-w-0 flex-1 rounded-md border border-border bg-input px-3 py-2 text-sm"
                                    aria-label="Montant compté"
                                />
                                <button type="button" class="rounded-md border border-border px-3 py-2 text-xs" @click="fillExpected">Attendu</button>
                            </div>
                            <textarea
                                v-model="closeForm.notes"
                                rows="2"
                                maxlength="1000"
                                placeholder="Notes de clôture (optionnel)"
                                class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                            ></textarea>
                            <button
                                type="submit"
                                class="rounded-md bg-primary px-3 py-2 text-sm font-medium text-white disabled:opacity-60"
                                :disabled="closeForm.processing"
                            >
                                Clôturer
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-border bg-card">
                <div class="p-5 font-headings text-lg font-bold">Historique des sessions</div>
                <div class="overflow-x-auto">
                    <div class="min-w-[760px]">
                        <div class="grid grid-cols-[1.4fr_1.2fr_1fr_1fr_1fr_0.8fr] bg-muted/60 px-4 py-2.5 text-xs font-medium text-muted-foreground">
                            <span>Session</span><span>Période</span><span>Fond</span><span>Attendu</span><span>Compté</span><span>Écart</span>
                        </div>
                        <div v-if="sessions.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                            Aucune session enregistrée.
                        </div>
                        <div
                            v-for="s in sessions"
                            :key="s.id"
                            class="grid grid-cols-[1.4fr_1.2fr_1fr_1fr_1fr_0.8fr] items-center border-t border-border px-4 py-3 text-sm"
                        >
                            <span>
                                <span class="font-medium">{{ s.user }}</span>
                                <span class="block text-xs text-muted-foreground">
                                    {{ s.status === 'open' ? 'Ouverte' : 'Clôturée' }}
                                </span>
                            </span>
                            <span class="text-xs text-muted-foreground">{{ s.opened_at }} → {{ s.closed_at ?? '—' }}</span>
                            <span>{{ s.opening }}</span>
                            <span>{{ s.expected }}</span>
                            <span>{{ s.closing }}</span>
                            <span :class="s.ok ? 'text-accent' : 'text-danger'">{{ s.difference }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
