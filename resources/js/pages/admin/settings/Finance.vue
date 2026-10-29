<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, update } from '@/routes/admin/settings/finance';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Finance settings', href: edit() }],
    },
});

const props = defineProps<{
    settings: {
        currency: string;
        timezone: string;
        fiscal_year_start_month: number;
        bank_name: string | null;
        bank_account_name: string | null;
        bank_account_number: string | null;
    };
    timezones: string[];
}>();

const form = useForm(
    Object.fromEntries(
        Object.entries(props.settings).map(([key, value]) => [
            key,
            value ?? '',
        ]),
    ) as {
        [K in keyof typeof props.settings]: NonNullable<
            (typeof props.settings)[K]
        >;
    },
);

const months = Array.from({ length: 12 }, (_, index) => ({
    value: index + 1,
    label: new Intl.DateTimeFormat(undefined, { month: 'long' }).format(
        new Date(2000, index, 1),
    ),
}));

const nativeSelect =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const submit = () => form.submit(update(), { preserveScroll: true });
</script>

<template>
    <Head title="Finance settings" />

    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <Heading
            variant="small"
            title="Defaults"
            description="Used for every amount, report and date across ParishDesk."
        />

        <div class="grid gap-5 rounded-xl border bg-card p-5 sm:grid-cols-3">
            <div class="grid gap-2">
                <Label for="currency">Currency</Label>
                <Input
                    id="currency"
                    v-model="form.currency"
                    maxlength="3"
                    class="uppercase"
                    placeholder="NGN"
                    @input="form.currency = form.currency.toUpperCase()"
                />
                <InputError :message="form.errors.currency" />
            </div>
            <div class="grid gap-2">
                <Label for="fiscal">Fiscal year starts</Label>
                <select
                    id="fiscal"
                    v-model.number="form.fiscal_year_start_month"
                    :class="nativeSelect"
                >
                    <option
                        v-for="month in months"
                        :key="month.value"
                        :value="month.value"
                    >
                        {{ month.label }}
                    </option>
                </select>
                <InputError :message="form.errors.fiscal_year_start_month" />
            </div>
            <div class="grid gap-2">
                <Label for="timezone">Timezone</Label>
                <select
                    id="timezone"
                    v-model="form.timezone"
                    :class="nativeSelect"
                >
                    <option v-for="zone in timezones" :key="zone" :value="zone">
                        {{ zone }}
                    </option>
                </select>
                <InputError :message="form.errors.timezone" />
            </div>
        </div>

        <Heading
            variant="small"
            title="Bank details"
            description="Shown to parishioners when they pay by bank transfer."
        />

        <div class="grid gap-5 rounded-xl border bg-card p-5 sm:grid-cols-3">
            <div class="grid gap-2">
                <Label for="bank_name">Bank</Label>
                <Input id="bank_name" v-model="form.bank_name" />
                <InputError :message="form.errors.bank_name" />
            </div>
            <div class="grid gap-2">
                <Label for="bank_account_name">Account name</Label>
                <Input
                    id="bank_account_name"
                    v-model="form.bank_account_name"
                />
                <InputError :message="form.errors.bank_account_name" />
            </div>
            <div class="grid gap-2">
                <Label for="bank_account_number">Account number</Label>
                <Input
                    id="bank_account_number"
                    v-model="form.bank_account_number"
                    inputmode="numeric"
                />
                <InputError :message="form.errors.bank_account_number" />
            </div>
        </div>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing"
                >Save settings</Button
            >
        </div>
    </form>
</template>
