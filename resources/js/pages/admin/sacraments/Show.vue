<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { BookOpen, CalendarDays, MapPin, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { formatDate, formatRelative } from '@/lib/format';
import { formatPlainDate } from '@/lib/people';
import { sacramentIcon, sacramentTile, sacramentTone } from '@/lib/sacraments';
import { cn } from '@/lib/utils';
import { show as memberShow } from '@/routes/admin/members';
import { destroy, edit, index, show } from '@/routes/admin/sacraments';
import type { MemberSummary, SacramentDetail } from '@/types';

const props = defineProps<{
    record: SacramentDetail;
    activity: {
        id: number;
        description: string;
        event: string | null;
        causer: string | null;
        createdAt: string | null;
    }[];
    can: { update: boolean; delete: boolean; viewMembers: boolean };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Sacraments', href: index() },
        { title: props.record.personName, href: show(props.record.id) },
    ],
});

const longDate = (value: string | null) =>
    value
        ? formatPlainDate(value, {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : null;

const details = computed(() =>
    [
        { label: 'Name', value: props.record.personName },
        { label: 'Gender', value: props.record.genderLabel },
        { label: 'Date of birth', value: longDate(props.record.dateOfBirth) },
        { label: 'Spouse', value: props.record.spouseName },
        { label: "Father's name", value: props.record.fatherName },
        { label: "Mother's name", value: props.record.motherName },
        { label: 'Confirmation name', value: props.record.confirmationName },
        { label: 'Ordained to the', value: props.record.orderLabel },
        { label: 'Date', value: longDate(props.record.receivedOn) },
        { label: 'Minister', value: props.record.minister },
        {
            label: 'Celebrated at',
            value: props.record.elsewhere ? props.record.place : 'This parish',
        },
    ].filter(
        (detail) =>
            detail.value !== null ||
            ['Name', 'Date', 'Minister'].includes(detail.label),
    ),
);

/** The people in the entry who are also in the member register. */
const linkedMembers = computed(
    () =>
        [
            props.record.member && {
                member: props.record.member,
                role:
                    props.record.type === 'matrimony'
                        ? 'In the register'
                        : 'Received the sacrament',
            },
            props.record.spouse && {
                member: props.record.spouse,
                role: 'Spouse',
            },
        ].filter(Boolean) as { member: MemberSummary; role: string }[],
);

const reference = computed(() => [
    { label: 'Book', value: props.record.registerBook },
    { label: 'Page', value: props.record.registerPage },
    { label: 'Entry', value: props.record.entryNo },
]);

const sponsorTitle = computed(() =>
    props.record.type === 'matrimony'
        ? 'Witnesses'
        : props.record.type === 'baptism'
          ? 'Godparents'
          : 'Sponsors',
);

const deleteOpen = ref(false);
const deleting = ref(false);
const remove = () => {
    deleting.value = true;
    router.delete(destroy(props.record.id).url, {
        onFinish: () => (deleting.value = false),
    });
};
</script>

<template>
    <Head :title="`${record.typeLabel} of ${record.personName}`" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <section
            class="relative overflow-hidden rounded-xl border bg-card p-5 md:p-6"
        >
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-20 bg-gradient-to-r from-primary/15 via-primary/5 to-transparent"
            />
            <div
                class="relative flex flex-col gap-4 sm:flex-row sm:items-center"
            >
                <span
                    :class="
                        cn(
                            'flex size-16 shrink-0 items-center justify-center rounded-2xl ring-4 ring-card',
                            sacramentTile[record.type],
                        )
                    "
                >
                    <component
                        :is="sacramentIcon[record.type]"
                        class="size-8"
                    />
                </span>
                <div class="min-w-0 flex-1 space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1
                            class="truncate text-2xl font-semibold tracking-tight"
                        >
                            {{ record.personName }}
                            <template v-if="record.spouseName">
                                &amp; {{ record.spouseName }}</template
                            >
                        </h1>
                        <StatusBadge :tone="sacramentTone[record.type]">{{
                            record.typeLabel
                        }}</StatusBadge>
                    </div>
                    <p
                        class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-1"
                            ><CalendarDays class="size-3.5" />
                            {{ longDate(record.receivedOn) }}</span
                        >
                        <span class="inline-flex items-center gap-1"
                            ><MapPin class="size-3.5" />
                            {{
                                record.elsewhere ? record.place : 'This parish'
                            }}</span
                        >
                        <span
                            v-if="record.reference"
                            class="inline-flex items-center gap-1"
                            ><BookOpen class="size-3.5" />
                            {{ record.reference }}</span
                        >
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="can.update"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link :href="edit(record.id)">
                            <Pencil class="size-4" /> Edit
                        </Link>
                    </Button>
                    <Button
                        v-if="can.delete"
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="deleteOpen = true"
                    >
                        <Trash2 class="size-4" /> Remove
                    </Button>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="flex flex-col gap-6 xl:col-span-2">
                <section class="rounded-xl border bg-card">
                    <header class="border-b px-5 py-4">
                        <h2 class="font-semibold">Register entry</h2>
                    </header>
                    <dl class="grid gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
                        <div v-for="detail in details" :key="detail.label">
                            <dt class="text-xs text-muted-foreground">
                                {{ detail.label }}
                            </dt>
                            <dd class="mt-0.5 text-sm">
                                {{ detail.value ?? '—' }}
                            </dd>
                        </div>
                        <div v-if="record.notes" class="sm:col-span-2">
                            <dt class="text-xs text-muted-foreground">Notes</dt>
                            <dd class="mt-0.5 text-sm whitespace-pre-line">
                                {{ record.notes }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    v-if="record.sponsors.length"
                    class="rounded-xl border bg-card"
                >
                    <header class="border-b px-5 py-4">
                        <h2 class="font-semibold">{{ sponsorTitle }}</h2>
                    </header>
                    <ul class="divide-y">
                        <li
                            v-for="sponsor in record.sponsors"
                            :key="sponsor.id"
                            class="flex items-center gap-3 px-5 py-3"
                        >
                            <MemberAvatar
                                :name="sponsor.name"
                                :photo-url="sponsor.member?.photoUrl"
                                class="size-9"
                            />
                            <div class="min-w-0 flex-1">
                                <Link
                                    v-if="sponsor.member && can.viewMembers"
                                    :href="memberShow(sponsor.member.id)"
                                    class="block truncate text-sm font-medium hover:underline"
                                    >{{ sponsor.name }}</Link
                                >
                                <p v-else class="truncate text-sm font-medium">
                                    {{ sponsor.name }}
                                </p>
                                <p
                                    class="text-xs text-muted-foreground tabular-nums"
                                >
                                    {{
                                        sponsor.member?.memberNo ??
                                        'Not in the member register'
                                    }}
                                </p>
                            </div>
                            <StatusBadge>{{ sponsor.roleLabel }}</StatusBadge>
                        </li>
                    </ul>
                </section>

                <section class="rounded-xl border bg-card p-5">
                    <h2 class="mb-4 font-semibold">History</h2>
                    <ol v-if="activity.length" class="flex flex-col gap-4">
                        <li
                            v-for="entry in activity"
                            :key="entry.id"
                            class="relative border-l pl-4"
                        >
                            <span
                                class="absolute top-1.5 -left-[5px] size-2.5 rounded-full border-2 border-card bg-primary"
                            />
                            <p class="text-sm">{{ entry.description }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ entry.causer ?? 'System' }} ·
                                {{ formatRelative(entry.createdAt) }}
                            </p>
                        </li>
                    </ol>
                    <p v-else class="text-sm text-muted-foreground">
                        No changes recorded yet.
                    </p>
                </section>
            </div>

            <aside class="flex flex-col gap-6">
                <section class="rounded-xl border bg-card p-5">
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Paper register
                    </p>
                    <dl class="mt-3 grid grid-cols-3 divide-x text-center">
                        <div v-for="part in reference" :key="part.label">
                            <dt class="text-xs text-muted-foreground">
                                {{ part.label }}
                            </dt>
                            <dd
                                class="mt-1 text-2xl font-semibold tabular-nums"
                            >
                                {{ part.value ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    v-if="linkedMembers.length"
                    class="rounded-xl border bg-card"
                >
                    <header class="border-b px-5 py-4">
                        <h2 class="font-semibold">Member register</h2>
                    </header>
                    <ul class="divide-y">
                        <li
                            v-for="linked in linkedMembers"
                            :key="linked.member.id"
                            class="flex items-center gap-3 px-5 py-3"
                        >
                            <MemberAvatar
                                :name="linked.member.name"
                                :photo-url="linked.member.photoUrl"
                            />
                            <div class="min-w-0">
                                <Link
                                    v-if="can.viewMembers"
                                    :href="memberShow(linked.member.id)"
                                    class="block truncate text-sm font-medium hover:underline"
                                    >{{ linked.member.name }}</Link
                                >
                                <p v-else class="truncate text-sm font-medium">
                                    {{ linked.member.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ linked.member.memberNo }} ·
                                    {{ linked.role }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </section>

                <section
                    class="rounded-xl border bg-card p-5 text-sm text-muted-foreground"
                >
                    Recorded
                    <template v-if="record.recordedBy"
                        >by {{ record.recordedBy }}</template
                    >
                    on {{ formatDate(record.createdAt) }}
                </section>
            </aside>
        </div>
    </div>

    <ConfirmDialog
        v-model:open="deleteOpen"
        :title="`Remove this ${record.typeLabel.toLowerCase()} entry?`"
        description="It will no longer appear in the register or on the member's profile. The audit trail keeps a copy of what it said."
        confirm-label="Remove entry"
        destructive
        :processing="deleting"
        @confirm="remove"
    />
</template>
