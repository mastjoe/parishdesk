<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { portalNavigation } from '@/lib/portalNavigation';
import { cn, toUrl } from '@/lib/utils';

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-30 border-t border-border/70 bg-background/90 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
        aria-label="Portal"
    >
        <ul class="mx-auto flex max-w-md items-stretch justify-around">
            <li
                v-for="item in portalNavigation"
                :key="toUrl(item.href)"
                class="flex-1"
            >
                <Link
                    :href="item.href"
                    prefetch
                    :class="
                        cn(
                            'flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium text-muted-foreground transition-colors',
                            isCurrentOrParentUrl(item.href) && 'text-primary',
                        )
                    "
                >
                    <component :is="item.icon" class="size-5" />
                    {{ item.title }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
