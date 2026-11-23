<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnSecondary, cardClass, formatTanggalWaktu } from '@/ui';

const props = defineProps({
    riwayat: { type: Array, default: () => [] },
    siswa: { type: Object, required: true },
    periode: { type: String, default: '' },
});

const komponenLabel = {
    pg: 'Pilihan Ganda',
    kompleks: 'PG Kompleks',
    jodohkan: 'Menjodohkan',
    isian: 'Isian Singkat',
    esai: 'Uraian',
};

function formatNilai(value) {
    if (value === null || value === undefined) return '-';
    return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

function badgeClass(item) {
    if (!item.hasil_tampil) return 'bg-muted text-muted-foreground border-border';
    if (item.kkm && item.total < item.kkm) return 'bg-destructive/10 text-destructive border-destructive/30';
    return 'bg-success/10 text-success border-success/30';
}
</script>

<template>
    <Head title="Hasil Ujian" />
    <AdminLayout title="Hasil Ujian">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Hasil Ujian">
                    <template #description>Riwayat ujian yang telah Anda selesaikan pada periode {{ props.periode }}.</template>
                
                </PageHeader>
                <div class="rounded-xl border border-border bg-card px-4 py-3 text-sm shadow-card">
                    <p class="font-semibold text-foreground">{{ props.siswa.nama }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ props.siswa.kelas || 'Belum ada kelas' }}
                        <span v-if="props.siswa.nomor_peserta"> · No. {{ props.siswa.nomor_peserta }}</span>
                    </p>
                </div>
            </div>

            <div v-if="!props.riwayat.length" :class="cardClass" class="p-10 text-center">
                <i class="fas fa-chart-bar mx-auto h-10 w-10 text-muted-foreground/50" ></i>
                <p class="mt-3 text-sm font-medium text-foreground">Belum ada ujian yang selesai.</p>
                <p class="mt-1 text-xs text-muted-foreground">Hasil akan muncul setelah Anda menyelesaikan ujian.</p>
                <Link href="/ujian" :class="btnSecondary" class="mt-4">Kembali ke Ujian Saya</Link>
            </div>

            <div v-else class="grid gap-4">
                <div v-for="item in props.riwayat" :key="item.id_jadwal" :class="cardClass" class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-medium" :class="badgeClass(item)">
                                    <template v-if="!item.hasil_tampil">Nilai belum diumumkan</template>
                                    <template v-else-if="item.kkm && item.total < item.kkm">Belum tuntas</template>
                                    <template v-else>Tuntas</template>
                                </span>
                                <span v-if="item.jenis" class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                                    {{ item.jenis }}
                                </span>
                                <span v-if="item.hasil_tampil && !item.dikoreksi" class="inline-flex items-center gap-1 rounded-full border border-warning/30 bg-warning/10 px-2.5 py-1 text-xs font-medium text-warning">
                                    <i class="fas fa-hourglass-half h-3.5 w-3.5" ></i> Menunggu koreksi esai
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-semibold text-foreground">{{ item.bank_nama }}</h2>
                                <span v-if="item.remedial" class="rounded-full border border-primary/30 bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                                    Remedial
                                </span>
                            </div>
                            <p class="text-sm text-muted-foreground">{{ item.mapel || 'Mata pelajaran' }}</p>
                            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
                                <span class="inline-flex items-center gap-1"><i class="fas fa-check-circle h-3.5 w-3.5" ></i> Selesai {{ formatTanggalWaktu(item.selesai) }}</span>
                                <span class="inline-flex items-center gap-1"><i class="fas fa-clock h-3.5 w-3.5" ></i> Durasi {{ item.lama_ujian || '00:00:00' }}</span>
                                <span v-if="item.kkm">KKM {{ item.kkm }}</span>
                            </div>
                        </div>

                        <div class="shrink-0 rounded-xl border border-border bg-muted/60 px-5 py-4 text-center">
                            <template v-if="item.hasil_tampil && item.total !== null">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Nilai Akhir</p>
                                <p class="mt-1 text-3xl font-semibold text-foreground">{{ formatNilai(item.total) }}</p>
                                <p v-if="item.nilai_akhir !== null && item.nilai_akhir !== undefined" class="mt-1 text-2xs text-muted-foreground">
                                    Nilai akhir (terbaik): <b class="text-foreground">{{ formatNilai(item.nilai_akhir) }}</b>
                                </p>
                            </template>
                            <template v-else>
                                <i class="fas fa-eye-slash mx-auto h-6 w-6 text-muted-foreground" ></i>
                                <p class="mt-1 text-xs text-muted-foreground">Belum diumumkan</p>
                            </template>
                        </div>
                    </div>

                    <div v-if="item.hasil_tampil && item.komponen" class="mt-4 grid grid-cols-2 gap-3 border-t border-border pt-4 sm:grid-cols-5">
                        <div v-for="(label, key) in komponenLabel" :key="key" class="rounded-lg bg-muted/60 px-3 py-2">
                            <p class="text-2xs font-medium uppercase tracking-wide text-muted-foreground">{{ label }}</p>
                            <p class="mt-0.5 text-sm font-semibold text-foreground">{{ formatNilai(item.komponen[key]) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
