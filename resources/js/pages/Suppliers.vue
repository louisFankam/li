<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Fournisseurs', href: '/suppliers' }];
const props = defineProps<{ suppliers: { id: number; name: string; contact: string | null; phone: string | null; email: string | null; address: string | null }[] }>();

const form = useForm({ name: '', contact: '', phone: '', email: '', address: '' });
const submit = () => form.post(route('suppliers.store'), { onSuccess: () => form.reset() });
function destroy(id: number) { if (confirm('Supprimer ?')) router.delete(route('suppliers.destroy', id)); }
</script>

<template>
    <Head title="Fournisseurs" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-8 flex flex-col gap-4">
            <form @submit.prevent="submit" class="grid grid-cols-3 gap-2">
                <input v-model="form.name" placeholder="Nom" class="border border-border rounded-md px-3 py-2 text-sm" />
                <input v-model="form.contact" placeholder="Contact" class="border border-border rounded-md px-3 py-2 text-sm" />
                <input v-model="form.phone" placeholder="Téléphone" class="border border-border rounded-md px-3 py-2 text-sm" />
                <input v-model="form.email" placeholder="Email" class="border border-border rounded-md px-3 py-2 text-sm" />
                <input v-model="form.address" placeholder="Adresse" class="border border-border rounded-md px-3 py-2 text-sm col-span-2" />
                <button type="submit" class="bg-primary text-white rounded-md px-4 py-2 text-sm">Ajouter</button>
            </form>
            <div class="bg-card border border-border rounded-lg overflow-hidden">
                <div v-for="s in props.suppliers" :key="s.id" class="flex items-center justify-between px-4 py-3 border-t border-border first:border-0">
                    <div>
                        <div class="font-medium">{{ s.name }}</div>
                        <div class="text-xs text-muted-foreground">{{ s.contact }} · {{ s.phone }} · {{ s.email }}</div>
                    </div>
                    <button @click="destroy(s.id)" class="text-xs border border-danger text-danger rounded-sm px-2.5 py-1.5">Supprimer</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
