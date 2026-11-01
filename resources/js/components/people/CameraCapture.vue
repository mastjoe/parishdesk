<script setup lang="ts">
import { Camera, CameraOff, RefreshCw, RotateCcw } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Take a photo with the device camera, e.g. a member's portrait at the
 * parish office desk. Emits the picture as a JPEG file.
 */
const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
    captured: [file: File];
}>();

/** The longest side of the saved photo, in pixels. */
const MAX_SIZE = 1280;

const video = ref<HTMLVideoElement | null>(null);
const stream = ref<MediaStream | null>(null);
const facingMode = ref<'user' | 'environment'>('user');
const canSwitch = ref(false);
const starting = ref(false);
const error = ref<string | null>(null);
const shot = ref<{ url: string; blob: Blob } | null>(null);

const stop = () => {
    stream.value?.getTracks().forEach((track) => track.stop());
    stream.value = null;
};

const clearShot = () => {
    if (shot.value) {
        URL.revokeObjectURL(shot.value.url);
    }

    shot.value = null;
};

const start = async () => {
    stop();
    error.value = null;

    if (!navigator.mediaDevices?.getUserMedia) {
        error.value =
            'This browser cannot use the camera here. Cameras only work on a secure (https) address.';

        return;
    }

    starting.value = true;

    try {
        stream.value = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: facingMode.value,
                width: { ideal: 1280 },
                height: { ideal: 1280 },
            },
            audio: false,
        });

        const devices = await navigator.mediaDevices.enumerateDevices();
        canSwitch.value =
            devices.filter((device) => device.kind === 'videoinput').length > 1;
    } catch (exception) {
        const name = exception instanceof DOMException ? exception.name : '';

        error.value =
            name === 'NotAllowedError'
                ? 'Camera access was blocked. Allow the camera for this site in your browser settings, then try again.'
                : name === 'NotFoundError' || name === 'OverconstrainedError'
                  ? 'No camera was found on this device.'
                  : 'The camera could not be started. It may be in use by another app.';
    } finally {
        starting.value = false;
    }
};

const switchCamera = () => {
    facingMode.value = facingMode.value === 'user' ? 'environment' : 'user';
    start();
};

const capture = () => {
    const source = video.value;

    if (!source || !source.videoWidth) {
        return;
    }

    const scale = Math.min(
        1,
        MAX_SIZE / Math.max(source.videoWidth, source.videoHeight),
    );
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(source.videoWidth * scale);
    canvas.height = Math.round(source.videoHeight * scale);

    const context = canvas.getContext('2d');

    if (!context) {
        return;
    }

    // The preview of the front camera is mirrored, so mirror the photo to match what was seen.
    if (facingMode.value === 'user') {
        context.translate(canvas.width, 0);
        context.scale(-1, 1);
    }

    context.drawImage(source, 0, 0, canvas.width, canvas.height);

    canvas.toBlob(
        (blob) => {
            if (blob) {
                clearShot();
                shot.value = { url: URL.createObjectURL(blob), blob };
                stop();
            }
        },
        'image/jpeg',
        0.9,
    );
};

const retake = () => {
    clearShot();
    start();
};

const usePhoto = () => {
    if (!shot.value) {
        return;
    }

    emit(
        'captured',
        new File([shot.value.blob], `photo-${Date.now()}.jpg`, {
            type: 'image/jpeg',
        }),
    );
    open.value = false;
};

// The video element mounts with the dialog and again after a retake, so attach the stream whenever both exist.
watch([video, stream], ([element, current]) => {
    if (element && current) {
        element.srcObject = current;
    }
});

// The camera only runs while the dialog is open.
watch(open, (isOpen) => {
    if (isOpen) {
        clearShot();
        start();
    } else {
        stop();
        clearShot();
    }
});

onBeforeUnmount(() => {
    stop();
    clearShot();
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Take a photo</DialogTitle>
                <DialogDescription>
                    Ask the member to face the camera in good light, with their
                    face in the circle.
                </DialogDescription>
            </DialogHeader>

            <div
                class="relative aspect-square w-full overflow-hidden rounded-lg bg-muted"
            >
                <img
                    v-if="shot"
                    :src="shot.url"
                    alt="The photo just taken"
                    class="size-full object-cover"
                />
                <template v-else>
                    <video
                        ref="video"
                        autoplay
                        playsinline
                        muted
                        :class="[
                            'size-full object-cover',
                            facingMode === 'user' && '-scale-x-100',
                        ]"
                    />
                    <!-- A guide for framing the face, matching the round avatar -->
                    <div
                        v-if="stream"
                        class="pointer-events-none absolute inset-[12%] rounded-full border-2 border-dashed border-white/70"
                    />
                    <div
                        v-if="error || starting"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center text-sm text-muted-foreground"
                    >
                        <CameraOff v-if="error" class="size-8" />
                        <Camera v-else class="size-8 animate-pulse" />
                        <p>{{ error ?? 'Starting the camera…' }}</p>
                        <Button
                            v-if="error"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="start"
                        >
                            Try again
                        </Button>
                    </div>
                </template>
            </div>

            <DialogFooter class="gap-2 sm:justify-between">
                <Button
                    v-if="!shot && canSwitch"
                    type="button"
                    variant="outline"
                    :disabled="!stream"
                    @click="switchCamera"
                >
                    <RefreshCw class="size-4" /> Switch camera
                </Button>
                <span v-else />
                <div class="flex gap-2">
                    <template v-if="shot">
                        <Button type="button" variant="outline" @click="retake">
                            <RotateCcw class="size-4" /> Retake
                        </Button>
                        <Button type="button" @click="usePhoto"
                            >Use this photo</Button
                        >
                    </template>
                    <Button
                        v-else
                        type="button"
                        :disabled="!stream"
                        @click="capture"
                    >
                        <Camera class="size-4" /> Capture
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
