<script setup lang="ts">
import { useSidebar } from '@/components/ui/sidebar';

const { openMobile, setOpenMobile } = useSidebar();
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-150"
        leave-to-class="opacity-0"
    >
        <div
            v-if="openMobile"
            class="fixed inset-0 z-40 bg-black/50 lg:hidden"
            aria-hidden="true"
            @click="setOpenMobile(false)"
        />
    </Transition>
</template>
