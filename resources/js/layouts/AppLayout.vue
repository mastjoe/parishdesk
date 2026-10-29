<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PortalLayout from '@/layouts/PortalLayout.vue';
import type { BreadcrumbItem } from '@/types';

/**
 * Wraps pages shared by staff and members, such as personal account
 * settings, in whichever dashboard the user belongs to.
 */
const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

const page = usePage();
const shell = computed(() =>
    page.props.auth.canAccessAdmin ? AdminLayout : PortalLayout,
);
</script>

<template>
    <component :is="shell" :breadcrumbs="breadcrumbs">
        <slot />
    </component>
</template>
