<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnSecondary, cardClass } from '@/ui';

const props = defineProps({
    student: { type: Object, required: true },
    notifikasi: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({ total: 0, belum: 0 }) },
});

function baca(item) {
    router.post(`/siswa/notifikasi/${item.id}/baca`, {}, { preserveScroll: true });
}

function bacaSemua() {
    router.post('/siswa/notifikasi/baca-semua', {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Notifikasi" />
    <AdminLayout title="Notifikasi">
        <div class="page-stack space-y-5">
            <div class="flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:flex-row lg:items-end lg:justify-between">
                <PageHeader title="Notifikasi">
                    <template #description>
                        Pusat notifikasi untuk {{ props.student.nama }}. Hasil ujian yang sudah dikoreksi muncul di sini.
                    </template>
                </PageHeader>
                <div class="flex items-center gap-3">
                    <p class="text-sm text-muted-foreground">
                        {{ props.ringkasan.belum }} belum dibaca dari {{ props.ringkasan.total }} notifikasi
                    </p>
                    <button
                        type="button"
                        :class="btnSecondary"
                        :disabled="props.ringkasan.belum === 0"
                        @click="bacaSemua"
                    >
                        Tandai semua dibaca
                    </button>
                </div>
            </div>

            <div v-if="!props.notifikasi.length" :class="cardClass" class="px-6 py-12 text-center">
                <p class="text-sm text-muted-foreground">Belum ada notifikasi. Hasil ujian akan muncul di sini setelah dikoreksi.</p>
            </div>

            <div v-else class="space-y-3">
                <article
                    v-for="item in props.notifikasi"
                    :key="item.id"
                    :class="[cardClass, 'flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between', !item.dibaca && 'border-l-4 border-primary']"
                >
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-foreground">
                            Nilai ujian tersedia: {{ item.ujian }}
                            <span
                                v-if="!item.dibaca"
                                class="rounded-full bg-primary/10 px-2 py-0.5 text-2xs font-semibold uppercase tracking-wide text-primary"
                            >Baru</span>
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ item.jenis_ujian || 'Ujian' }} · Nilai akhir {{ item.nilai_akhir }} · {{ item.waktu }}
                        </p>
                    </div>
                    <button
                        v-if="!item.dibaca"
                        type="button"
                        :class="btnSecondary"
                        @click="baca(item)"
                    >
                        Tandai dibaca
                    </button>
                </article>
            </div>
        </div>
    </AdminLayout>
</template>
