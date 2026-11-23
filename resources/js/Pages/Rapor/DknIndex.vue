<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { cardClass } from '@/ui';

defineProps({
    classes: { type: Array, default: () => [] },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
    settingAda: { type: Boolean, default: false },
});
</script>

<template>
    <Head title="DKN Rapor" />
    <AdminLayout title="DKN Rapor">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Daftar Kumpulan Nilai">
                    <template #description>Tahun pelajaran {{ tahun }} · Semester {{ semester }}. Ringkasan nilai semua mata pelajaran, muatan lokal, ekstrakurikuler, sikap, dan absensi per kelas.</template>
                
                </PageHeader></div>

            <div v-if="!settingAda" class="flex items-start gap-3 rounded-xl border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-warning">
                <i class="fas fa-exclamation-triangle mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" ></i>
                <p>Pengaturan rapor periode aktif belum dibuat. Lengkapi KKM dan tanggal rapor agar predikat pada DKN dihitung sesuai.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="item in classes" :key="item.id_kelas" :class="cardClass" class="flex flex-col gap-3 p-5">
                    <div class="flex items-start justify-between">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <i class="fas fa-chart-bar h-5 w-5" aria-hidden="true" ></i>
                        </div>
                        <span class="text-xs font-medium text-muted-foreground">{{ item.siswa_count }} siswa</span>
                    </div>
                    <div>
                        <p class="text-base font-semibold text-foreground">{{ item.nama_kelas }}</p>
                        <p class="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground">
                            <i class="fas fa-user-check h-3.5 w-3.5" aria-hidden="true" ></i> {{ item.wali || 'Belum ada wali kelas' }}
                        </p>
                    </div>
                    <div class="mt-auto flex items-center gap-2 pt-2">
                        <Link
                            :href="`/rapor/dkn/${item.id_kelas}`"
                            class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-xs font-medium text-white transition hover:bg-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <i class="fas fa-chart-bar h-3.5 w-3.5" aria-hidden="true" ></i> Buka DKN
                        </Link>
                        <a
                            :href="`/rapor/dkn/${item.id_kelas}/pdf`"
                            class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-border bg-card px-3 py-2 text-xs font-medium text-foreground transition hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <i class="fas fa-download h-3.5 w-3.5" aria-hidden="true" ></i> PDF
                        </a>
                    </div>
                </div>
            </div>

            <div v-if="!classes.length" :class="cardClass" class="px-6 py-12 text-center">
                <i class="fas fa-school mx-auto h-10 w-10 text-muted-foreground/50" aria-hidden="true" ></i>
                <p class="mt-3 text-sm font-medium text-foreground">Belum ada kelas yang dapat dibuka</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Anda belum ditetapkan sebagai wali kelas atau pengampu pada periode aktif.
                </p>
            </div>
        </div>
    </AdminLayout>
</template>
