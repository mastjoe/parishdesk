<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Home, LayoutDashboard, LogOut, Settings } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { logout } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { dashboard as portalDashboard } from '@/routes/portal';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();

const page = usePage();
const { currentUrl } = useCurrentUrl();
const canAccessAdmin = computed(() => page.props.auth.canAccessAdmin);
const inAdmin = computed(() => currentUrl.value.startsWith('/admin'));
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem v-if="canAccessAdmin && inAdmin" :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="portalDashboard()"
                data-test="switch-to-portal"
            >
                <Home class="mr-2 h-4 w-4" />
                My portal
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem v-if="canAccessAdmin && !inAdmin" :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="adminDashboard()"
                data-test="switch-to-admin"
            >
                <LayoutDashboard class="mr-2 h-4 w-4" />
                Admin dashboard
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
