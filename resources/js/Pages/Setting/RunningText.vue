<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, btnSecondary, inputClass, labelClass } from '@/ui';
import { ref } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    texts: { type: Array, default: () => [] },
});

const form = useForm({ text: '' });
const editing = ref(null);
const draft = ref('');

function submit() {
    form.post('/setting/running-text', {
        preserveScroll: true,
        onSuccess: () => form.reset('text'),
    });
}

function startEdit(item) {
    editing.value = item.id_text;
    draft.value = item.text;
}

function cancelEdit() {
    editing.value = null;
    draft.value = '';
}

function saveEdit(item) {
    const text = draft.value.trim();

    if (!text) {
        return;
    }

    router.put(`/setting/running-text/${item.id_text}`, { text }, {
        preserveScroll: true,
        onSuccess: cancelEdit,
    });
}

async function destroy(item) {
    const teks = item.text.length > 60 ? `${item.text.slice(0, 60)}…` : item.text;

    if (await askConfirm(`Hapus teks berjalan "${teks}"?`)) {
        router.delete(`/setting/running-text/${item.id_text}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Running Text" />
    <AdminLayout title="Running Text">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Running Text" description="Teks berjalan tampil di bagian atas semua halaman aplikasi. Maksimal 255 karakter per baris." /></div>

            <form class="space-y-3 rounded-xl border border-border bg-card p-5 shadow-card" @submit.prevent="submit">
                <div>
                    <label :class="labelClass" for="teks-baru">Teks baru</label>
                    <input
 id="teks-baru"
 v-model="form.text"
 :class="inputClass"
 type="text"
 maxlength="255"
 placeholder="Contoh: Ujian tengah semester dimulai 18 November 2026"
 required
 />
                    <p v-if="form.errors.text" role="alert" class="mt-1 text-xs font-medium text-destructive">{{ form.errors.text }}</p>
                </div>
                <div class="flex justify-end">
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">
                        <i class="fas fa-plus h-4 w-4" aria-hidden="true" ></i> Tambah
                    </button>
                </div>
            </form>

            <div v-if="!props.texts.length" class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                <i class="fas fa-bullhorn mx-auto mb-3 h-9 w-9 text-muted-foreground/50" aria-hidden="true" ></i>
                <p class="text-sm font-medium text-foreground">Belum ada teks berjalan.</p>
            </div>

            <ul v-else class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card shadow-card">
                <li v-for="item in props.texts" :key="item.id_text" class="flex flex-wrap items-center gap-3 px-5 py-3">
                    <template v-if="editing === item.id_text">
                        <label class="sr-only" :for="`teks-${item.id_text}`">Ubah teks berjalan</label>
                        <input
 :id="`teks-${item.id_text}`"
 v-model="draft"
 :class="inputClass"
 class="min-w-0 flex-1"
 type="text"
 maxlength="255"
 @keydown.enter.prevent="saveEdit(item)"
 @keydown.esc.prevent="cancelEdit"
 />
                        <div class="flex gap-2">
                            <button type="button" :class="btnPrimary" @click="saveEdit(item)">Simpan</button>
                            <button type="button" :class="btnSecondary" @click="cancelEdit">
                                <i class="fas fa-times h-4 w-4" aria-hidden="true" ></i> Batal
                            </button>
                        </div>
                    </template>
                    <template v-else>
                        <p class="min-w-0 flex-1 text-sm text-foreground">{{ item.text }}</p>
                        <div class="flex gap-1">
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                :aria-label="`Ubah teks: ${item.text}`"
                                @click="startEdit(item)"
                            >
                                <i class="fas fa-pencil-alt h-4 w-4" aria-hidden="true" ></i>
                            </button>
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-destructive/30"
                                :aria-label="`Hapus teks: ${item.text}`"
                                @click="destroy(item)"
                            >
                                <i class="fas fa-trash-alt h-4 w-4" aria-hidden="true" ></i>
                            </button>
                        </div>
                    </template>
                </li>
            </ul>
        </div>
    </AdminLayout>
</template>
