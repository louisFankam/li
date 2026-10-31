<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer, Plus } from 'lucide-vue-next';
import { onMounted } from 'vue';

const props = defineProps<{
    sale: {
        id: number;
        sale_number: string;
        date: string;
        status: string;
        pharmacy: { name: string; city: string | null; address: string | null; phone: string | null };
        seller: string;
        customer: string | null;
        insurer: string | null;
        items: { name: string; quantity: number; unit: string; unit_price: number; total: number }[];
        payments: { method: string; amount: number; reference: string | null }[];
        subtotal: number;
        discount_amount: number;
        discount_percent: number | null;
        total: number;
        client_part: number;
        insurer_part: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vente rapide', href: '/vente' },
    { title: 'Ticket', href: `/vente/${props.sale.id}/ticket` },
];

const money = (value: number) => value.toLocaleString('fr-FR') + ' GNF';

function printTicket() {
    window.print();
}

const methods: Record<string, string> = {
    especes: 'Espèces',
    mobile_money: 'Mobile money',
    carte: 'Carte bancaire',
    cheque: 'Chèque',
    virement: 'Virement',
    credit: 'Crédit',
};

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('print') === '1') {
        setTimeout(() => window.print(), 400);
    }
});
</script>

<template>
    <Head :title="`Ticket ${sale.sale_number}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4 lg:p-8">
            <div class="mb-4 flex items-center justify-between no-print">
                <Link href="/vente" class="flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="h-4 w-4" /> Retour à la vente
                </Link>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-md border border-border bg-card px-4 py-2 text-sm font-medium"
                        @click="printTicket"
                    >
                        <Printer class="h-4 w-4" /> Imprimer
                    </button>
                    <Link
                        href="/vente"
                        class="flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white"
                    >
                        <Plus class="h-4 w-4" /> Nouvelle vente
                    </Link>
                </div>
            </div>

            <div class="print-area rounded-lg border border-border bg-card p-6 shadow-sm">
                <div class="mb-6 text-center">
                    <div class="font-headings text-2xl font-bold text-primary">{{ sale.pharmacy.name }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ sale.pharmacy.address ?? sale.pharmacy.city ?? '' }}
                        <span v-if="sale.pharmacy.phone"> · Tél. {{ sale.pharmacy.phone }}</span>
                    </div>
                    <div class="mt-3 border-y border-dashed border-border py-2 text-sm font-medium">
                        TICKET DE VENTE
                    </div>
                </div>

                <div class="mb-4 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <div class="text-xs text-muted-foreground">N° de ticket</div>
                        <div class="font-semibold">{{ sale.sale_number }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-muted-foreground">Date</div>
                        <div class="font-semibold">{{ sale.date }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">Vendeur</div>
                        <div>{{ sale.seller }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-muted-foreground">Client</div>
                        <div>{{ sale.customer ?? 'Client comptoir' }}</div>
                    </div>
                    <div v-if="sale.insurer" class="col-span-2">
                        <div class="text-xs text-muted-foreground">Assurance</div>
                        <div>{{ sale.insurer }}</div>
                    </div>
                </div>

                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-y border-border text-xs uppercase text-muted-foreground">
                            <th class="py-2 text-left font-medium">Article</th>
                            <th class="py-2 text-center font-medium">Qté</th>
                            <th class="py-2 text-right font-medium">P.U.</th>
                            <th class="py-2 text-right font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in sale.items" :key="idx" class="border-b border-border/60">
                            <td class="py-2 pr-2">{{ item.name }}</td>
                            <td class="py-2 text-center">{{ item.quantity }}</td>
                            <td class="py-2 text-right">{{ money(item.unit_price) }}</td>
                            <td class="py-2 text-right font-medium">{{ money(item.total) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-4 ml-auto w-full max-w-xs space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Sous-total</span>
                        <span>{{ money(sale.subtotal) }}</span>
                    </div>
                    <div v-if="sale.discount_amount > 0" class="flex justify-between text-accent">
                        <span>Remise<span v-if="sale.discount_percent"> ({{ sale.discount_percent }} %)</span></span>
                        <span>−{{ money(sale.discount_amount) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-border pt-1 font-semibold">
                        <span>Total</span>
                        <span>{{ money(sale.total) }}</span>
                    </div>
                    <div v-if="sale.insurer_part > 0" class="flex justify-between text-muted-foreground">
                        <span>Part assureur</span>
                        <span>{{ money(sale.insurer_part) }}</span>
                    </div>
                    <div v-if="sale.insurer_part > 0" class="flex justify-between">
                        <span>Part client</span>
                        <span>{{ money(sale.client_part) }}</span>
                    </div>
                </div>

                <div v-if="sale.payments.length" class="mt-4 border-t border-dashed border-border pt-3 text-sm">
                    <div class="mb-1 text-xs uppercase text-muted-foreground">Règlements</div>
                    <div v-for="(p, idx) in sale.payments" :key="idx" class="flex justify-between">
                        <span>{{ methods[p.method] ?? p.method }}<span v-if="p.reference"> · {{ p.reference }}</span></span>
                        <span>{{ money(p.amount) }}</span>
                    </div>
                </div>

                <div class="mt-6 text-center text-xs text-muted-foreground">
                    <p>Merci de votre confiance. Conservez ce ticket.</p>
                    <p v-if="sale.status === 'cancelled'" class="mt-1 font-semibold text-danger">Vente annulée</p>
                    <p class="mt-2">Édité par PharmaGest</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
