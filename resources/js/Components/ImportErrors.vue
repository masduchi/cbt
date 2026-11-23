<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();
const errors = computed(() => page.props.flash?.import_errors ?? []);
const visible = ref(false);

watch(
    errors,
    (value) => {
        visible.value = Array.isArray(value) && value.length > 0;
    },
    { immediate: true },
);
</script>

<template>
    <div v-if="visible && errors.length" class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-warning" role="status">
        <div class="flex items-start justify-between gap-3">
            <p class="flex items-center gap-2 text-sm font-semibold">
                <i class="fas fa-exclamation-triangle h-4 w-4 shrink-0" ></i> Sebagian baris tidak diimpor
            </p>
            <button type="button" class="rounded p-0.5 text-warning transition hover:bg-warning/15" aria-label="Tutup rincian" @click="visible = false">
                <i class="fas fa-times h-4 w-4" ></i>
            </button>
        </div>
        <ul class="mt-2 max-h-52 space-y-1 overflow-y-auto text-xs">
            <li v-for="(error, index) in errors" :key="index">{{ error }}</li>
        </ul>
    </div>
</template>
