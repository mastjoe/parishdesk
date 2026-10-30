<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CircleCheck,
    FileSpreadsheet,
    TriangleAlert,
    Upload,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/members';
import {
    create,
    destroy,
    store,
    upload as uploadRoute,
} from '@/routes/admin/members/import';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Members', href: index() },
            { title: 'Import', href: create() },
        ],
    },
});

const props = defineProps<{
    fields: { key: string; label: string; required: boolean }[];
    upload: {
        fileName: string;
        headers: string[];
        preview: Record<string, string>[];
        rowCount: number;
        suggested: Record<string, string>;
    } | null;
    result: {
        fileName: string;
        created: number;
        skipped: { line: number; name: string; reason: string }[];
    } | null;
    maxRows: number;
}>();

const step = computed(() =>
    props.result ? 'done' : props.upload ? 'map' : 'upload',
);

const steps = [
    { key: 'upload', label: 'Upload file' },
    { key: 'map', label: 'Match columns' },
    { key: 'done', label: 'Import' },
];

// Step 1: upload
const fileInput = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const uploadForm = useForm({ file: null as File | null });

const send = (file: File | undefined | null) => {
    if (!file) {
        return;
    }

    uploadForm.file = file;
    uploadForm.post(uploadRoute().url, { preserveScroll: true });
};

const onDrop = (event: DragEvent) => {
    dragging.value = false;
    send(event.dataTransfer?.files[0]);
};

// Step 2: mapping
const SKIP = '__skip__';
const importForm = useForm({
    mapping: { ...props.upload?.suggested } as Record<string, string>,
});

const mappedFields = computed(() =>
    props.fields.filter((field) => importForm.mapping[field.key]),
);

const missingRequired = computed(() =>
    props.fields.filter(
        (field) => field.required && !importForm.mapping[field.key],
    ),
);

const setMapping = (field: string, header: string) => {
    if (header === SKIP) {
        delete importForm.mapping[field];
    } else {
        importForm.mapping[field] = header;
    }
};

const runImport = () => importForm.post(store().url, { preserveScroll: true });

const startOver = () => router.delete(destroy().url);
</script>

