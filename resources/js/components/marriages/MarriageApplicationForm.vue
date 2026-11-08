<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { CalendarDays, Church, HeartHandshake, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import RecordPicker from '@/components/app/RecordPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatPlainDate } from '@/lib/people';
import { members as lookupMembers } from '@/routes/admin/lookup';
import { index, show, store, update } from '@/routes/admin/marriages';
import type { MarriageFields, MarriageRow, MemberSummary } from '@/types';

const props = defineProps<{
    /** The application being changed; omit to open a new one. */
    application?: MarriageRow & { fields: MarriageFields };
    /** A member to start a new application with, as groom or bride. */
    member?: MemberSummary | null;
}>();

const fields = props.application?.fields;
const startsAsGroom = props.member?.gender === 'male';
const startsAsBride = props.member?.gender === 'female';

// Text inputs work with empty strings; the server stores them as null.
const form = useForm({
    groom_member_id:
        fields?.groom_member_id ?? (startsAsGroom ? props.member!.id : null),
    groom_name: fields?.groom_name ?? (startsAsGroom ? props.member!.name : ''),
    groom_parish: fields?.groom_parish ?? '',
    bride_member_id:
        fields?.bride_member_id ?? (startsAsBride ? props.member!.id : null),
    bride_name: fields?.bride_name ?? (startsAsBride ? props.member!.name : ''),
    bride_parish: fields?.bride_parish ?? '',
    proposed_date: fields?.proposed_date ?? '',
    wedding_parish: fields?.wedding_parish ?? '',
    officiant: fields?.officiant ?? '',
    notes: fields?.notes ?? '',
});

const sides = computed(() => [
    {
        key: 'groom' as const,
        title: 'Groom',
        initial:
            props.application?.groom ?? (startsAsGroom ? props.member! : null),
    },
    {
        key: 'bride' as const,
        title: 'Bride',
        initial:
            props.application?.bride ?? (startsAsBride ? props.member! : null),
    },
]);

const pick = (side: 'groom' | 'bride', picked: MemberSummary | null) => {
    if (picked) {
        form[`${side}_name`] = picked.name;
        form[`${side}_parish`] = '';
    }
};

const submit = () => {
    if (props.application) {
        form.submit(update(props.application.id));
    } else {
        form.submit(store());
    }
};
</script>

<template>
    <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="submit">
        <div class="flex flex-col gap-6 lg:col-span-2">
            <section
                v-for="side in sides"
                :key="side.key"
                class="rounded-xl border bg-card"
            >
                <header class="border-b px-5 py-4">
                    <h2 class="font-semibold">{{ side.title }}</h2>
                    <p class="text-sm text-muted-foreground">
                        Pick the member, or type the name and parish of someone
                        from another parish.
                    </p>
                </header>
                <div class="grid gap-4 p-5">
                    <div class="grid gap-2">
                        <Label :for="`marriage-${side.key}-member`"
                            >Member register</Label
                        >
                        <RecordPicker
                            :id="`marriage-${side.key}-member`"
                            v-model="form[`${side.key}_member_id`]"
                            :url="lookupMembers().url"
                            :initial="side.initial"
                            placeholder="Search members by name or number…"
                            :describe="(item: MemberSummary) => item.memberNo"
                            @select="pick(side.key, $event)"
                        />
                        <InputError
                            :message="form.errors[`${side.key}_member_id`]"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label :for="`marriage-${side.key}-name`"
                                >Full name</Label
                            >
                            <Input
                                :id="`marriage-${side.key}-name`"
                                v-model="form[`${side.key}_name`]"
                                required
                            />
                            <InputError
                                :message="form.errors[`${side.key}_name`]"
                            />
                        </div>
                        <div
                            v-if="!form[`${side.key}_member_id`]"
                            class="grid gap-2"
                        >
                            <Label :for="`marriage-${side.key}-parish`"
                                >Parish</Label
                            >
                            <Input
                                :id="`marriage-${side.key}-parish`"
                                v-model="form[`${side.key}_parish`]"
                                placeholder="St. Mary Catholic Church, Onitsha"
                            />
                            <InputError
                                :message="form.errors[`${side.key}_parish`]"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border bg-card">
                <header class="border-b px-5 py-4">
                    <h2 class="font-semibold">The wedding</h2>
                    <p class="text-sm text-muted-foreground">
                        The date the couple is hoping for, where the wedding
                        will be held and the priest who will witness it.
                    </p>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="marriage-date">Proposed date</Label>
                        <Input
                            id="marriage-date"
                            v-model="form.proposed_date"
                            type="date"
                        />
                        <InputError :message="form.errors.proposed_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="marriage-wedding-parish">Parish</Label>
                        <Input
                            id="marriage-wedding-parish"
                            v-model="form.wedding_parish"
                            placeholder="Leave empty for this parish"
                        />
                        <InputError :message="form.errors.wedding_parish" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="marriage-officiant">Officiant</Label>
                        <Input
                            id="marriage-officiant"
                            v-model="form.officiant"
                            placeholder="Rev. Fr. …"
                        />
                        <InputError :message="form.errors.officiant" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="marriage-notes">Notes</Label>
                        <Textarea
                            id="marriage-notes"
                            v-model="form.notes"
                            rows="3"
                            placeholder="Anything from the first interview…"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>
            </section>
        </div>

        <!-- Live preview -->
        <aside>
            <section
                class="relative isolate overflow-hidden rounded-xl border bg-card p-5 lg:sticky lg:top-4"
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
                        class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-300"
                    >
                        <HeartHandshake class="size-6" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-semibold">
                            {{ form.groom_name || 'Groom' }}
                        </p>
                        <p class="truncate text-sm text-muted-foreground">
                            &amp; {{ form.bride_name || 'Bride' }}
                        </p>
                    </div>
                </div>
                <ul class="mt-4 flex flex-col gap-2 border-t pt-4 text-sm">
                    <li class="flex items-center gap-2 text-muted-foreground">
                        <CalendarDays class="size-4 shrink-0" />
                        <span class="truncate">{{
                            form.proposed_date
                                ? formatPlainDate(form.proposed_date, {
                                      day: 'numeric',
                                      month: 'long',
                                      year: 'numeric',
                                  })
                                : 'Date not set'
                        }}</span>
                    </li>
                    <li class="flex items-center gap-2 text-muted-foreground">
                        <Church class="size-4 shrink-0" />
                        <span class="truncate">{{
                            form.wedding_parish || 'This parish'
                        }}</span>
                    </li>
                    <li class="flex items-center gap-2 text-muted-foreground">
                        <UserRound class="size-4 shrink-0" />
                        <span class="truncate">{{
                            form.officiant || 'Officiant not set'
                        }}</span>
                    </li>
                </ul>
            </section>
        </aside>

        <div
            class="sticky bottom-0 -mx-4 flex justify-end gap-2 border-t bg-background/90 px-4 py-3 backdrop-blur md:-mx-6 md:px-6 lg:col-span-3"
        >
            <Button variant="outline" as-child>
                <Link :href="application ? show(application.id) : index()"
                    >Cancel</Link
                >
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ application ? 'Save application' : 'Open application' }}
            </Button>
        </div>
    </form>
</template>
