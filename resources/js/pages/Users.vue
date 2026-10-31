<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Utilisateurs', href: '/users' }];

type UserRow = {
    id: number;
    name: string;
    email: string;
    is_super_admin?: boolean;
    pharmacies: { id: number; name: string; role: string }[];
};

const props = defineProps<{
    users: UserRow[];
    pharmacies: { id: number; name: string }[];
    roles: string[];
}>();

const roleLabels: Record<string, string> = {
    administrateur: 'Administrateur',
    responsable_regional: 'Responsable régional',
    gerant: 'Gérant',
    pharmacien: 'Pharmacien',
    vendeur: 'Vendeur',
    caissier: 'Caissier',
    gestionnaire_stock: 'Gestionnaire stock',
    comptable: 'Comptable',
};

const emptyPharmacy = () => ({ id: null as number | null, role: 'vendeur' });

const form = useForm({
    name: '',
    email: '',
    password: '',
    pharmacies: [emptyPharmacy()],
});

const editingId = ref<number | null>(null);

function resetForm() {
    editingId.value = null;
    form.reset();
    form.pharmacies = [emptyPharmacy()];
    form.clearErrors();
}

function edit(user: UserRow) {
    editingId.value = user.id;
    form.name = user.name;
    form.email = user.email;
    form.password = '';
    form.pharmacies = user.pharmacies.length
        ? user.pharmacies.map((p) => ({ id: p.id, role: p.role }))
        : [emptyPharmacy()];
    form.clearErrors();
}

function addPharmacy() {
    form.pharmacies.push(emptyPharmacy());
}

function removePharmacy(index: number) {
    if (form.pharmacies.length === 1) return;
    form.pharmacies.splice(index, 1);
}

function submit() {
    form.transform((data) => ({
        name: data.name,
        email: data.email,
        ...(editingId.value === null ? { password: data.password } : {}),
        pharmacies: data.pharmacies
            .filter((p) => p.id !== null)
            .map((p) => ({ id: p.id as number, role: p.role })),
    }));

    if (editingId.value !== null) {
        form.put(route('users.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => resetForm(),
        });
        return;
    }

    form.post(route('users.store'), {
        preserveScroll: true,
        onSuccess: () => resetForm(),
    });
}

function destroy(id: number) {
    if (!window.confirm('Supprimer cet utilisateur ?')) return;
    router.delete(route('users.destroy', id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Utilisateurs" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 lg:p-8">
            <form class="rounded-lg border border-border bg-card p-5" @submit.prevent="submit">
                <div class="mb-3 flex items-center justify-between">
                    <div class="font-headings text-lg font-bold">
                        {{ editingId ? 'Modifier l’utilisateur' : 'Nouvel utilisateur' }}
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

                <div class="grid gap-3 md:grid-cols-3">
                    <div class="flex flex-col gap-1">
                        <label for="user-name" class="text-xs font-medium text-muted-foreground">Nom</label>
                        <input
                            id="user-name"
                            v-model="form.name"
                            type="text"
                            maxlength="120"
                            class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                            required
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="user-email" class="text-xs font-medium text-muted-foreground">Email</label>
                        <input
                            id="user-email"
                            v-model="form.email"
                            type="email"
                            maxlength="190"
                            class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                            required
                        />
                    </div>
                    <div v-if="editingId === null" class="flex flex-col gap-1">
                        <label for="user-password" class="text-xs font-medium text-muted-foreground">Mot de passe</label>
                        <input
                            id="user-password"
                            v-model="form.password"
                            type="password"
                            minlength="8"
                            class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                            required
                        />
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-2">
                    <div class="text-xs font-medium text-muted-foreground">Accès aux pharmacies</div>
                    <div
                        v-for="(line, index) in form.pharmacies"
                        :key="index"
                        class="grid grid-cols-1 gap-2 md:grid-cols-[2fr_1.4fr_40px]"
                    >
                        <select
                            v-model="line.id"
                            class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                            aria-label="Pharmacie"
                        >
                            <option :value="null">Pharmacie…</option>
                            <option v-for="p in props.pharmacies" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <select
                            v-model="line.role"
                            class="rounded-md border border-border bg-input px-3 py-2 text-sm"
                            aria-label="Rôle"
                        >
                            <option v-for="r in props.roles" :key="r" :value="r">{{ roleLabels[r] ?? r }}</option>
                        </select>
                        <button
                            type="button"
                            class="flex items-center justify-center rounded-md border border-border text-muted-foreground hover:text-danger disabled:opacity-40"
                            :disabled="form.pharmacies.length === 1"
                            aria-label="Retirer la pharmacie"
                            @click="removePharmacy(index)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                    <button
                        type="button"
                        class="flex w-fit items-center gap-2 rounded-md border border-border px-3 py-2 text-sm"
                        @click="addPharmacy"
                    >
                        <Plus class="h-4 w-4" /> Ajouter une pharmacie
                    </button>
                </div>

                <button
                    type="submit"
                    class="mt-4 flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                    :disabled="form.processing"
                >
                    <Plus v-if="!editingId" class="h-4 w-4" />
                    <Pencil v-else class="h-4 w-4" />
                    {{ editingId ? 'Enregistrer' : 'Créer l’utilisateur' }}
                </button>
            </form>

            <div class="overflow-hidden rounded-lg border border-border bg-card">
                <div class="p-5 font-headings text-lg font-bold">Utilisateurs ({{ props.users.length }})</div>

                <div v-if="props.users.length === 0" class="px-5 py-8 text-center text-sm text-muted-foreground">
                    Aucun utilisateur enregistré.
                </div>

                <div
                    v-for="u in props.users"
                    :key="u.id"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ u.name }}</span>
                            <span
                                v-if="u.is_super_admin"
                                class="rounded-sm bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
                            >
                                Super admin
                            </span>
                        </div>
                        <div class="text-xs text-muted-foreground">{{ u.email }}</div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <span
                                v-for="p in u.pharmacies"
                                :key="p.id"
                                class="rounded-sm bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                            >
                                {{ p.name }} · {{ roleLabels[p.role] ?? p.role }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="flex items-center gap-1 rounded-sm border border-border px-2.5 py-1.5 text-xs font-medium"
                            @click="edit(u)"
                        >
                            <Pencil class="h-3.5 w-3.5" /> Modifier
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-1 rounded-sm border border-danger px-2.5 py-1.5 text-xs font-medium text-danger"
                            @click="destroy(u.id)"
                        >
                            <Trash2 class="h-3.5 w-3.5" /> Supprimer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
