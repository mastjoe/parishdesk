<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
import { toUrl } from '@/lib/utils';
import type { NavGroup } from '@/types';

const props = defineProps<{
    groups: NavGroup[];
}>();

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
const { canAny } = usePermissions();

const visibleGroups = computed(() =>
    props.groups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.permissions || canAny(item.permissions),
            ),
        }))
        .filter((group) => group.items.length > 0),
);

const isActive = (href: NavGroup['items'][number]['href']): boolean =>
    toUrl(href) === '/admin' ? isCurrentUrl(href) : isCurrentOrParentUrl(href);
</script>

<template>
    <div>
        <SidebarGroup
            v-for="group in visibleGroups"
            :key="group.title"
            class="px-2 py-1"
        >
            <SidebarGroupLabel>{{ group.title }}</SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in group.items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :is-active="isActive(item.href)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href" prefetch>
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </div>
</template>
