<script setup lang="ts">
import FlashToasts from '@/components/FlashToasts.vue';
import MobileSidebarOverlay from '@/components/MobileSidebarOverlay.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { resetSyncSession, startSyncLoop } from '@/offline/sync';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

interface Props {
    variant?: 'header' | 'sidebar';
}

defineProps<Props>();

const page = usePage<{
    auth?: { user?: { id: number } | null };
    activePharmacyId?: number | null;
}>();
const userId = computed(() => page.props.auth?.user?.id ?? null);
const activePharmacyId = computed(() => page.props.activePharmacyId ?? null);

const isOpen = ref(true);

watch(
    [userId, activePharmacyId],
    ([currentUserId, pharmacyId]) => {
        if (currentUserId === null) {
            resetSyncSession();
            return;
        }

        void startSyncLoop(currentUserId, pharmacyId).catch(() => {
            // L'ouverture IndexedDB peut échouer (navigation privée).
            // L'interface reste utilisable et retentera au prochain montage.
        });
    },
    { immediate: true },
);

onMounted(() => {
    isOpen.value = localStorage.getItem('sidebar') !== 'false';
});

onUnmounted(() => {
    resetSyncSession();
});

const handleSidebarChange = (open: boolean) => {
    isOpen.value = open;
    localStorage.setItem('sidebar', String(open));
};
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
        <FlashToasts />
    </div>
    <SidebarProvider v-else :default-open="isOpen" :open="isOpen" @update:open="handleSidebarChange">
        <slot />
        <MobileSidebarOverlay />
        <FlashToasts />
    </SidebarProvider>
</template>
