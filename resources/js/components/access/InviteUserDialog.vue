<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { MailPlus } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PermissionMatrix from '@/components/access/PermissionMatrix.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/admin/invitations';
import type { CatalogueGroup, RoleBadge } from '@/types';

defineProps<{
    roles: RoleBadge[];
    catalogue: CatalogueGroup[];
    grantable: string[];
}>();

const open = ref(false);
const showPermissions = ref(false);

const form = useForm({
    name: '',
    email: '',
    roles: [] as string[],
    permissions: [] as string[],
});

const toggleRole = (name: string, checked: boolean) => {
    form.roles = checked
        ? [...form.roles, name]
        : form.roles.filter((role) => role !== name);
};

const submit = () => {
    form.submit(store(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            open.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button data-test="invite-user">
                <MailPlus class="size-4" /> Invite user
            </Button>
        </DialogTrigger>
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Invite a staff user</DialogTitle>
                <DialogDescription>
                    They will get an email with a link to set their password.
                    The link expires in 7 days.
                </DialogDescription>
            </DialogHeader>

            <form id="invite-form" class="grid gap-5" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="invite-name">Full name</Label>
                        <Input id="invite-name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="invite-email">Email address</Label>
                        <Input
                            id="invite-email"
                            v-model="form.email"
                            type="email"
                            required
                        />
                        <InputError :message="form.errors.email" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label>Roles</Label>
                    <p
                        v-if="roles.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No roles exist yet. You can still grant individual
                        permissions below.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="role in roles"
                            :key="role.name"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-sm has-data-[state=checked]:border-primary has-data-[state=checked]:bg-primary/5"
                        >
                            <Checkbox
                                :model-value="form.roles.includes(role.name)"
                                @update:model-value="
                                    toggleRole(role.name, $event === true)
                                "
                            />
                            {{
                                role.name === 'super-admin'
                                    ? 'Super Admin'
                                    : role.name
                            }}
                        </label>
                    </div>
                    <InputError :message="form.errors.roles" />
                </div>

                <div class="grid gap-2">
                    <button
                        type="button"
                        class="w-fit text-sm font-medium text-primary hover:underline"
                        @click="showPermissions = !showPermissions"
                    >
                        {{ showPermissions ? 'Hide' : 'Add' }} individual
                        permissions
                        <span v-if="form.permissions.length"
                            >({{ form.permissions.length }})</span
                        >
                    </button>
                    <PermissionMatrix
                        v-if="showPermissions"
                        v-model="form.permissions"
                        :catalogue="catalogue"
                        :grantable="grantable"
                    />
                    <InputError :message="form.errors.permissions" />
                </div>
            </form>

            <DialogFooter>
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="invite-form"
                    :disabled="form.processing"
                >
                    Send invitation
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
