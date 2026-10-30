<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Church, House, Pencil, Plus, Trash2, UsersRound } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import OptionSelect from '@/components/app/OptionSelect.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import RecordPicker from '@/components/app/RecordPicker.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { destroy, index, store, update } from '@/routes/admin/communities';
import { members as lookupMembers } from '@/routes/admin/lookup';
import { index as membersIndex } from '@/routes/admin/members';
import type { MemberSummary, PeopleOptions } from '@/types';

type CommunityRow = {
    id: number;
    name: string;
    type: string;
    typeLabel: string;
    description: string | null;
    leader: MemberSummary | null;
    membersCount: number;
    familiesCount: number;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Communities', href: index() }],
    },
});

defineProps<{
    communities: CommunityRow[];
    options: PeopleOptions;
    can: { manage: boolean; viewMembers: boolean };
}>();

const open = ref(false);
const editing = ref<CommunityRow | null>(null);
const form = useForm({
    name: '',
    type: 'scc' as string | null,
    description: '',
    leader_member_id: null as number | null,
});

const openForm = (community: CommunityRow | null) => {
    editing.value = community;
    form.clearErrors();
    form.defaults({
        name: community?.name ?? '',
        type: community?.type ?? 'scc',
        description: community?.description ?? '',
        leader_member_id: community?.leader?.id ?? null,
    });
    form.reset();
    open.value = true;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (editing.value) {
        form.submit(update(editing.value.id), options);
    } else {
        form.submit(store(), options);
    }
};

const deleting = ref<CommunityRow | null>(null);
const remove = () => {
    if (deleting.value) {
        router.delete(destroy(deleting.value.id).url, {
            preserveScroll: true,
            onFinish: () => (deleting.value = null),
        });
    }
};

const typeTone: Record<string, string> = {
    scc: 'primary',
    station: 'blue',
    outstation: 'teal',
    zone: 'slate',
};
</script>

<template>
    <Head title="Communities" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Communities"
            description="Small Christian Communities, stations and outstations of the parish."
        >
            <template #actions>
                <Button v-if="can.manage" @click="openForm(null)">
                    <Plus class="size-4" /> New community
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="communities.length === 0"
            :icon="Church"
            title="No communities yet"
            description="Create the communities members belong to, so you can group and filter the register by them."
        />

        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="community in communities"
                :key="community.id"
                class="flex flex-col gap-4 rounded-xl border bg-card p-5"
            >
                <div class="flex items-start gap-3">
                    <div class="min-w-0 flex-1 space-y-1">
                        <h2 class="truncate font-semibold">
                            {{ community.name }}
                        </h2>
                        <StatusBadge :tone="typeTone[community.type]">{{
                            community.typeLabel
                        }}</StatusBadge>
                    </div>
                    <div v-if="can.manage" class="flex gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            :aria-label="`Edit ${community.name}`"
                            @click="openForm(community)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8 text-destructive hover:text-destructive"
                            :aria-label="`Delete ${community.name}`"
                            @click="deleting = community"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
                <p
                    v-if="community.description"
                    class="line-clamp-2 text-sm text-muted-foreground"
                >
                    {{ community.description }}
                </p>
                <div class="mt-auto flex items-center gap-3 border-t pt-4">
                    <template v-if="community.leader">
                        <MemberAvatar
                            :name="community.leader.name"
                            :photo-url="community.leader.photoUrl"
                            class="size-8"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ community.leader.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">Leader</p>
                        </div>
                    </template>
                    <p v-else class="flex-1 text-sm text-muted-foreground">
                        No leader chosen
                    </p>
                    <component
                        :is="can.viewMembers ? Link : 'span'"
                        :href="
                            can.viewMembers
                                ? membersIndex({
                                      query: {
                                          filter: { community: community.id },
                                      },
                                  })
                                : undefined
                        "
                        class="flex items-center gap-3 text-sm text-muted-foreground tabular-nums"
                    >
                        <span class="inline-flex items-center gap-1"
                            ><UsersRound class="size-3.5" />
                            {{ community.membersCount }}</span
                        >
                        <span class="inline-flex items-center gap-1"
                            ><House class="size-3.5" />
                            {{ community.familiesCount }}</span
                        >
                    </component>
                </div>
            </article>
        </div>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{
                    editing ? 'Edit community' : 'New community'
                }}</DialogTitle>
                <DialogDescription>
                    Members and families can be placed in a community.
                </DialogDescription>
            </DialogHeader>
            <form
                id="community-form"
                class="grid gap-4"
                @submit.prevent="submit"
            >
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="community-name">Name</Label>
                        <Input
                            id="community-name"
                            v-model="form.name"
                            placeholder="St. Jude SCC"
                            required
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
                </div>
                <div class="grid gap-2">
                    <Label for="community-leader">Leader</Label>
                    <RecordPicker
                        id="community-leader"
                        v-model="form.leader_member_id"
                        :url="lookupMembers().url"
                        :initial="editing?.leader ?? null"
                        placeholder="Search members…"
                        :describe="(item: MemberSummary) => item.memberNo"
                    />
                    <InputError :message="form.errors.leader_member_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="community-description">Description</Label>
                    <Textarea
                        id="community-description"
                        v-model="form.description"
                        rows="3"
                        placeholder="Where it meets, which streets it covers…"
                    />
                    <InputError :message="form.errors.description" />
                </div>
            </form>
            <DialogFooter>
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="community-form"
                    :disabled="form.processing"
                >
                    {{ editing ? 'Save community' : 'Create community' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="deleting !== null"
        :title="`Delete ${deleting?.name}?`"
        description="Its members and families stay in the register without a community."
        confirm-label="Delete community"
        destructive
        @update:open="(value) => !value && (deleting = null)"
        @confirm="remove"
    />
</template>
