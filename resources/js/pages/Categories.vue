<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Catégories', href: '/categories' }];
const props = defineProps<{ categories: { id: number; name: string }[] }>();

const form = useForm({ name: '' });
const submit = () => form.post(route('categories.store'), { onSuccess: () => form.reset() });
function destroy(id: number) {
    if (confirm('Supprimer ?')) router.delete(route('categories.destroy', id));
}
function update(c: { id: number; name: string }) {
    const name = prompt('Nouveau nom', c.name);
    if (name) router.put(route('categories.update', c.id), { name });
}
</script>

<template>
    <Head title="Catégories" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-8">
            <form @submit.prevent="submit" class="flex gap-2">
                <input v-model="form.name" placeholder="Nom catégorie" class="flex-1 rounded-md border border-border px-3 py-2 text-sm" />
                <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm text-white">Ajouter</button>
            </form>
            <div class="overflow-hidden rounded-lg border border-border bg-card">
                <div
                    v-for="c in props.categories"
                    :key="c.id"
                    class="flex items-center justify-between border-t border-border px-4 py-3 first:border-0"
                >
                    <span class="font-medium">{{ c.name }}</span>
                    <div class="flex gap-2">
                        <button @click="update(c)" class="rounded-sm border border-border px-2.5 py-1.5 text-xs">Modifier</button>
                        <button @click="destroy(c.id)" class="rounded-sm border border-danger px-2.5 py-1.5 text-xs text-danger">Supprimer</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
