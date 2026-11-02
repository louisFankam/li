<script setup lang="ts">
import { useSidebar } from '@/components/ui/sidebar';
import { pendingCount } from '@/offline/sync';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useOnline } from '@vueuse/core';
import {
    AlertTriangle,
    ArrowLeftRight,
    BookOpen,
    Building2,
    ClipboardCheck,
    FileBarChart,
    LayoutDashboard,
    LogOut,
    Package,
    ShieldCheck,
    Tags,
    Truck,
    Users,
    Wallet,
    Zap,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type NavItem = {
    label: string;
    icon: unknown;
    href: string;
    badge?: string;
    permission?: string;
};

type NavEntry = { section: string } | NavItem;

const nav: NavEntry[] = [
    { section: 'Pilotage' },
    { label: 'Tableau de bord', icon: LayoutDashboard, href: '/dashboard', permission: 'view_dashboard' },
    { label: 'Vente rapide', icon: Zap, href: '/vente', badge: 'F2', permission: 'make_sales' },
    { label: 'Multi-pharmacies', icon: Building2, href: '/pharmacies', permission: 'manage_pharmacies' },
    { section: 'Exploitation' },
    { label: 'Produits & Stocks', icon: Package, href: '/stocks', permission: 'manage_stock' },
    { label: 'Catégories', icon: Tags, href: '/categories', permission: 'manage_products' },
    { label: 'Clients', icon: Users, href: '/customers', permission: 'make_sales' },
    { label: 'Fournisseurs', icon: Truck, href: '/suppliers', permission: 'manage_purchases' },
    { label: 'Achats', icon: Truck, href: '/achats', permission: 'manage_purchases' },
    { label: 'Transferts', icon: ArrowLeftRight, href: '/transfers', permission: 'manage_transfers' },
    { label: 'Inventaires', icon: ClipboardCheck, href: '/inventaires', permission: 'manage_inventory' },
    { section: 'Finance & Assurance' },
    { label: 'Assureurs', icon: ShieldCheck, href: '/insurers', permission: 'manage_insurance' },
    { label: 'Assurances', icon: ShieldCheck, href: '/assurances', permission: 'manage_insurance' },
    { label: 'Caisses', icon: Wallet, href: '/caisses', permission: 'manage_cash' },
    { label: 'Rapports', icon: FileBarChart, href: '/rapports', permission: 'view_reports' },
    { section: 'Système' },
    { label: 'Utilisateurs', icon: Users, href: '/users', permission: 'manage_users' },
    { label: 'Administration', icon: Building2, href: '/administration', permission: 'manage_pharmacies' },
    { label: 'Audit', icon: BookOpen, href: '/audit', permission: 'manage_audit' },
    { label: 'Conflits sync', icon: AlertTriangle, href: '/sync/conflicts', permission: 'manage_audit' },
];

const page = usePage();
const auth = computed(() => (page.props as any).auth?.user ?? null);
const permissions = computed<string[]>(() => (page.props as any).auth?.permissions ?? []);
const pharmacies = computed<any[]>(() => (page.props as any).pharmacies ?? []);
const activePharmacyId = computed(() => (page.props as any).activePharmacyId ?? null);

const activePharmacy = computed(() => pharmacies.value.find((p) => p.id === activePharmacyId.value) ?? null);

const isSuperAdmin = computed(() => Boolean(auth.value?.is_super_admin));

function can(permission?: string): boolean {
    if (!permission) return true;
    if (isSuperAdmin.value) return true;
    return permissions.value.includes(permission);
}

const visibleNav = computed<NavEntry[]>(() => nav.filter((item) => !('permission' in item) || can(item.permission)));

const userInitials = computed(() => {
    const name = auth.value?.name ?? '';
    return name
        .split(' ')
        .filter(Boolean)
        .map((p: string) => p[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
});

const { isMobile, openMobile, setOpenMobile } = useSidebar();

const isOnline = useOnline();
const pending = ref(0);
let timer: ReturnType<typeof setInterval> | undefined;

async function refreshPending() {
    try {
        pending.value = await pendingCount();
    } catch {
        pending.value = 0;
    }
}

onMounted(() => {
    refreshPending();
    timer = setInterval(refreshPending, 15000);
});

onBeforeUnmount(() => {
    if (timer) clearInterval(timer);
});

function logout() {
    router.post(route('logout'));
}

function isActive(href: string): boolean {
    return page.url === href || page.url.startsWith(href + '/') || page.url.startsWith(href + '?');
}

// Referme le tiroir mobile après une navigation.
watch(
    () => page.url,
    () => {
        if (isMobile.value) setOpenMobile(false);
    },
);
</script>

<template>
    <aside
        class="no-print fixed inset-y-0 left-0 z-50 flex h-screen w-[264px] shrink-0 flex-col self-start overflow-hidden bg-dark text-dark-foreground transition-transform duration-300 lg:sticky lg:top-0 lg:z-auto lg:translate-x-0"
        :class="isMobile && !openMobile ? '-translate-x-full' : 'translate-x-0'"
        aria-label="Navigation principale"
    >
        <div class="flex items-center gap-3 px-5 pb-5 pt-6">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary">
                <svg viewBox="0 0 24 24" class="h-5 w-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M12 5v14M5 12h14" />
                </svg>
            </div>
            <div>
                <div class="font-headings text-lg font-bold leading-none text-white">PharmaGest</div>
                <div class="mt-1 text-xs opacity-70">Pharmacie · Guinée</div>
            </div>
        </div>

        <div class="mx-5 mb-4 flex items-center gap-3 rounded-md border border-white/10 bg-white/10 p-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-md bg-primary text-sm font-bold text-white">
                {{ activePharmacy?.short?.slice(0, 3) ?? 'ALL' }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-medium text-white">
                    {{ activePharmacy?.name ?? 'Toutes les pharmacies' }}
                </div>
                <div class="text-xs opacity-60">{{ activePharmacy?.city ?? 'Vue consolidée' }}</div>
            </div>
        </div>

        <nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto px-3 pb-4">
            <template v-for="(item, idx) in visibleNav" :key="idx">
                <div v-if="'section' in item" class="px-3 pb-1.5 pt-4 text-xs font-medium uppercase tracking-wider opacity-50">
                    {{ item.section }}
                </div>
                <Link
                    v-else
                    :href="item.href"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                    :class="isActive(item.href) ? 'bg-primary text-white' : 'text-dark-foreground/80 hover:bg-white/5 hover:text-white'"
                >
                    <component :is="item.icon" class="h-[18px] w-[18px] shrink-0" />
                    <span class="flex-1">{{ item.label }}</span>
                    <span
                        v-if="item.badge"
                        class="rounded-sm px-1.5 py-0.5 text-xs font-bold"
                        :class="isActive(item.href) ? 'bg-white text-dark' : 'bg-white/15 text-white'"
                    >
                        {{ item.badge }}
                    </span>
                </Link>
            </template>
        </nav>

        <div class="p-4">
            <div class="rounded-lg border border-white/10 bg-white/[0.08] p-3.5">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full" :class="isOnline ? 'bg-primary' : 'bg-danger'" />
                    <span class="text-sm font-medium text-white">{{ isOnline ? 'En ligne' : 'Hors ligne' }}</span>
                </div>
                <div class="mt-1.5 text-xs leading-relaxed opacity-60">{{ pending }} opération(s) en attente de synchronisation.</div>
            </div>
            <div class="mt-4 flex items-center gap-2.5 px-1">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-secondary text-sm font-bold text-secondary-foreground">
                    {{ userInitials || '?' }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium text-white">{{ auth?.name ?? 'Utilisateur' }}</div>
                    <div class="truncate text-xs capitalize opacity-60">{{ auth?.role ?? '—' }}</div>
                </div>
                <button
                    type="button"
                    class="rounded-md p-1.5 text-white/60 transition-colors hover:bg-white/10 hover:text-white"
                    title="Se déconnecter"
                    aria-label="Se déconnecter"
                    @click="logout"
                >
                    <LogOut class="h-4 w-4" />
                </button>
            </div>
        </div>
    </aside>
</template>
