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
            <div class="bg-card border border-border rounded-lg p-6">
                <div class="font-headings font-bold text-lg mb-4">Pharmacies</div>
                <div class="grid grid-cols-3 gap-4">
                    <div v-for="p in pharmacies" :key="p.id" class="border border-border rounded-md p-4">
                        <div class="font-medium text-sm">{{ p.name }}</div>
                        <div class="text-xs text-muted-foreground">{{ p.ville }}</div>
                        <span :class="['inline-block mt-2 text-xs font-medium px-2 py-1 rounded-sm', p.active ? 'bg-success-soft text-accent' : 'bg-danger-soft text-danger']">{{ p.active ? 'Actif' : 'Inactif' }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-card border border-border rounded-lg p-6">
                <div class="font-headings font-bold text-lg mb-4">Utilisateurs</div>
                <div class="border border-border rounded-md overflow-hidden">
                    <div class="grid grid-cols-4 bg-muted/60 text-xs font-medium text-muted-foreground px-4 py-2.5">
                        <span>Nom</span><span>Email</span><span>Rôle</span><span>Pharmacies</span>
                    </div>
                    <div v-for="u in users" :key="u.id" class="grid grid-cols-4 px-4 py-3 border-t border-border text-sm">
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
