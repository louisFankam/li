<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Fournisseurs', href: '/suppliers' }];
const props = defineProps<{
    suppliers: { id: number; name: string; contact: string | null; phone: string | null; email: string | null; address: string | null }[];
}>();

const form = useForm({ name: '', contact: '', phone: '', email: '', address: '' });
const submit = () => form.post(route('suppliers.store'), { onSuccess: () => form.reset() });
function destroy(id: number) {
    if (confirm('Supprimer ?')) router.delete(route('suppliers.destroy', id));
}
</script>

<template>
    <Head title="Fournisseurs" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-8">
            <form @submit.prevent="submit" class="grid grid-cols-3 gap-2">
                <input v-model="form.name" placeholder="Nom" class="rounded-md border border-border px-3 py-2 text-sm" />
                <input v-model="form.contact" placeholder="Contact" class="rounded-md border border-border px-3 py-2 text-sm" />
                <input v-model="form.phone" placeholder="Téléphone" class="rounded-md border border-border px-3 py-2 text-sm" />
                <input v-model="form.email" placeholder="Email" class="rounded-md border border-border px-3 py-2 text-sm" />
                <input v-model="form.address" placeholder="Adresse" class="col-span-2 rounded-md border border-border px-3 py-2 text-sm" />
                <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm text-white">Ajouter</button>
            </form>
            <div class="overflow-hidden rounded-lg border border-border bg-card">
                <div
                    v-for="s in props.suppliers"
                    :key="s.id"
                    class="flex items-center justify-between border-t border-border px-4 py-3 first:border-0"
                >
                    <div>
                        <div class="font-medium">{{ s.name }}</div>
                        <div class="text-xs text-muted-foreground">{{ s.contact }} · {{ s.phone }} · {{ s.email }}</div>
                    </div>
                    <button @click="destroy(s.id)" class="rounded-sm border border-danger px-2.5 py-1.5 text-xs text-danger">Supprimer</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
