<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, btnSecondary, cardClass } from '@/ui';

const props = defineProps({
    exams: { type: Array, default: () => [] },
    siswa: { type: Object, required: true },
    periode: { type: String, default: '' },
});

function statusLabel(status) {
    if (status === 1) return 'Sedang dikerjakan';
    if (status === 2) return 'Selesai';
    return 'Belum dimulai';
}

function statusClass(status) {
    if (status === 1) return 'bg-warning/10 text-warning border-warning/30';
    if (status === 2) return 'bg-success/10 text-success border-success/30';
    return 'bg-muted text-muted-foreground border-border';
}

function remainingLabel(seconds) {
    if (seconds === null || seconds === undefined) return null;
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}m ${String(s).padStart(2, '0')}s`;
}
</script>

<template>
    <Head title="Ujian Saya" />
    <AdminLayout title="Ujian Saya">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Ujian Saya">
                    <template #description>Daftar ujian yang tersedia untuk kelas Anda pada periode {{ props.periode }}.</template>
                
                </PageHeader>
                <div class="rounded-xl border border-border bg-card px-4 py-3 text-sm shadow-card">
                    <p class="font-semibold text-foreground">{{ props.siswa.nama }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ props.siswa.kelas || 'Belum ada kelas' }}
                        <span v-if="props.siswa.nomor_peserta"> · No. {{ props.siswa.nomor_peserta }}</span>
                    </p>
                </div>
            </div>

            <div v-if="!props.exams.length" :class="cardClass" class="p-10 text-center">
                <i class="fas fa-tv mx-auto h-10 w-10 text-muted-foreground/50" ></i>
                <p class="mt-3 text-sm font-medium text-foreground">Belum ada ujian untuk Anda saat ini.</p>
                <p class="mt-1 text-xs text-muted-foreground">Ujian akan muncul di sini ketika jadwal aktif dan kelas Anda terdaftar pada bank soal.</p>
            </div>

            <div v-else class="grid gap-4">
                <div v-for="exam in props.exams" :key="exam.id_jadwal" :class="cardClass" class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-medium" :class="statusClass(exam.status)">
                                    {{ statusLabel(exam.status) }}
                                </span>
                                <span v-if="exam.token" class="inline-flex items-center gap-1 rounded-full border border-primary/30 bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                                    <i class="fas fa-key h-3.5 w-3.5" ></i> Token
                                </span>
                                <span v-if="exam.jenis" class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                                    {{ exam.jenis }}
                                </span>
                                <span v-if="exam.jam_ke" class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                                    Jam ke-{{ exam.jam_ke }}
                                </span>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-semibold text-foreground">{{ exam.bank_nama }}</h2>
                                <span v-if="exam.remedial" class="rounded-full border border-primary/30 bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                                    Ujian remedial
                                </span>
                            </div>
                            <p class="text-sm text-muted-foreground">{{ exam.mapel || 'Mata pelajaran' }}</p>
                            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
                                <span class="inline-flex items-center gap-1"><i class="fas fa-clock h-3.5 w-3.5" ></i> {{ exam.durasi_ujian }} menit</span>
                                <span class="inline-flex items-center gap-1"><i class="fas fa-clipboard-check h-3.5 w-3.5" ></i> {{ exam.jumlah_soal }} soal</span>
                                <span>{{ exam.tgl_mulai }} – {{ exam.tgl_selesai }}</span>
                            </div>
                            <p v-if="exam.status === 1" class="mt-2 text-xs font-medium text-warning">
                                Sisa waktu: {{ remainingLabel(exam.remaining) }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-col items-stretch gap-2">
                            <template v-if="exam.status === 0 && exam.locked">
                                <span class="inline-flex cursor-not-allowed items-center justify-center gap-1.5 rounded-lg border border-border bg-muted px-3 py-2 text-sm font-medium text-muted-foreground">
                                    <i class="fas fa-lock h-4 w-4" ></i> Terkunci
                                </span>
                                <p class="max-w-[16rem] text-xs text-destructive">{{ exam.lock_reason }}</p>
                            </template>
                            <Link
                                v-else-if="exam.status === 0"
                                :href="`/ujian/${exam.id_jadwal}`"
                                :class="btnPrimary"
                            >
                                <i class="fas fa-play-circle h-4 w-4" ></i> Mulai Ujian
                            </Link>
                            <Link
                                v-else-if="exam.status === 1"
                                :href="`/ujian/${exam.id_jadwal}/kerjakan`"
                                :class="btnPrimary"
                            >
                                <i class="fas fa-play-circle h-4 w-4" ></i> Lanjutkan
                            </Link>
                            <template v-else>
                                <span class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-success/10 px-3 py-2 text-sm font-medium text-success">
                                    <i class="fas fa-check-circle h-4 w-4" ></i> Sudah selesai
                                </span>
                                <Link :href="`/ujian/hasil`" :class="btnSecondary">Lihat Hasil</Link>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
