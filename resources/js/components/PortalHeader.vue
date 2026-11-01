<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { usePortalNavigation } from '@/lib/portalNavigation';
import { cn, toUrl } from '@/lib/utils';
import { dashboard } from '@/routes/portal';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { isCurrentOrParentUrl } = useCurrentUrl();
const navigation = usePortalNavigation();
</script>

<template>
    <header
        class="sticky top-0 z-30 border-b border-border/70 bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/70"
    >
        <div
            class="mx-auto flex h-16 w-full max-w-6xl items-center gap-6 px-4 md:px-6"
        >
            <Link :href="dashboard()" class="flex min-w-0 items-center gap-2">
                <AppLogo />
            </Link>

            <nav class="hidden items-center gap-1 md:flex" aria-label="Portal">
                <Link
                    v-for="item in navigation"
                    :key="toUrl(item.href)"
                    :href="item.href"
                    prefetch
                    :class="
                        cn(
                            'rounded-full px-3.5 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground',
                            isCurrentOrParentUrl(item.href) &&
                                'bg-accent text-accent-foreground',
                        )
                    "
                >
                    {{ item.title }}
                </Link>
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="relative size-10 rounded-full p-1"
                            data-test="portal-user-menu"
                        >
                            <Avatar class="size-8 overflow-hidden rounded-full">
                                <AvatarImage
                                    v-if="user.avatar"
                                    :src="user.avatar"
                                    :alt="user.name"
                                />
                                <AvatarFallback
                                    class="rounded-full bg-primary/10 font-semibold text-primary"
                                >
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-60">
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </header>
</template>
