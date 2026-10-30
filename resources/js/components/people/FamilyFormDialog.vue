<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import OptionSelect from '@/components/app/OptionSelect.vue';
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
import { store, update } from '@/routes/admin/families';
import type { CommunityOption } from '@/types';

type FamilyFields = {
    id: number;
    name: string;
    community_id: number | null;
    address: string | null;
    home_phone: string | null;
    notes: string | null;
};

const props = defineProps<{
    /** The family being edited; omit to create one. */
    family?: FamilyFields | null;
    communities: CommunityOption[];
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    name: '',
    community_id: null as string | null,
    address: '',
    home_phone: '',
    notes: '',
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.defaults({
        name: props.family?.name ?? '',
        community_id: props.family?.community_id
            ? String(props.family.community_id)
            : null,
        address: props.family?.address ?? '',
        home_phone: props.family?.home_phone ?? '',
        notes: props.family?.notes ?? '',
    });
    form.reset();
});

const communityOptions = computed(() =>
    props.communities.map((community) => ({
        value: String(community.id),
        label: community.name,
    })),
);

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (props.family) {
        form.submit(update(props.family.id), options);
    } else {
        form.submit(store(), options);
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{
                    family ? 'Edit family' : 'New family'
                }}</DialogTitle>
                <DialogDescription>
                    A household in the parish. Add its members once it is
                    created.
                </DialogDescription>
            </DialogHeader>

            <form id="family-form" class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="family-name">Family name</Label>
                    <Input
                        id="family-name"
                        v-model="form.name"
                        placeholder="Okafor Family"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="family-community">Community</Label>
                        <OptionSelect
                            id="family-community"
                            v-model="form.community_id"
                            :options="communityOptions"
                            none-label="No community"
                        />
                        <InputError :message="form.errors.community_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="family-phone">Home phone</Label>
                        <Input
                            id="family-phone"
                            v-model="form.home_phone"
                            type="tel"
                        />
                        <InputError :message="form.errors.home_phone" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="family-address">Address</Label>
                    <Input id="family-address" v-model="form.address" />
                    <InputError :message="form.errors.address" />
                </div>
                <div class="grid gap-2">
                    <Label for="family-notes">Notes</Label>
                    <Textarea id="family-notes" v-model="form.notes" rows="3" />
                    <InputError :message="form.errors.notes" />
                </div>
            </form>

            <DialogFooter>
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="family-form"
                    :disabled="form.processing"
                >
                    {{ family ? 'Save family' : 'Create family' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
