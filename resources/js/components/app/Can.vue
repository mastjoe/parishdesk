<script setup lang="ts">
import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps<{
    /** Render when the user holds this permission. */
    permission?: string;
    /** Render when the user holds any of these permissions. */
    any?: string[];
}>();

const { can, canAny } = usePermissions();

const allowed = computed(() => {
    if (props.permission && !can(props.permission)) {
        return false;
    }

    return props.any ? canAny(props.any) : true;
});
</script>

<template>
    <slot v-if="allowed" />
</template>
