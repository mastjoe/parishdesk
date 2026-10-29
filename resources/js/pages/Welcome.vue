<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Church } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

const page = usePage();
const parish = computed(() => page.props.parish);
</script>

<template>
    <Head title="Welcome" />

    <div class="flex min-h-screen flex-col bg-background">
        <header
            class="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-5 md:px-6"
        >
            <div class="flex items-center gap-2.5">
                <span
                    class="flex size-9 items-center justify-center overflow-hidden rounded-lg bg-primary text-primary-foreground"
                >
                    <img
                        v-if="parish.logoUrl"
                        :src="parish.logoUrl"
                        :alt="parish.name"
                        class="size-full object-cover"
                    />
                    <Church v-else class="size-5" />
                </span>
                <span class="font-semibold">{{ parish.name }}</span>
            </div>
            <Button as-child variant="outline" size="sm">
                <Link :href="$page.props.auth.user ? dashboard() : login()">
                    {{ $page.props.auth.user ? 'Open dashboard' : 'Sign in' }}
                </Link>
            </Button>
        </header>

        <main
            class="mx-auto flex w-full max-w-6xl flex-1 flex-col justify-center px-4 py-16 md:px-6"
        >
            <div class="relative max-w-2xl">
                <div
                    class="pointer-events-none absolute -top-24 -left-24 size-72 rounded-full bg-primary/10 blur-3xl"
                />
                <p
                    class="relative mb-4 inline-flex rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                >
                    Parish community portal
                </p>
                <h1
                    class="relative text-4xl font-semibold tracking-tight text-balance md:text-5xl"
                >
                    Welcome to {{ parish.name }}
                </h1>
                <p class="relative mt-4 max-w-xl text-lg text-muted-foreground">
                    Parishioners sign in to see announcements, their family and
                    sacrament records, giving, and parish events. Accounts are
                    created by the parish office.
                </p>
                <div class="relative mt-8 flex flex-wrap gap-3">
                    <Button as-child size="lg">
                        <Link
                            :href="
                                $page.props.auth.user ? dashboard() : login()
                            "
                        >
                            {{
                                $page.props.auth.user
                                    ? 'Go to my dashboard'
                                    : 'Sign in'
                            }}
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </main>

        <footer
            class="mx-auto w-full max-w-6xl px-4 py-6 text-xs text-muted-foreground md:px-6"
        >
            © {{ new Date().getFullYear() }} {{ parish.name }} · Powered by
            ParishDesk
        </footer>
    </div>
</template>
