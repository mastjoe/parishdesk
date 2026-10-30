<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Cake, Heart } from '@lucide/vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { formatPlainDate, inDays } from '@/lib/people';
import { celebrations, index, show } from '@/routes/admin/members';
import type { Celebration } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Members', href: index() },
            { title: 'Celebrations', href: celebrations() },
        ],
    },
});

defineProps<{
    days: number;
    ranges: number[];
    birthdays: Celebration[];
    anniversaries: Celebration[];
}>();

const choose = (days: number) =>
    router.get(celebrations().url, { days }, { preserveScroll: true });

const ordinal = (value: number) => {
    const suffix =
        value % 100 >= 11 && value % 100 <= 13
            ? 'th'
            : (({ 1: 'st', 2: 'nd', 3: 'rd' } as Record<number, string>)[
                  value % 10
              ] ?? 'th');

    return `${value}${suffix}`;
};
</script>

<template>
    <Head title="Celebrations" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Celebrations"
            description="Birthdays and wedding anniversaries coming up in the parish."
        >
            <template #actions>
                <div class="inline-flex rounded-lg border bg-card p-0.5">
                    <Button
                        v-for="range in ranges"
                        :key="range"
                        size="sm"
                        :variant="range === days ? 'default' : 'ghost'"
                        @click="choose(range)"
                    >
                        {{ range }} days
                    </Button>
                </div>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <section
                v-for="list in [
                    {
                        title: 'Birthdays',
                        icon: Cake,
                        items: birthdays,
                        label: (item: Celebration) => `Turns ${item.years}`,
                    },
                    {
                        title: 'Wedding anniversaries',
                        icon: Heart,
                        items: anniversaries,
                        label: (item: Celebration) =>
                            `${ordinal(item.years)} anniversary`,
                    },
                ]"
                :key="list.title"
                class="flex flex-col rounded-xl border bg-card"
            >
                <header class="flex items-center gap-2 border-b px-5 py-4">
                    <component :is="list.icon" class="size-4 text-primary" />
                    <h2 class="font-semibold">{{ list.title }}</h2>
                    <span class="ml-auto text-sm text-muted-foreground">{{
                        list.items.length
                    }}</span>
                </header>
                <ul v-if="list.items.length" class="divide-y">
                    <li v-for="item in list.items" :key="item.id">
                        <Link
                            :href="show(item.id)"
                            class="flex items-center gap-3 px-5 py-3 hover:bg-muted/40"
                        >
                            <div
                                class="flex w-12 shrink-0 flex-col items-center rounded-lg border py-1 text-center"
                            >
                                <span
                                    class="text-[10px] font-medium text-primary uppercase"
                                    >{{
                                        formatPlainDate(item.date, {
                                            month: 'short',
                                        })
                                    }}</span
                                >
                                <span
                                    class="text-lg leading-none font-semibold tabular-nums"
                                    >{{
                                        formatPlainDate(item.date, {
                                            day: 'numeric',
                                        })
                                    }}</span
                                >
                            </div>
                            <MemberAvatar
                                :name="item.name"
                                :photo-url="item.photoUrl"
                            />
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-sm font-medium"
                                    >{{ item.name }}</span
                                >
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{ list.label(item) }}
                                    <template v-if="item.phone">
                                        · {{ item.phone }}</template
                                    ></span
                                >
                            </span>
                            <span
                                :class="[
                                    'text-xs whitespace-nowrap',
                                    item.inDays === 0
                                        ? 'font-semibold text-primary'
                                        : 'text-muted-foreground',
                                ]"
                                >{{ inDays(item.inDays) }}</span
                            >
                        </Link>
                    </li>
                </ul>
                <EmptyState
                    v-else
                    :icon="list.icon"
                    :title="`No ${list.title.toLowerCase()} in the next ${days} days`"
                    class="m-5"
                />
            </section>
        </div>
    </div>
</template>
