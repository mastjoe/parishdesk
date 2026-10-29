<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PermissionMatrix from '@/components/access/PermissionMatrix.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { index, store, update } from '@/routes/admin/roles';
import type { CatalogueGroup } from '@/types';

type RoleData = {
    id: number;
    name: string;
    description: string | null;
    color: string;
    permissions: string[];
};

const props = defineProps<{
    catalogue: CatalogueGroup[];
    colors: string[];
    grantable: string[];
    role?: RoleData;
}>();

const form = useForm({
    name: props.role?.name ?? '',
    description: props.role?.description ?? '',
    color: props.role?.color ?? 'blue',
    permissions: props.role?.permissions ?? ([] as string[]),
});

const swatches: Record<string, string> = {
    slate: 'bg-slate-500',
    blue: 'bg-blue-500',
    green: 'bg-emerald-500',
    purple: 'bg-violet-500',
    red: 'bg-red-500',
    amber: 'bg-amber-500',
    rose: 'bg-rose-500',
    teal: 'bg-teal-500',
};

const submit = () => {
    form.submit(props.role ? update(props.role.id) : store());
};
</script>

<template>
    <form class="flex flex-col gap-8" @submit.prevent="submit">
        <section
            class="grid gap-6 rounded-xl border bg-card p-5 md:grid-cols-2"
        >
            <div class="grid gap-2">
                <Label for="name">Role name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    required
                    maxlength="60"
                    placeholder="e.g. Parish Secretary"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label>Badge colour</Label>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="color in colors"
                        :key="color"
                        type="button"
                        :aria-label="color"
                        :aria-pressed="form.color === color"
                        :class="
                            cn(
                                'flex size-7 items-center justify-center rounded-full text-white ring-offset-2 ring-offset-background transition',
                                swatches[color],
                                form.color === color && 'ring-2 ring-ring',
                            )
                        "
                        @click="form.color = color"
                    >
                        <Check v-if="form.color === color" class="size-3.5" />
                    </button>
                    <StatusBadge :tone="form.color" class="ml-2">
                        {{ form.name || 'Preview' }}
                    </StatusBadge>
                </div>
                <InputError :message="form.errors.color" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="description">Description</Label>
                <Input
                    id="description"
                    v-model="form.description"
                    maxlength="255"
                    placeholder="What is this role responsible for?"
                />
                <InputError :message="form.errors.description" />
            </div>
        </section>

        <section class="flex flex-col gap-3">
            <div>
                <h2 class="text-base font-semibold">Permissions</h2>
                <p class="text-sm text-muted-foreground">
                    Tick exactly what people with this role may do. Anything
                    left unticked is denied. Permissions you do not hold
                    yourself cannot be granted.
                </p>
            </div>
            <InputError :message="form.errors.permissions" />
            <PermissionMatrix
                v-model="form.permissions"
                :catalogue="catalogue"
                :grantable="grantable"
            />
        </section>

        <div
            class="sticky bottom-0 -mx-4 flex items-center justify-end gap-2 border-t bg-background/90 px-4 py-3 backdrop-blur md:-mx-6 md:px-6"
        >
            <Button variant="outline" as-child>
                <Link :href="index()">Cancel</Link>
            </Button>
            <Button
                type="submit"
                :disabled="form.processing"
                data-test="save-role"
            >
                {{ role ? 'Save changes' : 'Create role' }}
            </Button>
        </div>
    </form>
</template>
