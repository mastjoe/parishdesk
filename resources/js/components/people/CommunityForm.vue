<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Church, House, UserRound, UsersRound } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import OptionSelect from '@/components/app/OptionSelect.vue';
import RecordPicker from '@/components/app/RecordPicker.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { optionLabel } from '@/lib/people';
import { index, store, update } from '@/routes/admin/communities';
import { members as lookupMembers } from '@/routes/admin/lookup';
import type { MemberSummary, PeopleOptions } from '@/types';

export type CommunityFields = {
    id: number;
    name: string;
    type: string;
    description: string | null;
    leader: MemberSummary | null;
    membersCount: number;
    familiesCount: number;
};

const props = defineProps<{
    /** The community being edited; omit to create one. */
    community?: CommunityFields;
    options: PeopleOptions;
}>();

const form = useForm({
    name: props.community?.name ?? '',
    type: props.community?.type ?? ('scc' as string | null),
    description: props.community?.description ?? '',
    leader_member_id: props.community?.leader?.id ?? (null as number | null),
});

// The chosen leader, kept for their name and photo in the preview.
const leader = ref<MemberSummary | null>(props.community?.leader ?? null);

const typeTone: Record<string, string> = {
    scc: 'primary',
    station: 'blue',
    outstation: 'teal',
    zone: 'slate',
};

const submit = () => {
    if (props.community) {
        form.submit(update(props.community.id));
    } else {
        form.submit(store());
    }
};
</script>

<template>
    <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="submit">
        <div class="flex flex-col gap-6 lg:col-span-2">
            <section class="rounded-xl border bg-card">
                <header class="border-b px-5 py-4">
                    <h2 class="font-semibold">Community</h2>
                    <p class="text-sm text-muted-foreground">
                        Its name and the kind of grouping it is in the parish.
                    </p>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-3">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="community-name">Name</Label>
                        <Input
                            id="community-name"
                            v-model="form.name"
                            placeholder="St. Jude SCC"
                            required
                            autofocus
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="community-type">Type</Label>
                        <OptionSelect
                            id="community-type"
                            v-model="form.type"
                            :options="options.communityTypes"
                        />
                        <InputError :message="form.errors.type" />
                    </div>
                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="community-description">Description</Label>
                        <Textarea
                            id="community-description"
                            v-model="form.description"
                            rows="4"
                            placeholder="Where it meets, which streets it covers…"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                </div>
            </section>

            <section class="rounded-xl border bg-card">
                <header class="border-b px-5 py-4">
                    <h2 class="font-semibold">Leadership</h2>
                    <p class="text-sm text-muted-foreground">
                        The member who leads the community and is the first
                        point of contact for it.
                    </p>
                </header>
                <div class="grid gap-2 p-5">
                    <Label for="community-leader">Leader</Label>
                    <RecordPicker
                        id="community-leader"
                        v-model="form.leader_member_id"
                        :url="lookupMembers().url"
                        :initial="community?.leader ?? null"
                        placeholder="Search members by name or number…"
                        :describe="(item: MemberSummary) => item.memberNo"
                        @select="leader = $event"
                    />
                    <InputError :message="form.errors.leader_member_id" />
                </div>
            </section>
        </div>

        <!-- Live preview -->
        <aside>
            <section
                class="relative isolate overflow-hidden rounded-xl border bg-card p-5"
            >
                <div
                    class="pointer-events-none absolute -top-12 -right-12 -z-10 size-36 rounded-full bg-primary/15 blur-2xl"
                />
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Preview
                </p>
                <div class="mt-3 flex items-center gap-3">
                    <span
                        class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Church class="size-6" />
                    </span>
                    <div class="min-w-0 space-y-1">
                        <p class="truncate font-semibold">
                            {{ form.name || 'Community name' }}
                        </p>
                        <StatusBadge
                            v-if="form.type"
                            :tone="typeTone[form.type]"
                        >
                            {{ optionLabel(options.communityTypes, form.type) }}
                        </StatusBadge>
                    </div>
                </div>
                <p
                    v-if="form.description"
                    class="mt-4 line-clamp-3 text-sm text-muted-foreground"
                >
                    {{ form.description }}
                </p>
                <div class="mt-4 flex items-center gap-3 border-t pt-4">
                    <template v-if="leader">
                        <MemberAvatar
                            :name="leader.name"
                            :photo-url="leader.photoUrl"
                            class="size-9"
                        />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ leader.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">Leader</p>
                        </div>
                    </template>
                    <p
                        v-else
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <UserRound class="size-4" /> No leader chosen
                    </p>
                </div>
                <div
                    v-if="community"
                    class="mt-4 flex gap-4 text-sm text-muted-foreground tabular-nums"
                >
                    <span class="inline-flex items-center gap-1.5">
                        <UsersRound class="size-4" />
                        {{ community.membersCount }} members
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <House class="size-4" />
                        {{ community.familiesCount }} families
                    </span>
                </div>
            </section>
        </aside>

        <div
            class="sticky bottom-0 -mx-4 flex justify-end gap-2 border-t bg-background/90 px-4 py-3 backdrop-blur md:-mx-6 md:px-6 lg:col-span-3"
        >
            <Button variant="outline" as-child>
                <Link :href="index()">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ community ? 'Save community' : 'Create community' }}
            </Button>
        </div>
    </form>
</template>
