<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Administration', href: '/administration' }];

defineProps<{
    pharmacies: { id: number; name: string; ville: string; active: boolean }[];
    users: { id: number; name: string; email: string; role: string; pharmacies: string }[];
}>();
</script>

<template>
    <Head title="Administration" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-8">
            <div class="rounded-lg border border-border bg-card p-6">
                <div class="mb-4 font-headings text-lg font-bold">Pharmacies</div>
                <div class="grid grid-cols-3 gap-4">
                    <div v-for="p in pharmacies" :key="p.id" class="rounded-md border border-border p-4">
                        <div class="text-sm font-medium">{{ p.name }}</div>
                        <div class="text-xs text-muted-foreground">{{ p.ville }}</div>
                        <span
                            :class="[
                                'mt-2 inline-block rounded-sm px-2 py-1 text-xs font-medium',
                                p.active ? 'bg-success-soft text-accent' : 'bg-danger-soft text-danger',
                            ]"
                            >{{ p.active ? 'Actif' : 'Inactif' }}</span
                        >
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-card p-6">
                <div class="mb-4 font-headings text-lg font-bold">Utilisateurs</div>
                <div class="overflow-hidden rounded-md border border-border">
                    <div class="grid grid-cols-4 bg-muted/60 px-4 py-2.5 text-xs font-medium text-muted-foreground">
                        <span>Nom</span><span>Email</span><span>Rôle</span><span>Pharmacies</span>
                    </div>
                    <div v-for="u in users" :key="u.id" class="grid grid-cols-4 border-t border-border px-4 py-3 text-sm">
                        <span class="font-medium">{{ u.name }}</span>
                        <span class="text-muted-foreground">{{ u.email }}</span>
                        <span>{{ u.role }}</span>
                        <span class="text-muted-foreground">{{ u.pharmacies }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
