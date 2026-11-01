<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Cross, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import OptionSelect from '@/components/app/OptionSelect.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import RecordPicker from '@/components/app/RecordPicker.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
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
import { formatPlainDate } from '@/lib/people';
import { destroy, index, store, update } from '@/routes/admin/clergy';
import { members as lookupMembers } from '@/routes/admin/lookup';
import type { PeopleOptions } from '@/types';

type Assignment = {
    id: number;
    name: string;
    role: string;
    roleLabel: string;
    starts_on: string;
    ends_on: string | null;
    notes: string | null;
    isCurrent: boolean;
    member: { id: number; name: string; memberNo: string } | null;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clergy', href: index() }],
    },
});

const props = defineProps<{
    assignments: Assignment[];
    options: PeopleOptions;
    can: { manage: boolean };
}>();

const current = computed(() => props.assignments.filter((a) => a.isCurrent));
const past = computed(() => props.assignments.filter((a) => !a.isCurrent));

const years = (assignment: Assignment) => {
    const start = Number(assignment.starts_on.slice(0, 4));
    const end = assignment.ends_on
        ? Number(assignment.ends_on.slice(0, 4))
        : null;

    return end === null
        ? `${start} to date`
        : start === end
          ? `${start}`
          : `${start} to ${end}`;
};

const open = ref(false);
const editing = ref<Assignment | null>(null);
const form = useForm({
    member_id: null as number | null,
    name: '',
    role: 'parish_priest' as string | null,
    starts_on: '',
    ends_on: '',
    notes: '',
});

const openForm = (assignment: Assignment | null) => {
    editing.value = assignment;
    form.clearErrors();
    form.defaults({
        member_id: assignment?.member?.id ?? null,
        name: assignment?.name ?? '',
        role: assignment?.role ?? 'parish_priest',
        starts_on: assignment?.starts_on ?? '',
        ends_on: assignment?.ends_on ?? '',
        notes: assignment?.notes ?? '',
    });
    form.reset();
    open.value = true;
};

const onMemberPicked = (
    member: { id: number; name: string; title?: string | null } | null,
) => {
    if (member && !form.name) {
        form.name = [member.title, member.name].filter(Boolean).join(' ');
    }
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

const deleting = ref<Assignment | null>(null);
const remove = () => {
    if (deleting.value) {
        router.delete(destroy(deleting.value.id).url, {
            preserveScroll: true,
            onFinish: () => (deleting.value = null),
        });
    }
};
</script>

<template>
    <Head title="Clergy" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Clergy"
            description="The priests and deacons serving the parish now, and those who served before."
        >
            <template #actions>
                <Button v-if="can.manage" @click="openForm(null)">
                    <Plus class="size-4" /> Record assignment
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="assignments.length === 0"
            :icon="Cross"
            title="No clergy recorded"
            description="Record the parish priest and assistants, and build up the history of those who served before."
        />

        <section v-if="current.length" class="flex flex-col gap-3">
            <h2
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                Serving now
            </h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="assignment in current"
                    :key="assignment.id"
                    class="relative flex flex-col gap-3 overflow-hidden rounded-xl border bg-card p-5"
                >
                    <span
                        class="absolute inset-y-0 left-0 w-1 bg-primary"
                        aria-hidden="true"
                    />
                    <div class="flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">
                                {{ assignment.name }}
                            </p>
                            <StatusBadge tone="primary">{{
                                assignment.roleLabel
                            }}</StatusBadge>
                        </div>
                        <Button
                            v-if="can.manage"
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            :aria-label="`Edit ${assignment.name}`"
                            @click="openForm(assignment)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Since {{ formatPlainDate(assignment.starts_on) }}
                    </p>
                </article>
            </div>
        </section>

        <section v-if="past.length" class="flex flex-col gap-3">
            <h2
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                History
            </h2>
            <ol class="relative ml-2 flex flex-col gap-5 border-l pl-6">
                <li
                    v-for="assignment in past"
                    :key="assignment.id"
                    class="relative"
                >
                    <span
                        class="absolute top-1.5 -left-[29px] size-2.5 rounded-full border-2 border-background bg-muted-foreground"
                    />
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <p class="font-medium">{{ assignment.name }}</p>
                        <span class="text-sm text-muted-foreground"
                            >{{ assignment.roleLabel }} ·
                            {{ years(assignment) }}</span
                        >
                        <div v-if="can.manage" class="ml-auto flex gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-7"
                                :aria-label="`Edit ${assignment.name}`"
                                @click="openForm(assignment)"
                            >
                                <Pencil class="size-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-7 text-destructive hover:text-destructive"
                                :aria-label="`Delete ${assignment.name}`"
                                @click="deleting = assignment"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                    <p
                        v-if="assignment.notes"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ assignment.notes }}
                    </p>
                </li>
            </ol>
        </section>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{
                    editing ? 'Edit assignment' : 'Record an assignment'
                }}</DialogTitle>
                <DialogDescription>
                    Leave the end date empty while the priest or deacon is still
                    serving.
                </DialogDescription>
            </DialogHeader>
            <form id="clergy-form" class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="clergy-member">Member record (optional)</Label>
                    <RecordPicker
                        id="clergy-member"
                        v-model="form.member_id"
                        :url="lookupMembers().url"
                        :initial="
                            editing?.member
                                ? {
                                      id: editing.member.id,
                                      name: editing.member.name,
                                  }
                                : null
                        "
                        placeholder="Link to a member in the register…"
                        @select="onMemberPicked"
                    />
                    <InputError :message="form.errors.member_id" />
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="clergy-name">Name</Label>
                        <Input
                            id="clergy-name"
                            v-model="form.name"
                            placeholder="Rev. Fr. Michael Eze"
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="clergy-role">Office</Label>
                        <OptionSelect
                            id="clergy-role"
                            v-model="form.role"
                            :options="options.clergyRoles"
                        />
                        <InputError :message="form.errors.role" />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="clergy-start">Started</Label>
                        <Input
                            id="clergy-start"
                            v-model="form.starts_on"
                            type="date"
                            required
                        />
                        <InputError :message="form.errors.starts_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="clergy-end">Ended</Label>
                        <Input
                            id="clergy-end"
                            v-model="form.ends_on"
                            type="date"
                        />
                        <InputError :message="form.errors.ends_on" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="clergy-notes">Notes</Label>
                    <Textarea id="clergy-notes" v-model="form.notes" rows="2" />
                    <InputError :message="form.errors.notes" />
                </div>
            </form>
            <DialogFooter>
                <Button
                    v-if="editing"
                    variant="ghost"
                    class="mr-auto text-destructive hover:text-destructive"
                    @click="
                        deleting = editing;
                        open = false;
                    "
                >
                    Delete
                </Button>
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="clergy-form"
                    :disabled="form.processing"
                >
                    Save
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="deleting !== null"
        :title="`Delete this assignment for ${deleting?.name}?`"
        description="It will be removed from the clergy history."
        confirm-label="Delete"
        destructive
        @update:open="(value) => !value && (deleting = null)"
        @confirm="remove"
    />
</template>
