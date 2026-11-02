<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pharmacies', href: '/pharmacies' }];
const props = defineProps<{
    pharmacies: { id: number; name: string; city: string | null; address: string | null; phone: string | null; is_active: boolean }[];
}>();

const form = useForm({ name: '', city: '', address: '', phone: '', is_active: true });
const submit = () => form.post(route('pharmacies.store'), { onSuccess: () => form.reset() });
function destroy(id: number) {
    if (confirm('Supprimer ?')) router.delete(route('pharmacies.destroy', id));
}
</script>

<template>
    <Head title="Pharmacies" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-8">
            <form @submit.prevent="submit" class="grid grid-cols-4 gap-2">
                <input v-model="form.name" placeholder="Nom" class="rounded-md border border-border px-3 py-2 text-sm" />
                <input v-model="form.city" placeholder="Ville" class="rounded-md border border-border px-3 py-2 text-sm" />
                <input v-model="form.phone" placeholder="Téléphone" class="rounded-md border border-border px-3 py-2 text-sm" />
                <input v-model="form.address" placeholder="Adresse" class="rounded-md border border-border px-3 py-2 text-sm" />
                <button type="submit" class="col-span-4 rounded-md bg-primary px-4 py-2 text-sm text-white">Ajouter</button>
            </form>
            <div class="overflow-hidden rounded-lg border border-border bg-card">
                <div
                    v-for="p in props.pharmacies"
                    :key="p.id"
                    class="flex items-center justify-between border-t border-border px-4 py-3 first:border-0"
                >
                    <div>
                        <div class="font-medium">{{ p.name }}</div>
                        <div class="text-xs text-muted-foreground">{{ p.city }} · {{ p.phone }}</div>
                    </div>
                    <button @click="destroy(p.id)" class="rounded-sm border border-danger px-2.5 py-1.5 text-xs text-danger">Supprimer</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
