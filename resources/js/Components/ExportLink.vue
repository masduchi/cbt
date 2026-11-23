<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { btnPrimary, btnSecondary } from '@/ui';

const props = defineProps({
    href: { type: String, required: true },
    label: { type: String, default: 'Ekspor' },
    icon: { type: String, default: 'download' },
    busyLabel: { type: String, default: 'Menyiapkan…' },
    variant: { type: String, default: 'secondary' },
});

const busy = ref(false);
let timer = null;

/**
 * Berkas dibuat di server, jadi tautan biasa tidak punya status proses.
 * Beri umpan balik singkat agar pengguna tahu unduhan sedang disiapkan.
 */
function start() {
    if (busy.value) {
        return;
    }

    busy.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => {
        busy.value = false;
    }, 6000);
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <a
        :href="props.href"
        :class="props.variant === 'primary' ? btnPrimary : btnSecondary"
        :aria-busy="busy"
        :title="props.label"
        @click="start"
    >
        <i
            v-if="busy"
            class="fas fa-spinner h-4 w-4 animate-spin"
            aria-hidden="true"
        ></i>
        <i v-else :class="'fas fa-' + props.icon" class="h-4 w-4" aria-hidden="true"></i>
        {{ busy ? props.busyLabel : props.label }}
    </a>
</template>
