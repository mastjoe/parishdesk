<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { UserPlus, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import RecordPicker from '@/components/app/RecordPicker.vue';
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
import { members as lookupMembers } from '@/routes/admin/lookup';
import { store } from '@/routes/admin/marriages/wedding';
import type { MarriageDetail, MemberSummary } from '@/types';

const props = defineProps<{
    application: MarriageDetail;
}>();

const open = defineModel<boolean>('open', { default: false });

/** The marriage register takes up to four witnesses. */
const maxWitnesses = 4;

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    received_on: '',
    minister: '',
    register_book: '',
    register_page: '',
    entry_no: '',
    notes: '',
    witnesses: [] as { member_id: number | null; name: string }[],
});

// Start from the proposed date and officiant each time the dialog opens.
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.received_on =
        props.application.proposedDate &&
        props.application.proposedDate <= today
            ? props.application.proposedDate
            : today;
    form.minister = props.application.officiant ?? '';
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

const witnessMembers = ref<Record<number, MemberSummary>>({});
const pickerKey = ref(0);

const addMember = (picked: MemberSummary | null) => {
    if (
        picked &&
        form.witnesses.length < maxWitnesses &&
        !form.witnesses.some((witness) => witness.member_id === picked.id)
    ) {
        witnessMembers.value[picked.id] = picked;
        form.witnesses.push({ member_id: picked.id, name: picked.name });
    }

    pickerKey.value++;
};

const witnessError = (position: number) =>
    errors.value[`witnesses.${position}.name`] ??
    errors.value[`witnesses.${position}.member_id`];

const submit = () => {
    form.submit(store(props.application.id), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Record the wedding</DialogTitle>
                <DialogDescription>
                    Writes the marriage of {{ application.groomName }} and
                    {{ application.brideName }} in the marriage register and
                    closes the application.
                </DialogDescription>
            </DialogHeader>
            <form
                id="wedding-form"
                class="grid gap-4 sm:grid-cols-2"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="wedding-date">Date of the wedding</Label>
                    <Input
                        id="wedding-date"
                        v-model="form.received_on"
                        type="date"
                        :max="today"
                        required
                    />
                    <InputError :message="form.errors.received_on" />
                </div>
                <div class="grid gap-2">
                    <Label for="wedding-minister">Minister</Label>
                    <Input
                        id="wedding-minister"
                        v-model="form.minister"
                        placeholder="Rev. Fr. …"
                    />
                    <InputError :message="form.errors.minister" />
                </div>

                <div class="grid gap-3 sm:col-span-2">
                    <Label>Witnesses</Label>
                    <ul
                        v-if="form.witnesses.length > 0"
                        class="divide-y rounded-lg border"
                    >
                        <li
                            v-for="(witness, position) in form.witnesses"
                            :key="position"
                            class="flex flex-col gap-1 p-2"
                        >
                            <div class="flex items-center gap-2">
                                <p
                                    v-if="witness.member_id"
                                    class="min-w-0 flex-1 truncate px-1 text-sm"
                                >
                                    {{ witness.name }}
                                    <span
                                        class="text-xs text-muted-foreground tabular-nums"
                                        >{{
                                            witnessMembers[witness.member_id]
                                                ?.memberNo
                                        }}</span
                                    >
                                </p>
                                <Input
                                    v-else
                                    v-model="witness.name"
                                    class="flex-1"
                                    placeholder="Full name"
                                    :aria-label="`Name of witness ${position + 1}`"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Remove ${witness.name || 'witness'}`"
                                    @click="form.witnesses.splice(position, 1)"
                                >
                                    <X class="size-4" />
                                </Button>
                            </div>
                            <InputError :message="witnessError(position)" />
                        </li>
                    </ul>
                    <div
                        v-if="form.witnesses.length < maxWitnesses"
                        class="flex flex-col gap-2 sm:flex-row"
                    >
                        <div class="flex-1">
                            <RecordPicker
                                :key="pickerKey"
                                :url="lookupMembers().url"
                                placeholder="Add a member…"
                                :clearable="false"
                                :describe="
                                    (item: MemberSummary) => item.memberNo
                                "
                                @select="addMember"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            @click="
                                form.witnesses.push({
                                    member_id: null,
                                    name: '',
                                })
                            "
                        >
                            <UserPlus class="size-4" /> Someone else
                        </Button>
                    </div>
                    <InputError :message="form.errors.witnesses" />
                </div>

                <div class="grid gap-4 sm:col-span-2 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="wedding-book">Register book</Label>
                        <Input id="wedding-book" v-model="form.register_book" />
                        <InputError :message="form.errors.register_book" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="wedding-page">Page</Label>
                        <Input id="wedding-page" v-model="form.register_page" />
                        <InputError :message="form.errors.register_page" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="wedding-entry">Entry number</Label>
                        <Input id="wedding-entry" v-model="form.entry_no" />
                        <InputError :message="form.errors.entry_no" />
                    </div>
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="wedding-notes">Notes</Label>
                    <Textarea
                        id="wedding-notes"
                        v-model="form.notes"
                        rows="2"
                    />
                    <InputError :message="form.errors.notes" />
                </div>
                <InputError class="sm:col-span-2" :message="errors.stage" />
            </form>
            <DialogFooter>
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="wedding-form"
                    :disabled="form.processing"
                >
                    Record wedding
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
