<script setup>
import { computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import { confirmState, resolveConfirm } from '@/confirm';
import { btnPrimary, btnSecondary } from '@/ui';

const confirmClass = computed(() =>
    confirmState.tone === 'danger'
        ? 'inline-flex h-9 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-destructive px-4 text-sm font-medium text-white transition-colors hover:bg-destructive/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-destructive focus-visible:ring-offset-2'
        : btnPrimary,
);
</script>

<template>
    <Modal :show="confirmState.show" :title="confirmState.title" max-width="max-w-md" @close="resolveConfirm(false)">
        <p class="whitespace-pre-line text-sm leading-relaxed text-foreground">{{ confirmState.message }}</p>

        <template #footer>
            <button
                v-if="confirmState.cancelLabel"
                type="button"
                data-autofocus
                :class="btnSecondary"
                @click="resolveConfirm(false)"
            >
                {{ confirmState.cancelLabel }}
            </button>
            <button type="button" :class="confirmClass" :data-autofocus="!confirmState.cancelLabel || null" @click="resolveConfirm(true)">
                {{ confirmState.confirmLabel }}
            </button>
        </template>
    </Modal>
</template>
