<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

defineProps<{
    title?: string;
    subtitle?: string;
    showPharmacySwitch?: boolean;
}>();

type SharedProps = {
    pharmacies?: { id: number; name: string; short: string; city: string | null }[];
    activePharmacyId?: number | null;
};

const page = usePage<SharedProps>();
const pharmacies = computed(() => page.props.pharmacies ?? []);
const activePharmacyId = computed(() => page.props.activePharmacyId ?? null);

const syncedAt = ref('--:--');
onMounted(() => {
    syncedAt.value = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
});

function activate(pharmacyId: number | null) {
    if (pharmacyId === activePharmacyId.value) return;
    router.post(route('pharmacies.activate'), { pharmacy_id: pharmacyId }, { preserveScroll: true });
}
</script>

<template>
    <div class="flex items-center gap-4 border-b border-border bg-card px-8 py-4">
        <div class="min-w-0 flex-1">
            <h1 class="truncate font-headings text-2xl font-bold leading-tight text-foreground">
                {{ title || 'Tableau de bord' }}
            </h1>
            <p v-if="subtitle" class="mt-0.5 truncate text-sm text-muted-foreground">{{ subtitle }}</p>
        </div>

        <div
            v-if="showPharmacySwitch && pharmacies.length"
            class="flex items-center gap-1 rounded-md border border-border bg-input px-1 py-1"
        >
            <button
                type="button"
                class="rounded-sm px-3 py-1.5 text-sm font-medium"
                :class="activePharmacyId === null ? 'bg-dark text-white' : 'text-muted-foreground hover:text-foreground'"
                @click="activate(null)"
            >
                Toutes
            </button>
            <button
                v-for="p in pharmacies"
                :key="p.id"
                type="button"
                class="rounded-sm px-3 py-1.5 text-sm font-medium"
                :class="
                    activePharmacyId === p.id ? 'bg-dark text-white' : 'text-muted-foreground hover:text-foreground'
                "
                @click="activate(p.id)"
            >
                {{ p.short }}
            </button>
        </div>

        <div class="flex items-center gap-2 rounded-md border border-primary/20 bg-success-soft px-3 py-2">
            <span class="h-2.5 w-2.5 rounded-full bg-primary" />
            <span class="text-sm font-medium text-accent">En ligne</span>
            <span class="text-xs text-accent/70">· Synchro {{ syncedAt }}</span>
        </div>

        <Link
            href="/vente"
            class="flex items-center gap-2 rounded-md bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground"
        >
            <Icon name="plus" :size="16" /> Nouvelle vente
        </Link>
        <button
            type="button"
            class="flex h-10 w-10 items-center justify-center rounded-md border border-border bg-card text-muted-foreground"
        >
            <Icon name="bell" :size="18" />
        </button>
    </div>
</template>
