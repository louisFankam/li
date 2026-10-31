<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, Info, X, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type ToastKind = 'success' | 'error' | 'info';
type Toast = { id: number; kind: ToastKind; message: string };

const page = usePage();
const toasts = ref<Toast[]>([]);
let nextId = 1;

function push(kind: ToastKind, message: string) {
    if (!message) return;
    const id = nextId++;
    toasts.value.push({ id, kind, message });
    setTimeout(() => dismiss(id), kind === 'error' ? 8000 : 5000);
}

function dismiss(id: number) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

const flash = computed(() => (page.props as any).flash ?? {});
const errors = computed<Record<string, string>>(() => (page.props as any).errors ?? {});

watch(
    () => flash.value.success,
    (message) => message && push('success', String(message)),
    { immediate: true },
);
watch(
    () => flash.value.error,
    (message) => message && push('error', String(message)),
    { immediate: true },
);
watch(
    () => errors.value,
    (value) => {
        const first = Object.values(value ?? {})[0];
        if (first) push('error', String(first));
    },
    { immediate: true, deep: true },
);

const styles: Record<ToastKind, string> = {
    success: 'border-success/40 bg-success-soft text-accent',
    error: 'border-danger/40 bg-danger-soft text-danger',
    info: 'border-info/40 bg-info-soft text-info',
};
</script>

<template>
    <div class="pointer-events-none fixed bottom-6 right-6 z-[100] flex w-full max-w-sm flex-col gap-2" aria-live="polite">
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-y-2 opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto flex items-start gap-3 rounded-md border px-4 py-3 shadow-lg"
                :class="styles[toast.kind]"
                role="status"
            >
                <CheckCircle2 v-if="toast.kind === 'success'" class="mt-0.5 h-4 w-4 shrink-0" />
                <XCircle v-else-if="toast.kind === 'error'" class="mt-0.5 h-4 w-4 shrink-0" />
                <Info v-else class="mt-0.5 h-4 w-4 shrink-0" />
                <p class="flex-1 text-sm font-medium">{{ toast.message }}</p>
                <button
                    type="button"
                    class="rounded p-0.5 opacity-70 transition-opacity hover:opacity-100"
                    aria-label="Fermer"
                    @click="dismiss(toast.id)"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
