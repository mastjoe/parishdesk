<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
import { adminNavigation } from '@/lib/adminNavigation';
import { cn, toUrl } from '@/lib/utils';

const { isCurrentOrParentUrl } = useCurrentUrl();
const { canAny } = usePermissions();

const items = computed(
    () =>
        adminNavigation
            .find((group) => group.title === 'Settings')
            ?.items.filter(
                (item) => !item.permissions || canAny(item.permissions),
            ) ?? [],
);
</script>

<template>
    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Parish settings"
            description="Profile, branding and defaults used across ParishDesk."
        />

        <div class="flex flex-col gap-6 lg:flex-row lg:gap-10">
            <nav
                class="flex gap-1 overflow-x-auto lg:w-52 lg:shrink-0 lg:flex-col"
                aria-label="Settings sections"
            >
                <Link
                    v-for="item in items"
                    :key="toUrl(item.href)"
                    :href="item.href"
                    prefetch
                    :class="
                        cn(
                            'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground',
                            isCurrentOrParentUrl(item.href) &&
                                'bg-accent text-accent-foreground',
                        )
                    "
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.title }}
                </Link>
            </nav>

            <div class="min-w-0 flex-1 lg:max-w-2xl">
                <slot />
            </div>
        </div>
    </div>
</template>
