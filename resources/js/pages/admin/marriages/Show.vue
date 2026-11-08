<script setup lang="ts">
import {
    Head,
    Link,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import {
    BadgeCheck,
    BookMarked,
    CalendarDays,
    Check,
    HeartHandshake,
    Megaphone,
    Pencil,
    ShieldAlert,
    Trash2,
    Undo2,
    UserRound,
    XCircle,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import RecordWeddingDialog from '@/components/marriages/RecordWeddingDialog.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { formatDate, formatRelative } from '@/lib/format';
import { marriageStages, marriageTone } from '@/lib/marriages';
import { formatPlainDate } from '@/lib/people';
import { cn } from '@/lib/utils';
import {
    clear,
    destroy,
    edit,
    index,
    show,
    verify,
    withdraw,
} from '@/routes/admin/marriages';
import {
    destroy as bannsDestroy,
    store as bannsStore,
} from '@/routes/admin/marriages/banns';
import { update as checksUpdate } from '@/routes/admin/marriages/checks';
import {
    store as objectionStore,
    update as objectionUpdate,
} from '@/routes/admin/marriages/objections';
import { show as memberShow } from '@/routes/admin/members';
import { show as sacramentShow } from '@/routes/admin/sacraments';
import type { BannsObjection, MarriageDetail, MemberSummary } from '@/types';

const props = defineProps<{
    application: MarriageDetail;
    activity: {
        id: number;
        description: string;
        event: string | null;
        causer: string | null;
        createdAt: string | null;
    }[];
    can: {
        update: boolean;
        recordWedding: boolean;
        delete: boolean;
        viewMembers: boolean;
        viewSacraments: boolean;
    };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Marriages & banns', href: index() },
        {
            title: props.application.applicationNo,
            href: show(props.application.id),
        },
    ],
});

const page = usePage();
const stageError = computed(
    () => (page.props.errors as Record<string, string | undefined>).stage,
);

const today = new Date().toISOString().slice(0, 10);

