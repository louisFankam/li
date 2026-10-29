<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import Topbar from '@/components/Topbar.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    subtitle?: string;
    showPharmacySwitch?: boolean;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    showPharmacySwitch: true,
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <Topbar :title="breadcrumbs.length ? breadcrumbs[breadcrumbs.length - 1].title : 'Tableau de bord'" :subtitle="subtitle" :show-pharmacy-switch="showPharmacySwitch" />
            <slot />
        </AppContent>
    </AppShell>
</template>
