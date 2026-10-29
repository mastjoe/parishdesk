<script setup lang="ts">
import PortalHeader from '@/components/PortalHeader.vue';
import PortalTabBar from '@/components/PortalTabBar.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

/**
 * The member portal shell: a light top header on desktop and a bottom tab
 * bar on phones, sharing the admin dashboard's tokens and components.
 */
defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-muted/30">
        <PortalHeader />
        <main
            class="mx-auto w-full max-w-6xl flex-1 px-4 pt-6 pb-24 md:px-6 md:pt-8 md:pb-12"
        >
            <slot />
        </main>
        <PortalTabBar />
        <Toaster />
    </div>
</template>
