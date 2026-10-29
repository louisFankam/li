<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Catégories', href: '/categories' }];
const props = defineProps<{ categories: { id: number; name: string }[] }>();

const form = useForm({ name: '' });
const submit = () => form.post(route('categories.store'), { onSuccess: () => form.reset() });
function destroy(id: number) { if (confirm('Supprimer ?')) router.delete(route('categories.destroy', id)); }
function update(c: { id: number; name: string }) {
    const name = prompt('Nouveau nom', c.name);
    if (name) router.put(route('categories.update', c.id), { name });
}
</script>

<template>
    <Head title="Catégories" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-8 flex flex-col gap-4">
            <form @submit.prevent="submit" class="flex gap-2">
                <input v-model="form.name" placeholder="Nom catégorie" class="border border-border rounded-md px-3 py-2 text-sm flex-1" />
                <button type="submit" class="bg-primary text-white rounded-md px-4 py-2 text-sm">Ajouter</button>
            </form>
            <div class="bg-card border border-border rounded-lg overflow-hidden">
                <div v-for="c in props.categories" :key="c.id" class="flex items-center justify-between px-4 py-3 border-t border-border first:border-0">
                    <span class="font-medium">{{ c.name }}</span>
                    <div class="flex gap-2">
                        <button @click="update(c)" class="text-xs border border-border rounded-sm px-2.5 py-1.5">Modifier</button>
                        <button @click="destroy(c.id)" class="text-xs border border-danger text-danger rounded-sm px-2.5 py-1.5">Supprimer</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