const longDate = (value: string | null) =>
    value
        ? formatPlainDate(value, {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : null;

const isWithdrawn = computed(() => props.application.status === 'withdrawn');

const stageIndex = computed(() =>
    marriageStages.findIndex(
        (stage) => stage.value === props.application.status,
    ),
);

/** What the parish does next, in a sentence. */
const nextStep = computed(() => {
    const { status, steps, bannsPublished, bannsRequired, hasOpenObjection } =
        props.application;

    if (hasOpenObjection) {
        return 'An objection is waiting to be resolved. The banns and the wedding are on hold until it is.';
    }

    return (
        {
            applied: steps.verifyDocuments
                ? 'Every check is done. Verify the documents to start the banns.'
                : 'Collect the documents and tick each check as it is done.',
            documents_verified:
                'Announce the first banns at Sunday Mass, then record it here.',
            banns: steps.clear
                ? `All ${bannsRequired} banns are published. Clear the couple to marry.`
                : `Banns ${bannsPublished} of ${bannsRequired} published. Record the next one after it is announced.`,
            cleared:
                'The couple is cleared. Record the wedding after it is celebrated.',
            married: 'The wedding is written in the marriage register.',
            withdrawn: 'The application was withdrawn.',
        }[status] ?? ''
    );
});

const linkedMembers = computed(
    () =>
        [
            props.application.groom && {
                member: props.application.groom,
                role: 'Groom',
            },
            props.application.bride && {
                member: props.application.bride,
                role: 'Bride',
            },
        ].filter(Boolean) as { member: MemberSummary; role: string }[],
);

// Checklist: each tick is saved straight away.
const checksEditable = computed(
    () => props.can.update && props.application.steps.changeChecks,
);
const toggleCheck = (value: string, done: boolean) => {
    const checks = props.application.checks
        .filter((check) => (check.value === value ? done : check.done))
        .map((check) => check.value);

    router.put(
        checksUpdate(props.application.id).url,
        { checks },
        {
            preserveScroll: true,
        },
    );
};

const post = (url: string) => router.post(url, {}, { preserveScroll: true });

// Banns
const bannsOpen = ref(false);
const bannsForm = useForm({ published_on: today });
const openBanns = () => {
    bannsForm.clearErrors();
    bannsForm.published_on = today;
    bannsOpen.value = true;
};
const publishBanns = () => {
    bannsForm.submit(bannsStore(props.application.id), {
        preserveScroll: true,
        onSuccess: () => (bannsOpen.value = false),
    });
};
const latestBanns = computed(() => props.application.publications.at(-1));
const undoOpen = ref(false);
const undoBanns = () => {
    if (latestBanns.value) {
        router.delete(
            bannsDestroy([props.application.id, latestBanns.value.id]).url,
            { preserveScroll: true, onFinish: () => (undoOpen.value = false) },
        );
    }
};

// Objections
const objectionOpen = ref(false);
const objectionForm = useForm({ raised_by: '', raised_on: today, details: '' });
const openObjection = () => {
    objectionForm.reset();
    objectionForm.clearErrors();
    objectionOpen.value = true;
};
const raiseObjection = () => {
    objectionForm.submit(objectionStore(props.application.id), {
        preserveScroll: true,
        onSuccess: () => (objectionOpen.value = false),
    });
};

const resolving = ref<BannsObjection | null>(null);
const resolveForm = useForm({ resolution: '' });
const openResolve = (objection: BannsObjection) => {
    resolveForm.reset();
    resolveForm.clearErrors();
    resolving.value = objection;
};
const resolveObjection = () => {
    if (resolving.value) {
        resolveForm.submit(
            objectionUpdate([props.application.id, resolving.value.id]),
            { preserveScroll: true, onSuccess: () => (resolving.value = null) },
        );
    }
};

const weddingOpen = ref(false);

// Withdrawal and removal
const withdrawOpen = ref(false);
const withdrawForm = useForm({ reason: '' });
const withdrawApplication = () => {
    withdrawForm.submit(withdraw(props.application.id), {
        preserveScroll: true,
        onSuccess: () => (withdrawOpen.value = false),
    });
};

const deleteOpen = ref(false);
const deleting = ref(false);
const remove = () => {
    deleting.value = true;
    router.delete(destroy(props.application.id).url, {
        onFinish: () => (deleting.value = false),
    });
};
</script>

<template>
    <Head :title="`${application.groomName} & ${application.brideName}`" />

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
                    class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 ring-4 ring-card dark:text-rose-300"
                >
                    <HeartHandshake class="size-8" />
                </span>
                <div class="min-w-0 flex-1 space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-semibold tracking-tight">
                            {{ application.groomName }} &amp;
                            {{ application.brideName }}
                        </h1>
                        <StatusBadge :tone="marriageTone[application.status]">{{
                            application.statusLabel
                        }}</StatusBadge>
                    </div>
                    <p
                        class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground"
                    >
                        <span class="tabular-nums">{{
                            application.applicationNo
                        }}</span>
                        <span class="inline-flex items-center gap-1"
                            ><CalendarDays class="size-3.5" />
                            {{
                                longDate(application.proposedDate) ??
                                'No date yet'
                            }}</span
                        >
                        <span
                            v-if="application.officiant"
                            class="inline-flex items-center gap-1"
                            ><UserRound class="size-3.5" />
                            {{ application.officiant }}</span
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
                        <Link :href="edit(application.id)">
                            <Pencil class="size-4" /> Edit
                        </Link>
                    </Button>
                    <Button
                        v-if="can.update"
                        variant="ghost"
                        size="sm"
                        @click="withdrawOpen = true"
                    >
                        <XCircle class="size-4" /> Withdraw
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

            <!-- Stages -->
            <ol
                v-if="!isWithdrawn"
                class="relative mt-6 grid grid-cols-5 gap-2"
                aria-label="Stages"
            >
                <li
                    v-for="(stage, position) in marriageStages"
                    :key="stage.value"
                    class="flex flex-col gap-2"
                    :aria-current="position === stageIndex ? 'step' : undefined"
                >
                    <span
                        :class="
                            cn(
                                'h-1.5 rounded-full bg-muted',
                                position <= stageIndex && 'bg-primary',
                            )
                        "
                    />
                    <span
                        :class="
                            cn(
                                'truncate text-xs text-muted-foreground',
                                position === stageIndex &&
                                    'font-medium text-foreground',
                            )
                        "
                        >{{ stage.label }}</span
                    >
                </li>
            </ol>
        </section>

        <div
            :class="
                cn(
                    'flex items-start gap-3 rounded-xl border p-4 text-sm',
                    application.hasOpenObjection
                        ? 'border-destructive/40 bg-destructive/5'
                        : 'bg-primary/5',
                )
            "
        >
            <ShieldAlert
                v-if="application.hasOpenObjection"
                class="mt-0.5 size-4 shrink-0 text-destructive"
            />
            <BadgeCheck v-else class="mt-0.5 size-4 shrink-0 text-primary" />
            <div class="flex-1 space-y-2">
                <p>{{ nextStep }}</p>
                <p
                    v-if="isWithdrawn && application.withdrawalReason"
                    class="whitespace-pre-line text-muted-foreground"
                >
                    {{ application.withdrawalReason }}
                </p>
                <InputError :message="stageError" />
            </div>
            <template v-if="can.update">
                <Button
                    v-if="application.steps.verifyDocuments"
                    size="sm"
                    @click="post(verify(application.id).url)"
                >
                    Verify documents
                </Button>
                <Button
                    v-if="application.steps.clear"
                    size="sm"
                    @click="post(clear(application.id).url)"
                >
                    Clear to marry
                </Button>
                <Button
                    v-if="application.steps.marry && can.recordWedding"
                    size="sm"
                    @click="weddingOpen = true"
                >
                    Record the wedding
                </Button>
            </template>
            <Button
                v-if="application.matrimonyRecordId && can.viewSacraments"
                size="sm"
                variant="outline"
                as-child
            >
                <Link :href="sacramentShow(application.matrimonyRecordId)">
                    <BookMarked class="size-4" /> Register entry
                </Link>
            </Button>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="flex flex-col gap-6 xl:col-span-2">
                <section class="rounded-xl border bg-card">
                    <header class="border-b px-5 py-4">
                        <h2 class="font-semibold">Documents and preparation</h2>
                        <p class="text-sm text-muted-foreground">
                            {{
                                application.documentsVerifiedAt
                                    ? `Verified on ${formatDate(application.documentsVerifiedAt)}.`
                                    : 'Tick each item once it is in the file.'
                            }}
                        </p>
                    </header>
                    <ul class="divide-y">
                        <li
                            v-for="check in application.checks"
                            :key="check.value"
                            class="flex items-center gap-3 px-5 py-3"
                        >
                            <Checkbox
                                v-if="checksEditable"
                                :id="`check-${check.value}`"
                                :model-value="check.done"
                                @update:model-value="
                                    toggleCheck(check.value, $event === true)
                                "
                            />
                            <span
                                v-else
                                :class="
                                    cn(
                                        'flex size-4 items-center justify-center rounded-sm border',
                                        check.done &&
                                            'border-primary bg-primary text-primary-foreground',
                                    )
                                "
                            >
                                <Check v-if="check.done" class="size-3" />
                            </span>
                            <Label
                                :for="`check-${check.value}`"
                                :class="
                                    cn(
                                        'font-normal',
                                        !check.done && 'text-muted-foreground',
                                    )
                                "
                                >{{ check.label }}</Label
                            >
                        </li>
                    </ul>
                </section>

                <section class="rounded-xl border bg-card">
                    <header
                        class="flex items-center justify-between gap-3 border-b px-5 py-4"
                    >
                        <div>
                            <h2 class="font-semibold">Banns</h2>
                            <p class="text-sm text-muted-foreground">
                                Announced on {{ application.bannsRequired }}
                                Sundays before the wedding.
                            </p>
                        </div>
                        <Button
                            v-if="can.update && application.steps.publishBanns"
                            size="sm"
                            @click="openBanns"
                        >
                            <Megaphone class="size-4" /> Record banns
                            {{ application.bannsPublished + 1 }}
                        </Button>
                    </header>
                    <ol class="grid gap-3 p-5 sm:grid-cols-3">
                        <li
                            v-for="number in application.bannsRequired"
                            :key="number"
                            :class="
                                cn(
                                    'flex flex-col gap-1 rounded-lg border p-3',
                                    application.publications[number - 1]
                                        ? 'border-primary/30 bg-primary/5'
                                        : 'border-dashed',
                                )
                            "
                        >
                            <span class="text-xs text-muted-foreground"
                                >Banns {{ number }}</span
                            >
                            <template
                                v-if="application.publications[number - 1]"
                            >
                                <span class="text-sm font-medium">{{
                                    longDate(
                                        application.publications[number - 1]
                                            .publishedOn,
                                    )
                                }}</span>
                                <span
                                    v-if="
                                        application.publications[number - 1]
                                            .recordedBy
                                    "
                                    class="text-xs text-muted-foreground"
                                    >Recorded by
                                    {{
                                        application.publications[number - 1]
                                            .recordedBy
                                    }}</span
                                >
                                <Button
                                    v-if="
                                        can.update &&
                                        application.steps.undoBanns &&
                                        number === application.bannsPublished
                                    "
                                    variant="ghost"
                                    size="sm"
                                    class="mt-1 self-start px-2"
                                    @click="undoOpen = true"
                                >
                                    <Undo2 class="size-3.5" /> Take back
                                </Button>
                            </template>
                            <span v-else class="text-sm text-muted-foreground"
                                >Not yet published</span
                            >
                        </li>
                    </ol>
                </section>

                <section class="rounded-xl border bg-card">
                    <header
                        class="flex items-center justify-between gap-3 border-b px-5 py-4"
                    >
                        <div>
                            <h2 class="font-semibold">Objections</h2>
                            <p class="text-sm text-muted-foreground">
                                Impediments made known after the banns.
                            </p>
                        </div>
                        <Button
                            v-if="
                                can.update &&
                                application.steps.receiveObjections
                            "
                            size="sm"
                            variant="outline"
                            @click="openObjection"
                        >
                            <ShieldAlert class="size-4" /> Record objection
                        </Button>
                    </header>
                    <ul v-if="application.objections.length" class="divide-y">
                        <li
                            v-for="objection in application.objections"
                            :key="objection.id"
                            class="flex flex-col gap-2 px-5 py-4"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium">
                                    {{ objection.raisedBy }}
                                </p>
                                <span class="text-xs text-muted-foreground">{{
                                    longDate(objection.raisedOn)
                                }}</span>
                                <StatusBadge
                                    :tone="objection.isOpen ? 'red' : 'green'"
                                    >{{
                                        objection.isOpen ? 'Open' : 'Resolved'
                                    }}</StatusBadge
                                >
                            </div>
                            <p class="text-sm whitespace-pre-line">
                                {{ objection.details }}
                            </p>
                            <div
                                v-if="!objection.isOpen"
                                class="rounded-lg bg-muted/50 p-3 text-sm"
                            >
                                <p class="whitespace-pre-line">
                                    {{ objection.resolution }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Resolved
                                    <template v-if="objection.resolvedBy"
                                        >by {{ objection.resolvedBy }}</template
                                    >
                                    on {{ formatDate(objection.resolvedAt) }}
                                </p>
                            </div>
                            <Button
                                v-else-if="can.update"
                                size="sm"
                                variant="outline"
                                class="self-start"
                                @click="openResolve(objection)"
                            >
                                Record resolution
                            </Button>
                        </li>
                    </ul>
                    <p v-else class="px-5 py-4 text-sm text-muted-foreground">
                        No objections have been raised.
                    </p>
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
                <section class="rounded-xl border bg-card">
                    <header class="border-b px-5 py-4">
                        <h2 class="font-semibold">The couple</h2>
                    </header>
                    <dl class="grid gap-4 p-5">
                        <div>
                            <dt class="text-xs text-muted-foreground">Groom</dt>
                            <dd class="mt-0.5 text-sm">
                                {{ application.groomName }}
                            </dd>
                            <dd class="text-xs text-muted-foreground">
                                {{
                                    application.groom
                                        ? 'Member of this parish'
                                        : (application.groomParish ??
                                          'Parish not recorded')
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Bride</dt>
                            <dd class="mt-0.5 text-sm">
                                {{ application.brideName }}
                            </dd>
                            <dd class="text-xs text-muted-foreground">
                                {{
                                    application.bride
                                        ? 'Member of this parish'
                                        : (application.brideParish ??
                                          'Parish not recorded')
                                }}
                            </dd>
                        </div>
                        <div v-if="application.notes">
                            <dt class="text-xs text-muted-foreground">Notes</dt>
                            <dd class="mt-0.5 text-sm whitespace-pre-line">
                                {{ application.notes }}
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
                    Opened
                    <template v-if="application.createdBy"
                        >by {{ application.createdBy }}</template
                    >
                    on {{ formatDate(application.createdAt) }}
                </section>
            </aside>
        </div>
    </div>

    <Dialog v-model:open="bannsOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle
                    >Record banns {{ application.bannsPublished + 1 }} of
                    {{ application.bannsRequired }}</DialogTitle
                >
                <DialogDescription>
                    The day the banns of {{ application.groomName }} and
                    {{ application.brideName }} were announced.
                </DialogDescription>
            </DialogHeader>
            <form
                id="banns-form"
                class="grid gap-2"
                @submit.prevent="publishBanns"
            >
                <Label for="banns-date">Announced on</Label>
                <Input
                    id="banns-date"
                    v-model="bannsForm.published_on"
                    type="date"
                    :max="today"
                    required
                />
                <InputError :message="bannsForm.errors.published_on" />
                <InputError
                    :message="
                        (bannsForm.errors as Record<string, string>).stage
                    "
                />
            </form>
            <DialogFooter>
                <Button variant="outline" @click="bannsOpen = false"
                    >Cancel</Button
                >
                <Button
                    type="submit"
                    form="banns-form"
                    :disabled="bannsForm.processing"
                    >Record banns</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="objectionOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Record an objection</DialogTitle>
                <DialogDescription>
                    The banns and the wedding are held up until the objection is
                    resolved.
                </DialogDescription>
            </DialogHeader>
            <form
                id="objection-form"
                class="grid gap-4 sm:grid-cols-2"
                @submit.prevent="raiseObjection"
            >
                <div class="grid gap-2">
                    <Label for="objection-by">Raised by</Label>
                    <Input
                        id="objection-by"
                        v-model="objectionForm.raised_by"
                        required
                    />
                    <InputError :message="objectionForm.errors.raised_by" />
                </div>
                <div class="grid gap-2">
                    <Label for="objection-on">Raised on</Label>
                    <Input
                        id="objection-on"
                        v-model="objectionForm.raised_on"
                        type="date"
                        :max="today"
                        required
                    />
                    <InputError :message="objectionForm.errors.raised_on" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="objection-details">What was made known</Label>
                    <Textarea
                        id="objection-details"
                        v-model="objectionForm.details"
                        rows="4"
                        required
                    />
                    <InputError :message="objectionForm.errors.details" />
                    <InputError
                        :message="
                            (objectionForm.errors as Record<string, string>)
                                .stage
                        "
                    />
                </div>
            </form>
            <DialogFooter>
                <Button variant="outline" @click="objectionOpen = false"
                    >Cancel</Button
                >
                <Button
                    type="submit"
                    form="objection-form"
                    :disabled="objectionForm.processing"
                    >Record objection</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="resolving !== null"
        @update:open="!$event && (resolving = null)"
    >
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Resolve the objection</DialogTitle>
                <DialogDescription>
                    Say how the objection raised by {{ resolving?.raisedBy }}
                    was looked into and settled.
                </DialogDescription>
            </DialogHeader>
            <form
                id="resolve-form"
                class="grid gap-2"
                @submit.prevent="resolveObjection"
            >
                <Label for="objection-resolution">Resolution</Label>
                <Textarea
                    id="objection-resolution"
                    v-model="resolveForm.resolution"
                    rows="4"
                    required
                />
                <InputError :message="resolveForm.errors.resolution" />
            </form>
            <DialogFooter>
                <Button variant="outline" @click="resolving = null"
                    >Cancel</Button
                >
                <Button
                    type="submit"
                    form="resolve-form"
                    :disabled="resolveForm.processing"
                    >Mark resolved</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="withdrawOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Withdraw this application?</DialogTitle>
                <DialogDescription>
                    It closes the application. The couple would need a new one
                    to marry here later.
                </DialogDescription>
            </DialogHeader>
            <form
                id="withdraw-form"
                class="grid gap-2"
                @submit.prevent="withdrawApplication"
            >
                <Label for="withdraw-reason">Reason</Label>
                <Textarea
                    id="withdraw-reason"
                    v-model="withdrawForm.reason"
                    rows="3"
                    placeholder="e.g. The couple will marry in the bride's parish."
                    required
                />
                <InputError :message="withdrawForm.errors.reason" />
            </form>
            <DialogFooter>
                <Button variant="outline" @click="withdrawOpen = false"
                    >Cancel</Button
                >
                <Button
                    type="submit"
                    form="withdraw-form"
                    variant="destructive"
                    :disabled="withdrawForm.processing"
                    >Withdraw</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <RecordWeddingDialog
        v-if="can.recordWedding"
        v-model:open="weddingOpen"
        :application="application"
    />

    <ConfirmDialog
        v-model:open="undoOpen"
        :title="`Take back banns ${latestBanns?.number}?`"
        description="Use this when the banns were recorded by mistake. The publication is removed from the application."
        confirm-label="Take back"
        destructive
        @confirm="undoBanns"
    />

    <ConfirmDialog
        v-model:open="deleteOpen"
        title="Remove this marriage application?"
        description="Only remove applications opened by mistake. To close a real one, withdraw it instead. The audit trail keeps a copy."
        confirm-label="Remove application"
        destructive
        :processing="deleting"
        @confirm="remove"
    />
</template>
