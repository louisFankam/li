<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { useSidebar } from '@/components/ui/sidebar';
import { db } from '@/offline/db';
import { pendingCount } from '@/offline/sync';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useOnline } from '@vueuse/core';
import { Menu } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

defineProps<{
    title?: string;
    subtitle?: string;
    showPharmacySwitch?: boolean;
}>();

type SharedProps = {
    pharmacies?: { id: number; name: string; short: string; city: string | null }[];
    activePharmacyId?: number | null;
    auth?: { permissions?: string[]; user?: { is_super_admin?: boolean } | null };
};

const page = usePage<SharedProps>();
const pharmacies = computed(() => page.props.pharmacies ?? []);
const activePharmacyId = computed(() => page.props.activePharmacyId ?? null);
const canSell = computed(() => {
    if (page.props.auth?.user?.is_super_admin) return true;
    return (page.props.auth?.permissions ?? []).includes('make_sales');
});

const { toggleSidebar } = useSidebar();
const isOnline = useOnline();
const pending = ref(0);
const lastSync = ref<string | null>(null);
let timer: ReturnType<typeof setInterval> | undefined;

async function refreshSync() {
    try {
        pending.value = await pendingCount();
        const meta = await db.meta.get('last_sync');
        lastSync.value = typeof meta?.value === 'string' ? meta.value : null;
    } catch {
        pending.value = 0;
    }
}

const syncLabel = computed(() => {
    if (!lastSync.value) return 'Jamais synchronisé';
    return 'Synchro ' + new Date(lastSync.value).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
});

onMounted(() => {
    refreshSync();
    timer = setInterval(refreshSync, 15000);
});

onBeforeUnmount(() => {
    if (timer) clearInterval(timer);
});

function activate(pharmacyId: number) {
    if (pharmacyId === activePharmacyId.value) return;
    router.post(route('pharmacies.activate'), { pharmacy_id: pharmacyId }, { preserveScroll: true });
}
</script>

<template>
    <div class="no-print flex flex-wrap items-center gap-3 border-b border-border bg-card px-4 py-3 lg:px-8 lg:py-4">
        <button
            type="button"
            class="flex h-10 w-10 items-center justify-center rounded-md border border-border text-muted-foreground lg:hidden"
            aria-label="Ouvrir le menu"
            @click="toggleSidebar"
        >
            <Menu class="h-5 w-5" />
        </button>

        <div class="min-w-0 flex-1">
            <h1 class="truncate font-headings text-xl font-bold leading-tight text-foreground lg:text-2xl">
                {{ title || 'Tableau de bord' }}
            </h1>
            <p v-if="subtitle" class="mt-0.5 truncate text-sm text-muted-foreground">{{ subtitle }}</p>
        </div>

        <div
            v-if="showPharmacySwitch && pharmacies.length > 1"
            class="flex flex-wrap items-center gap-1 rounded-md border border-border bg-input px-1 py-1"
        >
            <button
                v-for="p in pharmacies"
                :key="p.id"
                type="button"
                class="rounded-sm px-3 py-1.5 text-sm font-medium"
                :class="activePharmacyId === p.id ? 'bg-dark text-white' : 'text-muted-foreground hover:text-foreground'"
                @click="activate(p.id)"
            >
                {{ p.short }}
            </button>
        </div>

        <div
            class="flex items-center gap-2 rounded-md border px-3 py-2"
            :class="isOnline ? 'border-primary/20 bg-success-soft' : 'border-danger/30 bg-danger-soft'"
            :title="syncLabel"
        >
            <span class="h-2.5 w-2.5 rounded-full" :class="isOnline ? 'bg-primary' : 'bg-danger'" />
            <span class="text-sm font-medium" :class="isOnline ? 'text-accent' : 'text-danger'">
                {{ isOnline ? 'En ligne' : 'Hors ligne' }}
            </span>
            <span class="hidden text-xs text-muted-foreground sm:inline"> · {{ pending }} en attente · {{ syncLabel }} </span>
        </div>

        <Link
            v-if="canSell"
            href="/vente"
            class="flex items-center gap-2 rounded-md bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground"
        >
            <Icon name="plus" :size="16" /> Nouvelle vente
        </Link>
        <button
            type="button"
            class="flex h-10 w-10 items-center justify-center rounded-md border border-border bg-card text-muted-foreground"
            aria-label="Notifications"
        >
            <Icon name="bell" :size="18" />
        </button>
    </div>
</template>
