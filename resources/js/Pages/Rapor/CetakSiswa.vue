<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnSecondary, cardClass } from '@/ui';

defineProps({
    kelas: { type: Object, required: true },
    siswas: { type: Array, default: () => [] },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
    settingAda: { type: Boolean, default: false },
});
</script>

<template>
    <Head :title="`Cetak Rapor ${kelas.nama_kelas}`" />
    <AdminLayout :title="`Cetak Rapor ${kelas.nama_kelas}`">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader :title="`Cetak Rapor ${kelas.nama_kelas}`" :back="{ href: '/rapor/cetak', label: 'Pilih kelas lain' }">
                    <template #description>
                        Tahun pelajaran {{ tahun }} · Semester {{ semester }} ·
                        Wali: {{ kelas.wali || 'belum ditetapkan' }}
                    </template>
                </PageHeader>
                <a :href="`/rapor/leger/${kelas.id_kelas}`" :class="btnSecondary">
                    <i class="fas fa-file-excel h-4 w-4" ></i> Cetak Leger Kelas
                </a>
            </div>

            <div v-if="!settingAda" class="flex items-start gap-3 rounded-xl border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-warning">
                <i class="fas fa-exclamation-triangle mt-0.5 h-4 w-4 shrink-0" ></i>
                <p>Pengaturan rapor periode ini belum ada. Rapor tetap bisa dicetak, tetapi KKM dan tanggal pengesahan memakai nilai bawaan.</p>
            </div>

            <div :class="cardClass" class="overflow-x-auto">
                <table class="data-table min-w-full divide-y divide-border text-sm">
                    <thead>
                        <tr>
                            <th class="w-12 px-4 py-3">#</th>
                            <th class="px-4 py-3">Nama Siswa</th>
                            <th class="px-4 py-3">NIS / NISN</th>
                            <th class="px-4 py-3 text-center">Nilai Terisi</th>
                            <th class="px-4 py-3 text-center">Peringkat</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="(row, index) in siswas" :key="row.id_siswa" class="hover:bg-muted/40">
                            <td class="px-4 py-3 text-muted-foreground">{{ index + 1 }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-foreground">{{ row.nama }}</p>
                                <p class="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                    <i class="fas fa-user-check h-3 w-3" ></i> {{ row.ranking ? `Peringkat ${row.ranking}` : 'Belum ada peringkat' }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ row.nis || '-' }} / {{ row.nisn || '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    class="inline-flex min-w-[2.5rem] items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="row.jumlah_nilai > 0 ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground'"
                                >
                                    {{ row.jumlah_nilai }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-muted-foreground">{{ row.ranking || '-' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a
                                        :href="`/rapor/cetak/${kelas.id_kelas}/${row.id_siswa}?jenis=pts`"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-muted/40"
                                    >
                                        <i class="fas fa-print h-3.5 w-3.5" ></i> Nilai PTS
                                    </a>
                                    <a
                                        :href="`/rapor/cetak/${kelas.id_kelas}/${row.id_siswa}`"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white transition hover:bg-primary-600"
                                    >
                                        <i class="fas fa-print h-3.5 w-3.5" ></i> Cetak Rapor
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!siswas.length">
                            <td colspan="6" class="px-4 py-12 text-center">
                                <i class="fas fa-users mx-auto h-9 w-9 text-muted-foreground/50" ></i>
                                <p class="mt-2 text-sm font-medium text-foreground">Belum ada siswa di kelas ini</p>
                                <p class="mt-1 text-xs text-muted-foreground">Tambahkan anggota kelas pada menu Data Master → Kelas / Rombel.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
