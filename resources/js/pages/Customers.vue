<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clients', href: '/customers' }];

type Customer = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    insurance_company_id: number | null;
    insurance_company: { name: string } | null;
};

const props = defineProps<{
    customers: Customer[];
    insurers: { id: number; name: string }[];
}>();

const form = useForm({
    name: '',
    phone: '',
    email: '',
    insurance_company_id: null as number | null,
});

const editingId = ref<number | null>(null);

function resetForm() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function edit(customer: Customer) {
    editingId.value = customer.id;
    form.name = customer.name;
    form.phone = customer.phone ?? '';
    form.email = customer.email ?? '';
    form.insurance_company_id = customer.insurance_company_id;
    form.clearErrors();
}

function submit() {
    if (editingId.value !== null) {
        form.put(route('customers.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => resetForm(),
        });
        return;
    }

    form.post(route('customers.store'), {
        preserveScroll: true,
        onSuccess: () => resetForm(),
    });
}

function destroy(id: number) {
    if (!window.confirm('Supprimer ce client ?')) return;
    router.delete(route('customers.destroy', id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Clients" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 lg:p-8">
            <form class="rounded-lg border border-border bg-card p-5" @submit.prevent="submit">
                <div class="mb-3 flex items-center justify-between">
                    <div class="font-headings text-lg font-bold">
                        {{ editingId ? 'Modifier le client' : 'Nouveau client' }}
                    </div>
                    <button
                        v-if="editingId"
                        type="button"
                        class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                        @click="resetForm"
                    >
                        <X class="h-4 w-4" /> Annuler
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div class="flex flex-col gap-1">
                        <label for="customer-name" class="text-xs font-medium text-muted-foreground">Nom</label>
                        <input
                            id="customer-name"
                            v-model="form.name"
                            type="text"
                            maxlength="190"
                            class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                            required
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="customer-phone" class="text-xs font-medium text-muted-foreground">Téléphone</label>
                        <input
                            id="customer-phone"
                            v-model="form.phone"
                            type="tel"
                            maxlength="40"
                            class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="customer-email" class="text-xs font-medium text-muted-foreground">Email</label>
                        <input
                            id="customer-email"
                            v-model="form.email"
                            type="email"
                            maxlength="190"
                            class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="customer-insurer" class="text-xs font-medium text-muted-foreground">Assureur</label>
                        <select
                            id="customer-insurer"
                            v-model="form.insurance_company_id"
                            class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                        >
                            <option :value="null">Aucun</option>
                            <option v-for="i in props.insurers" :key="i.id" :value="i.id">{{ i.name }}</option>
                        </select>
                    </div>
                </div>

                <button
                    type="submit"
                    class="mt-4 flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                    :disabled="form.processing"
                >
                    <Plus v-if="!editingId" class="h-4 w-4" />
                    <Pencil v-else class="h-4 w-4" />
                    {{ editingId ? 'Enregistrer' : 'Ajouter le client' }}
                </button>
            </form>

            <div class="overflow-hidden rounded-lg border border-border bg-card">
                <div class="p-5 font-headings text-lg font-bold">Clients ({{ props.customers.length }})</div>

                <div v-if="props.customers.length === 0" class="px-5 py-8 text-center text-sm text-muted-foreground">Aucun client enregistré.</div>

                <div
                    v-for="c in props.customers"
                    :key="c.id"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3"
                >
                    <div class="min-w-0">
                        <div class="font-medium">{{ c.name }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ c.phone || 'Sans téléphone' }} · {{ c.email || 'Sans email' }} ·
                            {{ c.insurance_company?.name ?? 'Sans assureur' }}
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="flex items-center gap-1 rounded-sm border border-border px-2.5 py-1.5 text-xs font-medium"
                            @click="edit(c)"
                        >
                            <Pencil class="h-3.5 w-3.5" /> Modifier
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-1 rounded-sm border border-danger px-2.5 py-1.5 text-xs font-medium text-danger"
                            @click="destroy(c.id)"
                        >
                            <Trash2 class="h-3.5 w-3.5" /> Supprimer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
