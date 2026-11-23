<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { btnPrimary, cardClass, inputClass, labelClass } from '@/ui';

const props = defineProps({
    jadwal: { type: Object, required: true },
    durasi: { type: Object, default: null },
    tokenReady: { type: Boolean, default: false },
    siswa: { type: Object, required: true },
});

const form = useForm({ token: '' });

const status = props.durasi?.status ?? 0;

function submit() {
    form.post(`/ujian/${props.jadwal.id_jadwal}/mulai`);
}

function remainingLabel(seconds) {
    if (seconds === null || seconds === undefined) return '';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}m ${String(s).padStart(2, '0')}s`;
}
</script>

<template>
    <Head :title="`Konfirmasi ${props.jadwal.bank_nama}`" />
    <AdminLayout :title="props.jadwal.bank_nama || 'Konfirmasi Ujian'">
        <div class="page-stack space-y-5">
            <Link href="/ujian" class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                <i class="fas fa-arrow-left h-4 w-4" ></i> Kembali ke daftar ujian
            </Link>

            <div :class="cardClass" class="overflow-hidden">
                <div class="border-b border-border px-6 py-5">
                    <p class="text-xs font-medium text-muted-foreground">Konfirmasi ujian</p>
                    <h1 class="mt-1 text-lg font-semibold text-foreground">{{ props.jadwal.bank_nama }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ props.jadwal.mapel }} · {{ props.jadwal.jenis }}</p>
                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-muted-foreground">
                        <span class="inline-flex items-center gap-1.5"><i class="fas fa-clock h-3.5 w-3.5 text-muted-foreground" aria-hidden="true" ></i> {{ props.jadwal.durasi_ujian }} menit</span>
                        <span class="inline-flex items-center gap-1.5"><i class="fas fa-clipboard-check h-3.5 w-3.5 text-muted-foreground" aria-hidden="true" ></i> {{ props.jadwal.jumlah_soal }} soal</span>
                        <span>{{ props.jadwal.tgl_mulai }} – {{ props.jadwal.tgl_selesai }}</span>
                    </div>
                    <p v-if="props.remedial" class="mt-3 rounded-lg border border-primary/30 bg-primary/10 px-3 py-2 text-xs font-medium text-primary">
                        Ujian remedial · percobaan {{ props.remedial.percobaan }} dari {{ props.remedial.max_percobaan }}.
                        Nilai terbaik yang dipakai.
                    </p>
                </div>

                <div class="space-y-5 p-6">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-border bg-muted/60 p-3">
                            <p class="text-xs text-muted-foreground">Nama</p>
                            <p class="text-sm font-semibold text-foreground">{{ props.siswa.nama }}</p>
                        </div>
                        <div class="rounded-lg border border-border bg-muted/60 p-3">
                            <p class="text-xs text-muted-foreground">Kelas / No. Peserta</p>
                            <p class="text-sm font-semibold text-foreground">
                                {{ props.siswa.kelas || '-' }}<span v-if="props.siswa.nomor_peserta"> · {{ props.siswa.nomor_peserta }}</span>
                            </p>
                        </div>
                    </div>

                    <div v-if="status === 2" class="rounded-lg border border-success/30 bg-success/10 p-4 text-sm text-success">
                        <p class="inline-flex items-center gap-2 font-semibold"><i class="fas fa-check-circle h-4 w-4" ></i> Ujian sudah Anda selesaikan.</p>
                        <div class="mt-3">
                            <Link href="/ujian/hasil" :class="btnPrimary">Lihat Hasil Ujian</Link>
                        </div>
                    </div>

                    <div v-else-if="status === 1" class="space-y-4">
                        <div class="rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm text-warning">
                            <p class="font-semibold">Ujian sedang berlangsung.</p>
                            <p class="mt-1">Sisa waktu: {{ remainingLabel(props.durasi?.remaining) }}. Waktu tetap berjalan meskipun halaman ditutup.</p>
                        </div>
                        <Link :href="`/ujian/${props.jadwal.id_jadwal}/kerjakan`" :class="btnPrimary">
                            <i class="fas fa-play-circle h-4 w-4" ></i> Lanjutkan Ujian
                        </Link>
                    </div>

                    <form v-else class="space-y-4" @submit.prevent="submit">
                        <div v-if="props.jadwal.token" class="rounded-lg border border-primary/30 bg-primary/10 p-4">
                            <label :class="labelClass" class="inline-flex items-center gap-1.5 text-primary-900">
                                <i class="fas fa-key h-3.5 w-3.5" ></i> Masukkan token ujian
                            </label>
                            <input v-model="form.token" type="text" :class="inputClass" maxlength="10" autocomplete="off" placeholder="Token dari pengawas" aria-label="Token ujian" />
                            <p v-if="form.errors.token" class="mt-1 text-xs text-destructive">{{ form.errors.token }}</p>
                            <p v-if="!props.tokenReady" class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-destructive">
                                <i class="fas fa-exclamation-triangle h-3.5 w-3.5" ></i> Token belum digenerate pengawas.
                            </p>
                        </div>

                        <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                            <li>Kerjakan soal dengan jujur dan mandiri.</li>
                            <li>Jawaban tersimpan otomatis setiap kali Anda menjawab.</li>
                            <li>Waktu tetap berjalan meskipun halaman ditutup atau di-refresh.</li>
                        </ul>

                        <div class="flex justify-end">
                            <button type="submit" :class="btnPrimary" :disabled="form.processing || (props.jadwal.token && !props.tokenReady)">
                                <i class="fas fa-play-circle h-4 w-4" ></i> Mulai Sekarang
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
