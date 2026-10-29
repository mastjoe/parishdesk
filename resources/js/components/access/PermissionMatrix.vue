<script setup lang="ts">
import { Lock, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { CatalogueGroup } from '@/types';

const props = withDefaults(
    defineProps<{
        catalogue: CatalogueGroup[];
        /** Permissions the current user may hand out. Others are disabled. */
        grantable?: string[];
        /** Permissions already held another way, mapped to their source. */
        inherited?: Record<string, string[]>;
        disabled?: boolean;
    }>(),
    {
        grantable: undefined,
        inherited: () => ({}),
        disabled: false,
    },
);

const selected = defineModel<string[]>({ required: true });
const query = ref('');

const selectedSet = computed(() => new Set(selected.value));
const grantableSet = computed(() =>
    props.grantable ? new Set(props.grantable) : null,
);

const isLocked = (name: string): boolean =>
    props.disabled ||
    (grantableSet.value !== null && !grantableSet.value.has(name));

const groups = computed(() => {
    const term = query.value.trim().toLowerCase();

    return props.catalogue
        .map((group) => ({
            ...group,
            permissions: group.permissions.filter(
                (permission) =>
                    !term ||
                    permission.label.toLowerCase().includes(term) ||
                    permission.name.includes(term) ||
                    group.label.toLowerCase().includes(term),
            ),
        }))
        .filter((group) => group.permissions.length > 0);
});

const toggle = (name: string, checked: boolean) => {
    const next = new Set(selected.value);

    if (checked) {
        next.add(name);
    } else {
        next.delete(name);
    }

    selected.value = [...next];
};

const groupState = (group: CatalogueGroup): boolean | 'indeterminate' => {
    const count = group.permissions.filter((permission) =>
        selectedSet.value.has(permission.name),
    ).length;

    if (count === 0) {
        return false;
    }

    return count === group.permissions.length ? true : 'indeterminate';
};

const toggleGroup = (group: CatalogueGroup, checked: boolean) => {
    const next = new Set(selected.value);

    for (const permission of group.permissions) {
        if (isLocked(permission.name)) {
            continue;
        }

        if (checked) {
            next.add(permission.name);
        } else {
            next.delete(permission.name);
        }
    }

    selected.value = [...next];
};

const groupLocked = (group: CatalogueGroup): boolean =>
    group.permissions.every((permission) => isLocked(permission.name));
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="relative w-full sm:max-w-xs">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="query"
                    type="search"
                    placeholder="Filter permissions…"
                    class="pl-9"
                />
            </div>
            <p class="text-sm text-muted-foreground">
                <span class="font-medium text-foreground tabular-nums">{{
                    selected.length
                }}</span>
                selected
            </p>
        </div>

        <section
            v-for="group in groups"
            :key="group.key"
            class="overflow-hidden rounded-xl border bg-card"
        >
            <header
                class="flex items-start gap-3 border-b bg-muted/40 px-4 py-3"
            >
                <Checkbox
                    :id="`group-${group.key}`"
                    class="mt-0.5"
                    :model-value="groupState(group)"
                    :disabled="groupLocked(group)"
                    @update:model-value="toggleGroup(group, $event === true)"
                />
                <label :for="`group-${group.key}`" class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold">{{
                        group.label
                    }}</span>
                    <span class="block text-xs text-muted-foreground">{{
                        group.description
                    }}</span>
                </label>
            </header>
            <ul class="grid divide-y sm:grid-cols-2 sm:divide-y-0">
                <li
                    v-for="permission in group.permissions"
                    :key="permission.name"
                    class="sm:border-b sm:odd:border-r sm:[&:nth-last-child(-n+2)]:border-b-0"
                >
                    <label
                        :for="`permission-${permission.name}`"
                        :class="
                            cn(
                                'flex h-full items-start gap-3 px-4 py-3 transition-colors',
                                isLocked(permission.name)
                                    ? 'cursor-not-allowed opacity-60'
                                    : 'cursor-pointer hover:bg-muted/30',
                            )
                        "
                    >
                        <Checkbox
                            :id="`permission-${permission.name}`"
                            class="mt-0.5"
                            :model-value="
                                selectedSet.has(permission.name) ||
                                !!inherited[permission.name]
                            "
                            :disabled="
                                isLocked(permission.name) ||
                                !!inherited[permission.name]
                            "
                            :data-test="`permission-${permission.name}`"
                            @update:model-value="
                                toggle(permission.name, $event === true)
                            "
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium">{{
                                permission.label
                            }}</span>
                            <span class="block text-xs text-muted-foreground">{{
                                permission.description
                            }}</span>
                            <span
                                v-if="inherited[permission.name]"
                                class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-primary"
                            >
                                <Lock class="size-3" />
                                via {{ inherited[permission.name].join(', ') }}
                            </span>
                            <code
                                class="mt-1 block font-mono text-[11px] text-muted-foreground/80"
                                >{{ permission.name }}</code
                            >
                        </span>
                    </label>
                </li>
            </ul>
        </section>

        <p
            v-if="groups.length === 0"
            class="rounded-xl border border-dashed py-10 text-center text-sm text-muted-foreground"
        >
            No permissions match “{{ query }}”.
        </p>
    </div>
</template>
