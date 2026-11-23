<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    maxWidth: { type: String, default: 'max-w-lg' },
});

const emit = defineEmits(['close']);

const panel = ref(null);
const titleId = useId();

watch(
    () => props.show,
    async (visible) => {
        document.body.style.overflow = visible ? 'hidden' : '';

        if (visible) {
            await nextTick();
            panel.value?.querySelector('[data-autofocus]')?.focus();
        }
    },
);

function onKeydown(event) {
    if (event.key === 'Escape' && props.show) {
        emit('close');
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:items-center">
            <div class="fixed inset-0 bg-foreground/40" aria-hidden="true" @click="emit('close')" />
            <div
                ref="panel"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="titleId"
                tabindex="-1"
                class="relative my-8 w-full rounded-xl bg-card shadow-modal focus:outline-none"
                :class="maxWidth"
            >
                <div class="flex items-start justify-between gap-4 px-6 pb-1.5 pt-6">
                    <h2 :id="titleId" class="text-lg font-bold text-foreground">{{ title }}</h2>
                    <button
                        type="button"
                        data-autofocus
                        class="rounded-md p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        aria-label="Tutup dialog"
                        @click="emit('close')"
                    >
                        <i class="fas fa-times text-sm" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="px-6 pb-6 pt-4">
                    <slot />
                </div>
                <div
                    v-if="$slots.footer"
                    class="sticky bottom-0 flex items-center justify-end gap-2 rounded-b-xl border-t border-border bg-card px-6 py-3.5 shadow-[0_-6px_16px_-12px_rgba(0,0,0,0.35)]"
                >
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
