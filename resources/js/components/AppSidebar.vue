<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Bell,
    Building2,
    ClipboardCheck,
    FileBarChart,
    LayoutDashboard,
    LogOut,
    Package,
    RefreshCw,
    ShieldCheck,
    Truck,
    Users,
    Wallet,
    Zap,
} from 'lucide-vue-next';

const nav = [
    { section: 'Pilotage' },
    { label: 'Tableau de bord', icon: LayoutDashboard, href: '/dashboard', active: 'dashboard' },
    { label: 'Vente rapide', icon: Zap, href: '/vente', active: 'vente', badge: 'F2' },
    { label: 'Multi-pharmacies', icon: Building2, href: '/pharmacies', active: 'pharmacies' },
    { section: 'Exploitation' },
    { label: 'Produits & Stocks', icon: Package, href: '/stocks', active: 'stocks' },
    { label: 'Achats & Fournisseurs', icon: Truck, href: '/suppliers', active: 'achats' },
    { label: 'Transferts', icon: ArrowLeftRight, href: '/transfers', active: 'transferts', badge: '3' },
    { label: 'Inventaires', icon: ClipboardCheck, href: '/stocks', active: 'inventaires' },
    { section: 'Finance & Assurance' },
    { label: 'Assurances', icon: ShieldCheck, href: '/assurances', active: 'assurances' },
    { label: 'Caisses & Dépenses', icon: Wallet, href: '/caisses', active: 'caisses' },
    { label: 'Rapports', icon: FileBarChart, href: '/dashboard', active: 'rapports' },
    { section: 'Système' },
    { label: 'Utilisateurs & Rôles', icon: Users, href: '/users', active: 'admin' },
    { label: 'Synchronisation', icon: RefreshCw, href: '/dashboard', active: 'sync' },
];

const page = usePage();
</script>

<template>
    <div class="w-[264px] shrink-0 bg-dark text-dark-foreground flex flex-col min-h-full">
        <div class="px-5 pt-6 pb-5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-primary flex items-center justify-center">
                <svg viewBox="0 0 24 24" class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14" /></svg>
            </div>
            <div>
                <div class="font-headings font-bold text-lg leading-none text-white">PharmaGest</div>
                <div class="text-xs opacity-70 mt-1">Central · Conakry</div>
            </div>
        </div>

        <div class="mx-5 mb-4 bg-white/10 rounded-md p-3 flex items-center gap-3 border border-white/10">
            <div class="w-9 h-9 rounded-md bg-primary flex items-center justify-center text-white font-bold text-sm">KM5</div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-white truncate">Pharmacie KM5</div>
                <div class="text-xs opacity-60">Kaloum · Ouverte</div>
            </div>
            <svg class="w-4 h-4 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 10l5 5 5-5" /></svg>
        </div>

        <div class="flex-1 px-3 pb-4 flex flex-col gap-0.5">
            <template v-for="(item, idx) in nav" :key="idx">
                <div v-if="item.section" class="px-3 pt-4 pb-1.5 text-xs font-medium uppercase tracking-wider opacity-50">
                    {{ item.section }}
                </div>
                <Link v-else :href="item.href" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium" :class="page.url === item.href || page.url.startsWith(item.href + '/') ? 'bg-primary text-white' : 'text-dark-foreground/80 hover:bg-white/5'">
                    <component :is="item.icon" class="w-[18px] h-[18px]" />
                    <span class="flex-1">{{ item.label }}</span>
                    <span v-if="item.badge" class="text-xs px-1.5 py-0.5 rounded-sm font-bold" :class="page.url === item.href || page.url.startsWith(item.href + '/') ? 'bg-white text-dark' : 'bg-white/15 text-white'">{{ item.badge }}</span>
                </Link>
            </template>
        </div>

        <div class="p-4">
            <div class="rounded-lg bg-white/8 border border-white/10 p-3.5">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-primary" />
                    <span class="text-sm font-medium text-white">Hors ligne — actif</span>
                </div>
                <div class="text-xs opacity-60 mt-1.5 leading-relaxed">Serveur local KM5 relaye les ventes. 128 tickets en attente de synchro.</div>
                <div class="mt-2.5 h-1.5 rounded-full bg-white/15 overflow-hidden">
                    <div class="h-full w-[68%] bg-primary rounded-full" />
                </div>
                <button class="mt-3 w-full bg-primary text-white text-sm font-medium rounded-md py-2 flex items-center justify-center gap-2">
                    <RefreshCw class="w-3.5 h-3.5" /> Synchroniser
                </button>
            </div>
            <div class="flex items-center gap-2.5 mt-4 px-1">
                <div class="w-8 h-8 rounded-full bg-secondary text-secondary-foreground flex items-center justify-center text-sm font-bold">AD</div>
                <div class="flex-1">
                    <div class="text-sm font-medium text-white">Aïcha Diallo</div>
                    <div class="text-xs opacity-60">Caissière · Shift A</div>
                </div>
                <LogOut class="w-4 h-4 opacity-60" />
            </div>
        </div>
    </div>
</template>
