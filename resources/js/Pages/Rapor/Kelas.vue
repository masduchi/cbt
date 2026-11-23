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
});
</script>

<template>
    <Head title="Data Rapor Kelas" />
    <AdminLayout title="Data Rapor Kelas">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Data Rapor Kelas">
                    <template #description>Tahun pelajaran {{ tahun }} · Semester {{ semester }}. Pilih kelas untuk mengisi sikap, absensi, prestasi, fisik, ekstrakurikuler, dan kenaikan.</template>
                
                </PageHeader></div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="item in kelas"
                    :key="item.id_kelas"
                    :href="`/rapor/kelas/${item.id_kelas}`"
                    :class="cardClass"
                    class="group flex flex-col gap-3 p-5 transition hover:border-primary/40 hover:shadow-md"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <i class="fas fa-school h-5 w-5" ></i>
                        </div>
                        <i class="fas fa-arrow-right h-4 w-4 text-muted-foreground/50 transition group-hover:text-primary" ></i>
                    </div>
                    <div>
                        <p class="text-base font-semibold text-foreground">{{ item.nama_kelas }}</p>
                        <p class="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground">
                            <i class="fas fa-user-check h-3.5 w-3.5" ></i> {{ item.wali || 'Belum ada wali kelas' }}
                        </p>
                    </div>
                    <p class="inline-flex items-center gap-1 text-xs text-muted-foreground">
                        <i class="fas fa-users h-3.5 w-3.5" ></i> {{ item.siswa_count }} siswa
                    </p>
                </Link>
            </div>

            <div v-if="!kelas.length" :class="cardClass" class="px-6 py-12 text-center">
                <i class="fas fa-school mx-auto h-10 w-10 text-muted-foreground/50" ></i>
                <p class="mt-3 text-sm font-medium text-foreground">Belum ada kelas yang dapat dikelola</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ isAdmin ? 'Tambahkan kelas pada menu Data Master → Kelas / Rombel.' : 'Anda belum ditetapkan sebagai wali kelas pada periode aktif.' }}
                </p>
            </div>
        </div>
    </AdminLayout>
</template>
