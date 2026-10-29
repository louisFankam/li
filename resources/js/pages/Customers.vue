<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clients', href: '/customers' }];
const props = defineProps<{
    customers: { id: number; name: string; phone: string | null; email: string | null; insurance_company: { name: string } | null }[];
    insurers: { id: number; name: string }[];
}>();

const form = useForm({ name: '', phone: '', email: '', insurance_company_id: null as number | null });
const submit = () => form.post(route('customers.store'), { onSuccess: () => form.reset() });
function destroy(id: number) { if (confirm('Supprimer ?')) router.delete(route('customers.destroy', id)); }
</script>

<template>
    <Head title="Clients" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-8 flex flex-col gap-4">
            <form @submit.prevent="submit" class="grid grid-cols-4 gap-2">
                <input v-model="form.name" placeholder="Nom" class="border border-border rounded-md px-3 py-2 text-sm" />
                <input v-model="form.phone" placeholder="Téléphone" class="border border-border rounded-md px-3 py-2 text-sm" />
                <input v-model="form.email" placeholder="Email" class="border border-border rounded-md px-3 py-2 text-sm" />
                <select v-model="form.insurance_company_id" class="border border-border rounded-md px-3 py-2 text-sm">
                    <option :value="null">Assureur</option>
                    <option v-for="i in props.insurers" :key="i.id" :value="i.id">{{ i.name }}</option>
                </select>
                <button type="submit" class="bg-primary text-white rounded-md px-4 py-2 text-sm col-span-4">Ajouter</button>
            </form>
            <div class="bg-card border border-border rounded-lg overflow-hidden">
                <div v-for="c in props.customers" :key="c.id" class="flex items-center justify-between px-4 py-3 border-t border-border first:border-0">
                    <div>
                        <div class="font-medium">{{ c.name }}</div>
                        <div class="text-xs text-muted-foreground">{{ c.phone }} · {{ c.email }} · {{ c.insurance_company?.name }}</div>
                    </div>
                    <button @click="destroy(c.id)" class="text-xs border border-danger text-danger rounded-sm px-2.5 py-1.5">Supprimer</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
