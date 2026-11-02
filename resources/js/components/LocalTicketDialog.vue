<script setup lang="ts">
import type { OfflinePayment, OfflineSale, OfflineSaleItem } from '@/offline/db';
import { Printer, X } from 'lucide-vue-next';

defineProps<{
    sale: OfflineSale;
    items: OfflineSaleItem[];
    payments: OfflinePayment[];
}>();

const emit = defineEmits<{ close: [] }>();

const statusLabels: Record<OfflineSale['status'], string> = {
    pending: 'En attente de synchronisation',
    synced: 'Synchronisée',
    failed: 'À retenter',
    conflict: 'À régulariser',
    rejected: 'Rejetée',
};

const money = (value: number) => value.toLocaleString('fr-FR') + ' GNF';

function print() {
    window.print();
}
</script>

<template>
    <div class="fixed inset-0 z-[100] flex items-start justify-center overflow-auto bg-dark/60 p-4">
        <div class="w-full max-w-xl rounded-lg bg-card shadow-xl">
            <div class="no-print flex items-center justify-between border-b border-border p-4">
                <div>
                    <div class="font-headings text-lg font-bold">Ticket local</div>
                    <div class="text-xs text-muted-foreground">{{ statusLabels[sale.status] }}</div>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-medium text-white"
                        @click="print"
                    >
                        <Printer class="h-4 w-4" /> Imprimer
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-border p-2 text-muted-foreground"
                        aria-label="Fermer le ticket"
                        @click="emit('close')"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div class="print-area space-y-4 p-6">
                <div class="text-center">
                    <div class="font-headings text-xl font-bold">PharmaGest</div>
                    <div class="mt-1 text-xs text-muted-foreground">Ticket hors ligne</div>
                    <div class="mt-2 rounded-md bg-warning-soft px-3 py-1 text-xs font-medium text-gold">
                        {{ statusLabels[sale.status] }}
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <div class="text-xs text-muted-foreground">N° local</div>
                        <div class="font-semibold">{{ sale.sale_number }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-muted-foreground">Date</div>
                        <div>{{ new Date(sale.created_at).toLocaleString('fr-FR') }}</div>
                    </div>
                </div>

                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-y border-border text-xs uppercase text-muted-foreground">
                            <th class="py-2 text-left font-medium">Article</th>
                            <th class="py-2 text-center font-medium">Qté</th>
                            <th class="py-2 text-right font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items" :key="item.id" class="border-b border-border/60">
                            <td class="py-2">{{ item.name }}</td>
                            <td class="py-2 text-center">{{ item.quantity }}</td>
                            <td class="py-2 text-right">{{ money(item.total) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="ml-auto w-full max-w-xs space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Sous-total</span>
                        <span>{{ money(sale.subtotal) }}</span>
                    </div>
                    <div v-if="sale.discount_amount > 0" class="flex justify-between text-accent">
                        <span>Remise</span>
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

                <div class="border-t border-dashed border-border pt-3 text-sm">
                    <div class="mb-1 text-xs uppercase text-muted-foreground">Règlements</div>
                    <div v-for="payment in payments" :key="payment.id" class="flex justify-between">
                        <span>{{ payment.method }}</span>
                        <span>{{ money(payment.amount) }}</span>
                    </div>
                </div>

                <p v-if="sale.status === 'conflict' && sale.error" class="rounded-md bg-danger-soft p-3 text-sm text-danger">
                    {{ sale.error }}
                </p>
                <p class="text-center text-xs text-muted-foreground">Ticket conservé localement. Il sera définitif après synchronisation serveur.</p>
            </div>
        </div>
    </div>
</template>
