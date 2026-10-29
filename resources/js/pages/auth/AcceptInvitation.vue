<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Accept your invitation',
        description: 'Choose a password to finish setting up your account',
    },
});

defineProps<{
    invitation: { name: string; email: string };
    parishName: string;
    passwordRules: string;
}>();

/** The signed URL, including its signature, is also where the form posts. */
const action = usePage().url;
</script>

<template>
    <Head title="Accept invitation" />

    <Form
        :action="action"
        method="post"
        :reset-on-error="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-6">
            <p class="text-center text-sm text-muted-foreground">
                Welcome,
                <span class="font-medium text-foreground">{{
                    invitation.name
                }}</span
                >. You have been invited to the {{ parishName }} team.
            </p>

            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    :model-value="invitation.email"
                    readonly
                />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    autofocus
                    placeholder="Password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                />
            </div>

            <Button
                type="submit"
                class="w-full"
                :disabled="processing"
                data-test="accept-invitation"
            >
                <Spinner v-if="processing" />
                Create my account
            </Button>
        </div>
    </Form>
</template>
