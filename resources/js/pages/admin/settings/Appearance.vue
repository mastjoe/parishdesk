<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Check, ImageUp, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { edit, update } from '@/routes/admin/settings/appearance';

type Preset = { key: string; label: string; swatch: string };

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Appearance', href: edit() }],
    },
});

const props = defineProps<{
    settings: {
        theme: string;
        customColor: string | null;
        logoUrl: string | null;
    };
    presets: Preset[];
}>();

const form = useForm({
    theme: props.settings.theme,
    custom_color: props.settings.customColor ?? '#2f5fb3',
    logo: null as File | null,
    remove_logo: false,
});

const logoPreview = ref<string | null>(props.settings.logoUrl);

let savedTheme: string | undefined;

onMounted(() => {
    savedTheme = document.documentElement.dataset.theme;
});

/**
 * Preview presets live by swapping data-theme. A custom colour is only
 * generated on the server, so it previews as its swatch until saved.
 */
watch(
    () => form.theme,
    (theme) => {
        if (theme !== 'custom') {
            document.documentElement.dataset.theme = theme;
        }
    },
);

onBeforeUnmount(() => {
    if (savedTheme) {
        document.documentElement.dataset.theme = savedTheme;
    }
});

const choose = (key: string) => (form.theme = key);

const onLogo = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.logo = file;
    form.remove_logo = false;
    logoPreview.value = file
        ? URL.createObjectURL(file)
        : props.settings.logoUrl;
};

const removeLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = null;
};

const isCustom = computed(() => form.theme === 'custom');

const submit = () =>
    form.submit(update(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            if (form.theme === 'custom') {
                // The custom palette CSS is rendered by the server template.
                window.location.reload();

                return;
            }

            savedTheme = form.theme;
        },
    });
</script>

<template>
    <Head title="Appearance" />

    <form class="flex flex-col gap-8" @submit.prevent="submit">
        <section class="flex flex-col gap-4">
            <Heading
                variant="small"
                title="Theme colour"
                description="Sets the primary colour of the admin dashboard and the member portal for everyone. Each person still chooses light or dark mode for themselves."
            />

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <button
                    v-for="preset in presets"
                    :key="preset.key"
                    type="button"
                    :aria-pressed="form.theme === preset.key"
                    :class="
                        cn(
                            'flex flex-col gap-3 rounded-xl border bg-card p-3 text-left transition hover:shadow-sm',
                            form.theme === preset.key &&
                                'border-primary ring-2 ring-primary/30',
                        )
                    "
                    :data-test="`theme-${preset.key}`"
                    @click="choose(preset.key)"
                >
                    <span
                        class="flex h-12 items-center justify-center rounded-lg text-white"
                        :style="{ backgroundColor: preset.swatch }"
                    >
                        <Check
                            v-if="form.theme === preset.key"
                            class="size-5"
                        />
                    </span>
                    <span class="text-sm font-medium">{{ preset.label }}</span>
                </button>

                <button
                    type="button"
                    :aria-pressed="isCustom"
                    :class="
                        cn(
                            'flex flex-col gap-3 rounded-xl border bg-card p-3 text-left transition hover:shadow-sm',
                            isCustom && 'border-primary ring-2 ring-primary/30',
                        )
                    "
                    @click="choose('custom')"
                >
                    <span
                        class="flex h-12 items-center justify-center rounded-lg text-white"
                        :style="{ backgroundColor: form.custom_color }"
                    >
                        <Check v-if="isCustom" class="size-5" />
                    </span>
                    <span class="text-sm font-medium">Custom colour</span>
                </button>
            </div>

            <div
                v-if="isCustom"
                class="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-4"
            >
                <input
                    v-model="form.custom_color"
                    type="color"
                    class="size-10 cursor-pointer rounded-md border bg-transparent"
                    aria-label="Pick a colour"
                />
                <Input
                    v-model="form.custom_color"
                    class="w-32 font-mono"
                    maxlength="7"
                />
                <p class="text-sm text-muted-foreground">
                    We adjust the lightness automatically so text stays readable
                    in light and dark mode.
                </p>
                <InputError
                    class="w-full"
                    :message="form.errors.custom_color"
                />
            </div>
            <InputError :message="form.errors.theme" />

            <div class="rounded-xl border bg-card p-5">
                <p
                    class="mb-3 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Preview
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <Button type="button">Primary action</Button>
                    <Button type="button" variant="outline">Secondary</Button>
                    <span
                        class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary"
                        >Badge</span
                    >
                    <span class="text-sm font-medium text-primary"
                        >Link text</span
                    >
                </div>
            </div>
        </section>

        <section class="flex flex-col gap-4">
            <Heading
                variant="small"
                title="Parish logo"
                description="A square PNG, JPG, WebP or SVG up to 2 MB works best."
            />
            <div class="flex items-center gap-4 rounded-xl border bg-card p-4">
                <div
                    class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border bg-muted"
                >
                    <img
                        v-if="logoPreview"
                        :src="logoPreview"
                        alt="Parish logo"
                        class="size-full object-cover"
                    />
                    <ImageUp v-else class="size-6 text-muted-foreground" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        as="label"
                        class="cursor-pointer"
                    >
                        <ImageUp class="size-4" /> Upload
                        <input
                            type="file"
                            accept="image/png,image/jpeg,image/webp,image/svg+xml"
                            class="sr-only"
                            @change="onLogo"
                        />
                    </Button>
                    <Button
                        v-if="logoPreview"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="removeLogo"
                    >
                        <Trash2 class="size-4" /> Remove
                    </Button>
                </div>
            </div>
            <InputError :message="form.errors.logo" />
        </section>

        <div class="flex justify-end">
            <Button
                type="submit"
                :disabled="form.processing"
                data-test="save-appearance"
            >
                Save appearance
            </Button>
        </div>
    </form>
</template>
