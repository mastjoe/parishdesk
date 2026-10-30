<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, Inbox, X } from '@lucide/vue';
import { reactive, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { formatRelative } from '@/lib/format';
import { formatPlainDate, memberFieldLabels } from '@/lib/people';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/admin/change-requests';
import { show as memberShow } from '@/routes/admin/members';
import type { MemberSummary, Paginated } from '@/types';

type ChangeRequest = {
    id: number;
    member: MemberSummary;
    changes: Record<string, string | null>;
    original: Record<string, string | null>;
    current: Record<string, string | null>;
    note: string | null;
    status: 'pending' | 'approved' | 'rejected';
    reviewNote: string | null;
    reviewer: string | null;
    createdAt: string | null;
    reviewedAt: string | null;
    canReview: boolean;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Change requests', href: index() }],
    },
});

defineProps<{
    requests: Paginated<ChangeRequest>;
    status: 'pending' | 'approved' | 'rejected';
    pendingCount: number;
}>();

const tabs = [
    { key: 'pending', label: 'Waiting' },
    { key: 'approved', label: 'Approved' },
    { key: 'rejected', label: 'Rejected' },
];

const show = (value: string | null | undefined, field: string) => {
    if (!value) {
        return '(empty)';
    }

    if (field === 'marital_status') {
        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    return field.includes('date') ? formatPlainDate(value) : value;
};

const form = useForm({ decision: 'approve', review_note: '' });
const rejecting = reactive<Record<number, boolean>>({});
const reviewingId = ref<number | null>(null);

const review = (request: ChangeRequest, decision: 'approve' | 'reject') => {
    reviewingId.value = request.id;
    form.decision = decision;
    form.submit(update(request.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            delete rejecting[request.id];
        },
    });
};
</script>

<template>
    <Head title="Change requests" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Change requests"
            description="Details members asked to update from the portal. Approving applies them to the register."
        />

        <nav class="inline-flex w-fit rounded-lg bg-muted p-[3px]">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="index({ query: { status: tab.key } })"
                preserve-scroll
                :class="
                    cn(
                        'inline-flex items-center gap-1.5 rounded-md px-3 py-1 text-sm font-medium',
                        status === tab.key
                            ? 'bg-background shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    )
                "
            >
                {{ tab.label }}
                <span
                    v-if="tab.key === 'pending' && pendingCount"
                    class="rounded-full bg-primary px-1.5 text-xs text-primary-foreground tabular-nums"
                    >{{ pendingCount }}</span
                >
            </Link>
        </nav>

        <EmptyState
            v-if="requests.data.length === 0"
            :icon="Inbox"
            :title="status === 'pending' ? 'All caught up' : 'Nothing here yet'"
            :description="
                status === 'pending'
                    ? 'No members are waiting for their changes to be reviewed.'
                    : undefined
            "
        />

        <article
            v-for="request in requests.data"
            :key="request.id"
            class="rounded-xl border bg-card"
        >
            <header
                class="flex flex-wrap items-center gap-3 border-b px-5 py-4"
            >
                <MemberAvatar
                    :name="request.member.name"
                    :photo-url="request.member.photoUrl"
                />
                <div class="min-w-0 flex-1">
                    <Link
                        :href="memberShow(request.member.id)"
                        class="block truncate font-medium hover:underline"
                        >{{ request.member.name }}</Link
                    >
                    <p class="text-xs text-muted-foreground">
                        {{ request.member.memberNo }} · sent
                        {{ formatRelative(request.createdAt) }}
                    </p>
                </div>
                <StatusBadge
                    v-if="request.status !== 'pending'"
                    :tone="request.status === 'approved' ? 'green' : 'red'"
                >
                    {{ request.status }} by {{ request.reviewer ?? 'someone' }}
                    {{ formatRelative(request.reviewedAt) }}
                </StatusBadge>
            </header>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead
                        class="text-left text-xs text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="h-9 px-5 font-medium">Field</th>
                            <th class="h-9 px-5 font-medium">
                                In the register
                            </th>
                            <th class="h-9 px-5 font-medium">Requested</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y border-t">
                        <tr
                            v-for="(value, field) in request.changes"
                            :key="field"
                        >
                            <td class="px-5 py-2.5 text-muted-foreground">
                                {{ memberFieldLabels[field] ?? field }}
                            </td>
                            <td class="px-5 py-2.5">
                                {{
                                    show(
                                        request.status === 'pending'
                                            ? request.current[field]
                                            : request.original[field],
                                        field,
                                    )
                                }}
                                <span
                                    v-if="
                                        request.status === 'pending' &&
                                        request.current[field] !==
                                            request.original[field]
                                    "
                                    class="ml-1 text-xs text-amber-600"
                                    >(changed since the request)</span
                                >
                            </td>
                            <td class="px-5 py-2.5 font-medium">
                                {{ show(value, field) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="request.note || request.reviewNote"
                class="flex flex-col gap-2 border-t px-5 py-4 text-sm"
            >
                <p v-if="request.note">
                    <span class="text-muted-foreground">Member's note:</span>
                    {{ request.note }}
                </p>
                <p v-if="request.reviewNote">
                    <span class="text-muted-foreground">Office note:</span>
                    {{ request.reviewNote }}
                </p>
            </div>

            <footer
                v-if="request.status === 'pending'"
                class="flex flex-col gap-3 border-t px-5 py-4"
            >
                <template v-if="request.canReview">
                    <div v-if="rejecting[request.id]" class="grid gap-2">
                        <Textarea
                            v-model="form.review_note"
                            rows="2"
                            placeholder="Tell the member why, e.g. please bring your baptism certificate to the office."
                        />
                        <InputError
                            v-if="reviewingId === request.id"
                            :message="form.errors.review_note"
                        />
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        <template v-if="rejecting[request.id]">
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="delete rejecting[request.id]"
                                >Back</Button
                            >
                            <Button
                                variant="destructive"
                                size="sm"
                                :disabled="form.processing"
                                @click="review(request, 'reject')"
                            >
                                Reject request
                            </Button>
                        </template>
                        <template v-else>
                            <Button
                                variant="outline"
                                size="sm"
                                @click="rejecting[request.id] = true"
                            >
                                <X class="size-4" /> Reject
                            </Button>
                            <Button
                                size="sm"
                                :disabled="form.processing"
                                @click="review(request, 'approve')"
                            >
                                <Check class="size-4" /> Approve and apply
                            </Button>
                        </template>
                    </div>
                </template>
                <p v-else class="text-sm text-muted-foreground">
                    This is your own member record, so another reviewer needs to
                    handle it.
                </p>
            </footer>
        </article>

        <Pagination :paginator="requests" />
    </div>
</template>
