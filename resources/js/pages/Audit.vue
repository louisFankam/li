<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Filter, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Audit', href: '/audit' }];

type AuditLogRow = {
    id: number;
    action: string;
    subject: string;
    user: string;
    pharmacy: string;
    ip: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    date: string;
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    logs: Paginator<AuditLogRow>;
    filters: { action: string; user_id: number | null; pharmacy_id: number | null; from: string; to: string };
    users: { id: number; name: string }[];
    pharmacies: { id: number; name: string }[];
}>();

const form = reactive({
    action: props.filters.action ?? '',
    user_id: props.filters.user_id ?? null,
    pharmacy_id: props.filters.pharmacy_id ?? null,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const expanded = ref<number | null>(null);

const hasFilters = computed(
    () => Boolean(form.action || form.user_id || form.pharmacy_id),
);

function apply() {
    router.get(
        '/audit',
        {
            action: form.action || undefined,
            user_id: form.user_id || undefined,
            pharmacy_id: form.pharmacy_id || undefined,
            from: form.from || undefined,
            to: form.to || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function reset() {
    form.action = '';
    form.user_id = null;
    form.pharmacy_id = null;
    apply();
}

function go(url: string | null) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
}

function toggle(id: number) {
    expanded.value = expanded.value === id ? null : id;
}

function formatValues(values: Record<string, unknown> | null): string {
    if (!values || Object.keys(values).length === 0) return '—';
    return JSON.stringify(values, null, 2);
}
</script>

<template>
    <Head title="Audit" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 lg:p-8">
            <div class="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-card p-4">
                <div class="flex flex-col gap-1">
                    <label for="audit-action" class="text-xs font-medium text-muted-foreground">Action</label>
                    <input
                        id="audit-action"
                        v-model="form.action"
                        type="text"
                        placeholder="sale., stock.…"
                        class="w-40 rounded-md border border-border bg-input px-3 py-2 text-sm"
                        @keyup.enter="apply"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label for="audit-user" class="text-xs font-medium text-muted-foreground">Utilisateur</label>
                    <select
                        id="audit-user"
                        v-model="form.user_id"
                        class="w-48 rounded-md border border-border bg-input px-3 py-2 text-sm"
                    >
                        <option :value="null">Tous</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label for="audit-pharmacy" class="text-xs font-medium text-muted-foreground">Pharmacie</label>
                    <select
                        id="audit-pharmacy"
                        v-model="form.pharmacy_id"
                        class="w-48 rounded-md border border-border bg-input px-3 py-2 text-sm"
                    >
                        <option :value="null">Toutes</option>
                        <option v-for="p in pharmacies" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label for="audit-from" class="text-xs font-medium text-muted-foreground">Du</label>
                    <input id="audit-from" v-model="form.from" type="date" class="rounded-md border border-border bg-input px-3 py-2 text-sm" />
                </div>
                <div class="flex flex-col gap-1">
                    <label for="audit-to" class="text-xs font-medium text-muted-foreground">Au</label>
                    <input id="audit-to" v-model="form.to" type="date" class="rounded-md border border-border bg-input px-3 py-2 text-sm" />
                </div>
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white"
                    @click="apply"
                >
                    <Filter class="h-4 w-4" /> Filtrer
                </button>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="flex items-center gap-2 rounded-md border border-border px-4 py-2 text-sm font-medium text-muted-foreground"
                    @click="reset"
                >
                    <X class="h-4 w-4" /> Réinitialiser
                </button>
            </div>

            <div class="overflow-hidden rounded-lg border border-border bg-card">
                <div class="hidden grid-cols-6 gap-2 bg-muted/60 px-4 py-2.5 text-xs font-medium text-muted-foreground md:grid">
                    <span>Date</span><span>Action</span><span>Sujet</span><span>Utilisateur</span><span>Pharmacie</span><span>IP</span>
                </div>

                <div v-if="logs.data.length === 0" class="px-4 py-10 text-center text-sm text-muted-foreground">
                    Aucun événement sur cette période.
                </div>

                <template v-for="l in logs.data" :key="l.id">
                    <button
                        type="button"
                        class="grid w-full grid-cols-1 gap-1 border-t border-border px-4 py-3 text-left text-sm hover:bg-muted/30 md:grid-cols-6 md:gap-2"
                        @click="toggle(l.id)"
                    >
                        <span class="text-muted-foreground">{{ l.date }}</span>
                        <span class="font-medium">{{ l.action }}</span>
                        <span class="truncate">{{ l.subject }}</span>
                        <span>{{ l.user }}</span>
                        <span>{{ l.pharmacy }}</span>
                        <span class="text-muted-foreground">{{ l.ip ?? '—' }}</span>
                    </button>
                    <div v-if="expanded === l.id" class="grid gap-3 border-t border-border bg-muted/20 px-4 py-3 text-xs md:grid-cols-2">
                        <div>
                            <div class="mb-1 font-medium text-muted-foreground">Avant</div>
                            <pre class="overflow-auto rounded bg-card p-2">{{ formatValues(l.old_values) }}</pre>
                        </div>
                        <div>
                            <div class="mb-1 font-medium text-muted-foreground">Après</div>
                            <pre class="overflow-auto rounded bg-card p-2">{{ formatValues(l.new_values) }}</pre>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                <span>
                    {{ logs.total }} événement(s)
                    <span v-if="logs.from"> · {{ logs.from }}–{{ logs.to }}</span>
                </span>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded-md border border-border px-3 py-1.5 disabled:opacity-40"
                        :disabled="!logs.prev_page_url"
                        @click="go(logs.prev_page_url)"
                    >
                        <ChevronLeft class="h-4 w-4" /> Précédent
                    </button>
                    <span>Page {{ logs.current_page }} / {{ logs.last_page }}</span>
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded-md border border-border px-3 py-1.5 disabled:opacity-40"
                        :disabled="!logs.next_page_url"
                        @click="go(logs.next_page_url)"
                    >
                        Suivant <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
