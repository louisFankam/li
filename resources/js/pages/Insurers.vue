<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Power, X } from 'lucide-vue-next';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Assureurs', href: '/insurers' }];

type Company = {
    id: number;
    name: string;
    code: string | null;
    contact: string | null;
    phone: string | null;
    email: string | null;
    is_active: boolean;
    customers_count?: number;
};

const props = defineProps<{ companies: Company[] }>();

const form = useForm({
    name: '',
    code: '',
    contact: '',
    phone: '',
    email: '',
    is_active: true as boolean,
});

const editingId = ref<number | null>(null);

function resetForm() {
    editingId.value = null;
    form.reset();
    form.is_active = true;
    form.clearErrors();
}

function edit(company: Company) {
    editingId.value = company.id;
    form.name = company.name;
    form.code = company.code ?? '';
    form.contact = company.contact ?? '';
    form.phone = company.phone ?? '';
    form.email = company.email ?? '';
    form.is_active = company.is_active;
    form.clearErrors();
}

function submit() {
    if (editingId.value !== null) {
        form.put(route('insurers.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => resetForm(),
        });
        return;
    }

    form.post(route('insurers.store'), {
        preserveScroll: true,
        onSuccess: () => resetForm(),
    });
}

function disable(id: number) {
    if (!window.confirm('Désactiver cet assureur ?')) return;
    router.delete(route('insurers.destroy', id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Assureurs" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 lg:p-8">
            <form class="rounded-lg border border-border bg-card p-5" @submit.prevent="submit">
                <div class="mb-3 flex items-center justify-between">
                    <div class="font-headings text-lg font-bold">
                        {{ editingId ? 'Modifier l’assureur' : 'Nouvel assureur' }}
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

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div class="flex flex-col gap-1">
                        <label for="insurer-name" class="text-xs font-medium text-muted-foreground">Nom</label>
                        <input
                            id="insurer-name"
                            v-model="form.name"
                            type="text"
                            maxlength="190"
                            class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                            required
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="insurer-code" class="text-xs font-medium text-muted-foreground">Code</label>
                        <input
                            id="insurer-code"
                            v-model="form.code"
                            type="text"
                            maxlength="40"
                            class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="insurer-contact" class="text-xs font-medium text-muted-foreground">Contact</label>
                        <input
                            id="insurer-contact"
                            v-model="form.contact"
                            type="text"
                            maxlength="120"
                            class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="insurer-phone" class="text-xs font-medium text-muted-foreground">Téléphone</label>
                        <input
                            id="insurer-phone"
                            v-model="form.phone"
                            type="tel"
                            maxlength="40"
                            class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="insurer-email" class="text-xs font-medium text-muted-foreground">Email</label>
                        <input
                            id="insurer-email"
                            v-model="form.email"
                            type="email"
                            maxlength="190"
                            class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                        />
                    </div>
                    <label class="flex items-center gap-2 self-end pb-2 text-sm">
                        <input v-model="form.is_active" type="checkbox" class="h-4 w-4" />
                        Actif
                    </label>
                </div>

                <button
                    type="submit"
                    class="mt-4 flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                    :disabled="form.processing"
                >
                    <Plus v-if="!editingId" class="h-4 w-4" />
                    <Pencil v-else class="h-4 w-4" />
                    {{ editingId ? 'Enregistrer' : 'Ajouter l’assureur' }}
                </button>
            </form>

            <div class="overflow-hidden rounded-lg border border-border bg-card">
                <div class="p-5 font-headings text-lg font-bold">Assureurs ({{ props.companies.length }})</div>

                <div v-if="props.companies.length === 0" class="px-5 py-8 text-center text-sm text-muted-foreground">Aucun assureur enregistré.</div>

                <div
                    v-for="c in props.companies"
                    :key="c.id"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ c.name }}</span>
                            <span
                                class="rounded-sm px-2 py-0.5 text-xs font-medium"
                                :class="c.is_active ? 'bg-success-soft text-accent' : 'bg-muted text-muted-foreground'"
                            >
                                {{ c.is_active ? 'Actif' : 'Désactivé' }}
                            </span>
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ c.code || 'Sans code' }} · {{ c.contact || 'Sans contact' }} · {{ c.phone || '—' }} · {{ c.email || '—' }} ·
                            {{ c.customers_count ?? 0 }} client(s)
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
                            v-if="c.is_active"
                            type="button"
                            class="flex items-center gap-1 rounded-sm border border-danger px-2.5 py-1.5 text-xs font-medium text-danger"
                            @click="disable(c.id)"
                        >
                            <Power class="h-3.5 w-3.5" /> Désactiver
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
