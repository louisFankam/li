<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Utilisateurs', href: '/users' }];
const props = defineProps<{
    users: { id: number; name: string; email: string; pharmacies: { id: number; name: string; role: string }[] }[];
    pharmacies: { id: number; name: string }[];
}>();

const form = useForm({ name: '', email: '', password: '', pharmacy_id: null as number | null, role: 'vendeur' });
const submit = () => form.post(route('users.store'), { onSuccess: () => form.reset() });
function destroy(id: number) { if (confirm('Supprimer ?')) router.delete(route('users.destroy', id)); }
</script>

<template>
    <Head title="Utilisateurs" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-8 flex flex-col gap-4">
            <form @submit.prevent="submit" class="grid grid-cols-3 gap-2">
                <input v-model="form.name" placeholder="Nom" class="border border-border rounded-md px-3 py-2 text-sm" />
                <input v-model="form.email" placeholder="Email" class="border border-border rounded-md px-3 py-2 text-sm" />
                <input v-model="form.password" type="password" placeholder="Mot de passe" class="border border-border rounded-md px-3 py-2 text-sm" />
                <select v-model="form.pharmacy_id" class="border border-border rounded-md px-3 py-2 text-sm">
                    <option :value="null">Pharmacie</option>
                    <option v-for="p in props.pharmacies" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <select v-model="form.role" class="border border-border rounded-md px-3 py-2 text-sm">
                    <option value="administrateur">Administrateur</option>
                    <option value="gerant">Gérant</option>
                    <option value="pharmacien">Pharmacien</option>
                    <option value="vendeur">Vendeur</option>
                    <option value="gestionnaire_stock">Gestionnaire stock</option>
                    <option value="comptable">Comptable</option>
                </select>
                <button type="submit" class="bg-primary text-white rounded-md px-4 py-2 text-sm">Ajouter</button>
            </form>
            <div class="bg-card border border-border rounded-lg overflow-hidden">
                <div v-for="u in props.users" :key="u.id" class="flex items-center justify-between px-4 py-3 border-t border-border first:border-0">
                    <div>
                        <div class="font-medium">{{ u.name }}</div>
                        <div class="text-xs text-muted-foreground">{{ u.email }} · {{ u.pharmacies.map(p => p.name + ' (' + p.role + ')').join(', ') }}</div>
                    </div>
                    <button @click="destroy(u.id)" class="text-xs border border-danger text-danger rounded-sm px-2.5 py-1.5">Supprimer</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