<template>
    <Head title="Import members" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Import members"
            description="Bring in your existing register from a spreadsheet saved as CSV."
        />

        <ol class="flex flex-wrap items-center gap-2 text-sm">
            <li
                v-for="(item, position) in steps"
                :key="item.key"
                class="flex items-center gap-2"
            >
                <span
                    :class="
                        cn(
                            'flex size-6 items-center justify-center rounded-full text-xs font-semibold',
                            item.key === step
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground',
                        )
                    "
                    >{{ position + 1 }}</span
                >
                <span
                    :class="
                        item.key === step
                            ? 'font-medium'
                            : 'text-muted-foreground'
                    "
                    >{{ item.label }}</span
                >
                <span
                    v-if="position < steps.length - 1"
                    class="mx-1 h-px w-8 bg-border"
                />
            </li>
        </ol>

        <!-- Step 1 -->
        <section v-if="step === 'upload'" class="grid gap-6 lg:grid-cols-3">
            <div
                :class="
                    cn(
                        'flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed bg-card px-6 py-14 text-center transition-colors lg:col-span-2',
                        dragging && 'border-primary bg-primary/5',
                    )
                "
                @dragover.prevent="dragging = true"
                @dragleave="dragging = false"
                @drop.prevent="onDrop"
            >
                <span
                    class="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <Upload class="size-5" />
                </span>
                <div class="space-y-1">
                    <p class="font-medium">Drop a CSV file here</p>
                    <p class="text-sm text-muted-foreground">
                        Up to {{ maxRows.toLocaleString() }} rows and 5 MB.
                    </p>
                </div>
                <Button
                    type="button"
                    :disabled="uploadForm.processing"
                    @click="fileInput?.click()"
                >
                    Choose file
                </Button>
                <input
                    ref="fileInput"
                    type="file"
                    accept=".csv,text/csv"
                    class="hidden"
                    @change="
                        send(($event.target as HTMLInputElement).files?.[0])
                    "
                />
                <InputError :message="uploadForm.errors.file" />
            </div>
            <div
                class="flex flex-col gap-3 rounded-xl border bg-card p-5 text-sm"
            >
                <h2 class="font-semibold">Preparing the file</h2>
                <ul class="list-disc space-y-2 pl-4 text-muted-foreground">
                    <li>The first row must hold the column names.</li>
                    <li>
                        First name, last name and gender are required for every
                        row.
                    </li>
                    <li>
                        Write dates as 2026-10-30 or 30/10/2026 (day first).
                    </li>
                    <li>
                        Communities are matched by name. Create them first so
                        members land in the right one.
                    </li>
                    <li>
                        People already in the register (same email, or same name
                        and date of birth) are skipped.
                    </li>
                </ul>
            </div>
        </section>

        <!-- Step 2 -->
        <section
            v-else-if="step === 'map' && upload"
            class="flex flex-col gap-6"
        >
            <div
                class="flex flex-col gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center"
            >
                <FileSpreadsheet class="size-5 text-primary" />
                <p class="flex-1 text-sm">
                    <span class="font-medium">{{ upload.fileName }}</span>
                    <span class="text-muted-foreground">
                        · {{ upload.rowCount }} rows ·
                        {{ upload.headers.length }} columns</span
                    >
                </p>
                <Button variant="ghost" size="sm" @click="startOver"
                    >Use another file</Button
                >
            </div>

            <div class="grid gap-6 xl:grid-cols-5">
                <div class="rounded-xl border bg-card xl:col-span-2">
                    <header class="border-b px-5 py-4">
                        <h2 class="font-semibold">Match columns</h2>
                        <p class="text-sm text-muted-foreground">
                            We guessed from the column names. Check each one.
                        </p>
                    </header>
                    <ul class="divide-y">
                        <li
                            v-for="field in fields"
                            :key="field.key"
                            class="flex flex-col gap-1.5 px-5 py-2.5"
                        >
                            <div class="flex items-center gap-3">
                                <span class="flex-1 text-sm">
                                    {{ field.label }}
                                    <span
                                        v-if="field.required"
                                        class="text-destructive"
                                        >*</span
                                    >
                                </span>
                                <Select
                                    :model-value="
                                        importForm.mapping[field.key] ?? SKIP
                                    "
                                    @update:model-value="
                                        setMapping(field.key, String($event))
                                    "
                                >
                                    <SelectTrigger size="sm" class="w-48">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="SKIP"
                                            >Don't import</SelectItem
                                        >
                                        <SelectItem
                                            v-for="header in upload.headers"
                                            :key="header"
                                            :value="header"
                                        >
                                            {{ header }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <InputError
                                :message="
                                    importForm.errors[
                                        `mapping.${field.key}` as keyof typeof importForm.errors
                                    ]
                                "
                            />
                        </li>
                    </ul>
                </div>

                <div class="flex flex-col gap-4 xl:col-span-3">
                    <div class="rounded-xl border bg-card">
                        <header class="border-b px-5 py-4">
                            <h2 class="font-semibold">Preview</h2>
                            <p class="text-sm text-muted-foreground">
                                The first rows as they will be imported.
                            </p>
                        </header>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/40">
                                    <tr>
                                        <th
                                            v-for="field in mappedFields"
                                            :key="field.key"
                                            class="h-9 px-4 text-left text-xs font-medium whitespace-nowrap text-muted-foreground uppercase"
                                        >
                                            {{ field.label }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr
                                        v-for="(
                                            row, rowIndex
                                        ) in upload.preview"
                                        :key="rowIndex"
                                    >
                                        <td
                                            v-for="field in mappedFields"
                                            :key="field.key"
                                            class="px-4 py-2 whitespace-nowrap"
                                        >
                                            {{
                                                row[
                                                    importForm.mapping[
                                                        field.key
                                                    ]
                                                ] || '—'
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center"
                    >
                        <p class="flex-1 text-sm text-muted-foreground">
                            <template v-if="missingRequired.length">
                                Choose a column for
                                {{
                                    missingRequired
                                        .map((field) => field.label)
                                        .join(', ')
                                }}
                                to continue.
                            </template>
                            <template v-else>
                                Ready to import up to
                                {{ upload.rowCount }} members.
                            </template>
                        </p>
                        <Button
                            :disabled="
                                importForm.processing ||
                                missingRequired.length > 0
                            "
                            @click="runImport"
                        >
                            {{
                                importForm.processing
                                    ? 'Importing…'
                                    : 'Import members'
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Step 3 -->
        <section v-else-if="result" class="flex flex-col gap-6">
            <div
                class="flex flex-col items-center gap-3 rounded-xl border bg-card px-6 py-10 text-center"
            >
                <span
                    class="flex size-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600"
                >
                    <CircleCheck class="size-6" />
                </span>
                <div class="space-y-1">
                    <p class="text-lg font-semibold">
                        {{ result.created }}
                        {{ result.created === 1 ? 'member' : 'members' }}
                        imported
                    </p>
                    <p class="text-sm text-muted-foreground">
                        From {{ result.fileName }}
                        <template v-if="result.skipped.length">
                            · {{ result.skipped.length }} rows skipped</template
                        >
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="startOver"
                        >Import another file</Button
                    >
                    <Button as-child>
                        <Link :href="index()">Go to members</Link>
                    </Button>
                </div>
            </div>

            <div v-if="result.skipped.length" class="rounded-xl border bg-card">
                <header class="flex items-center gap-2 border-b px-5 py-4">
                    <TriangleAlert class="size-4 text-amber-500" />
                    <h2 class="font-semibold">Skipped rows</h2>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40">
                            <tr
                                class="text-left text-xs text-muted-foreground uppercase"
                            >
                                <th class="h-9 px-4 font-medium">Line</th>
                                <th class="h-9 px-4 font-medium">Name</th>
                                <th class="h-9 px-4 font-medium">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in result.skipped" :key="row.line">
                                <td class="px-4 py-2 tabular-nums">
                                    {{ row.line }}
                                </td>
                                <td class="px-4 py-2">{{ row.name }}</td>
                                <td class="px-4 py-2 text-muted-foreground">
                                    {{ row.reason }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</template>
