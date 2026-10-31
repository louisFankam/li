<script setup lang="ts">
import FlashToasts from '@/components/FlashToasts.vue';
import MobileSidebarOverlay from '@/components/MobileSidebarOverlay.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { startSyncLoop, stopSyncLoop } from '@/offline/sync';
import { usePage, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface Props {
    variant?: 'header' | 'sidebar';
}

defineProps<Props>();

const page = usePage<{ auth?: { user?: { id: number } | null }; activePharmacyId?: number | null }>();
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));

const isOpen = ref(true);

let idleUnsubscribe: (() => void) | null = null;

const start = () => {
    startSyncLoop(page.props.activePharmacyId ?? null);
};

const handleIdle = () => {
    if (isAuthenticated.value) {
        // Remet le minuteur à zéro une fois la page stabilisée : le jeton
        // CSRF partagé est fiable seulement après ce point.
        stopSyncLoop();
        start();
    }
};

onMounted(() => {
    isOpen.value = localStorage.getItem('sidebar') !== 'false';

    if (isAuthenticated.value) {
        start();
    }

    // La boucle part uniquement une fois la page Inertia stabilisée :
    // le jeton CSRF partagé peut encore être celui de la page de connexion
    // pendant la transition client-side.
    idleUnsubscribe = router.on('idle', handleIdle);
});

onUnmounted(() => {
    idleUnsubscribe?.();
    idleUnsubscribe = null;

    // Le layout peut être démonté à la déconnexion : on arrête la boucle
    // pour ne pas interroger le serveur avec un jeton d'appareil révoqué.
    if (!isAuthenticated.value) {
        stopSyncLoop();
    }
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
