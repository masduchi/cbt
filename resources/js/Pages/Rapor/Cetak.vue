<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { cardClass } from '@/ui';

defineProps({
    kelas: { type: Array, default: () => [] },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
    isAdmin: { type: Boolean, default: false },
    settingAda: { type: Boolean, default: false },
});
</script>

<template>
    <Head title="Cetak Rapor" />
    <AdminLayout title="Cetak Rapor">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Cetak Rapor &amp; Leger">
                    <template #description>Tahun pelajaran {{ tahun }} · Semester {{ semester }}. Pilih kelas untuk mencetak rapor per siswa atau leger nilai kelas.</template>
                
                </PageHeader></div>

            <div v-if="!settingAda" class="flex items-start gap-3 rounded-xl border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-warning">
                <i class="fas fa-exclamation-triangle mt-0.5 h-4 w-4 shrink-0" ></i>
                <p>Pengaturan rapor untuk periode aktif belum dibuat. Lengkapi KKM, bobot, dan tanggal rapor di menu <b>Pengaturan Rapor</b> agar format cetak sesuai.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="item in kelas" :key="item.id_kelas" :class="cardClass" class="flex flex-col gap-3 p-5">
                    <div class="flex items-start justify-between">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <i class="fas fa-school h-5 w-5" ></i>
                        </div>
                        <span class="text-xs font-medium text-muted-foreground">{{ item.siswa_count }} siswa</span>
                    </div>
                    <div>
                        <p class="text-base font-semibold text-foreground">{{ item.nama_kelas }}</p>
                        <p class="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground">
                            <i class="fas fa-user-check h-3.5 w-3.5" ></i> {{ item.wali || 'Belum ada wali kelas' }}
                        </p>
                    </div>
                    <div class="mt-auto flex items-center gap-2 pt-2">
                        <Link
                            :href="`/rapor/cetak/${item.id_kelas}`"
                            class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-xs font-medium text-white transition hover:bg-primary-600"
                        >
                            <i class="fas fa-print h-3.5 w-3.5" ></i> Rapor
                        </Link>
                        <a
                            :href="`/rapor/leger/${item.id_kelas}`"
                            class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-border bg-card px-3 py-2 text-xs font-medium text-foreground transition hover:bg-muted/40"
                        >
                            <i class="fas fa-file-excel h-3.5 w-3.5" ></i> Leger
                        </a>
                    </div>
                </div>
            </div>

            <div v-if="!kelas.length" :class="cardClass" class="px-6 py-12 text-center">
                <i class="fas fa-school mx-auto h-10 w-10 text-muted-foreground/50" ></i>
                <p class="mt-3 text-sm font-medium text-foreground">Belum ada kelas yang dapat dicetak</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ isAdmin ? 'Tambahkan kelas pada menu Data Master → Kelas / Rombel.' : 'Anda belum ditetapkan sebagai wali kelas pada periode aktif.' }}
                </p>
            </div>
        </div>
    </AdminLayout>
</template>
