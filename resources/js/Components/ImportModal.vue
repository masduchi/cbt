<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import { btnPrimary, btnSecondary, labelClass } from '@/ui';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Impor Data' },
    action: { type: String, required: true },
    description: { type: String, default: '' },
    columns: { type: Array, default: () => [] },
    templateUrl: { type: String, default: '' },
    params: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close']);

const form = useForm({ file: null });
const fileName = ref('');

watch(
    () => props.show,
    () => {
        form.reset();
        form.clearErrors();
        fileName.value = '';
    },
);

function choose(event) {
    if (form.processing) {
        return;
    }

    form.file = event.target.files?.[0] ?? null;
    fileName.value = form.file?.name ?? '';
}

function close() {
    if (!form.processing) {
        emit('close');
    }
}

function submit() {
    form.transform((data) => ({ ...data, ...props.params })).post(props.action, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Modal :show="show" :title="title" @close="close">
        <form class="space-y-4" @submit.prevent="submit">
            <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>

            <div v-if="columns.length" class="rounded-lg border border-border bg-muted/50 px-3 py-2.5 text-xs text-muted-foreground">
                <p class="font-medium text-foreground">Kolom yang dibaca</p>
                <p class="mt-1 flex flex-wrap gap-1.5">
                    <span v-for="column in columns" :key="column" class="rounded bg-card px-1.5 py-0.5 font-mono text-2xs text-muted-foreground ring-1 ring-border">
                        {{ column }}
                    </span>
                </p>
                <a
                    v-if="templateUrl"
                    :href="templateUrl"
                    class="mt-2 inline-flex items-center gap-1 font-medium text-primary hover:text-primary"
                >
                    <i class="fas fa-download h-3.5 w-3.5" ></i> Unduh template Excel
                </a>
            </div>

            <div>
                <label :class="labelClass" for="import-file">Berkas Excel atau CSV</label>
                <label
                    for="import-file"
                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-border bg-card px-4 py-4 transition hover:border-primary/20 hover:bg-primary/10"
                    :class="form.processing && 'pointer-events-none opacity-60'"
                >
                    <i class="fas fa-file-excel h-8 w-8 shrink-0 text-muted-foreground" ></i>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-foreground">{{ fileName || 'Pilih berkas' }}</span>
                        <span class="block text-xs text-muted-foreground">.xlsx, .xls, atau .csv, maksimal 5 MB</span>
                    </span>
                </label>
                <input
 id="import-file"
 type="file"
 accept=".xlsx,.xls,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
 class="sr-only"
 @change="choose"
 />
                <p v-if="form.errors.file" class="mt-1 text-xs text-destructive">{{ form.errors.file }}</p>
            </div>

            <p v-if="form.processing" role="status" class="flex items-center gap-2 text-xs font-medium text-primary">
                <i class="fas fa-spinner h-3.5 w-3.5 animate-spin" aria-hidden="true"></i>
                Berkas sedang diproses. Jangan tutup jendela ini hingga impor selesai.
            </p>

            <div class="flex justify-end gap-2">
                <button type="button" :class="btnSecondary" :disabled="form.processing" @click="close">Batal</button>
                <button type="submit" :class="btnPrimary" :disabled="form.processing || !form.file" :aria-busy="form.processing">
                    <i v-if="form.processing" class="fas fa-spinner h-4 w-4 animate-spin" aria-hidden="true"></i>
                    <i v-else class="fas fa-upload h-4 w-4" aria-hidden="true"></i>
                    {{ form.processing ? 'Mengimpor…' : 'Mulai Impor' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
