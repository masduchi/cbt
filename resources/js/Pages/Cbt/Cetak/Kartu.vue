<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    ujian: { type: Object, required: true },
    peserta: { type: Array, default: () => [] },
    kop: { type: Object, default: null },
    sekolah: { type: String, default: '' },
    alamat: { type: String, default: '' },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
    filters: { type: Object, default: () => ({ q: '', pilih: [] }) },
});

const kopLines = computed(() =>
    ['header_1', 'header_2', 'header_3', 'header_4'].map((key) => props.kop?.[key]).filter(Boolean),
);

const cari = ref(props.filters.q ?? '');
const dipilih = ref(props.peserta.map((row) => row.id_siswa));

watch(
    () => props.peserta,
    (rows) => {
        dipilih.value = rows.map((row) => row.id_siswa);
    },
);

const jumlahDipilih = computed(() => props.peserta.filter((row) => dipilih.value.includes(row.id_siswa)).length);
const semuaDipilih = computed(() => props.peserta.length > 0 && jumlahDipilih.value === props.peserta.length);

const pdfUrl = computed(() => {
    const params = new URLSearchParams();

    if (props.filters.q) {
        params.set('q', props.filters.q);
    }

    const terpilih = props.peserta.filter((row) => dipilih.value.includes(row.id_siswa));

    if (terpilih.length === 0) {
        return null;
    }

    if (terpilih.length !== props.peserta.length) {
        terpilih.forEach((row) => params.append('pilih[]', String(row.id_siswa)));
    }

    const query = params.toString();

    return `/cbt/jadwal/${props.ujian.id_jadwal}/kartu/pdf${query ? `?${query}` : ''}`;
});

function terpilih(id) {
    return dipilih.value.includes(id);
}

function toggle(id) {
    dipilih.value = terpilih(id)
        ? dipilih.value.filter((value) => value !== id)
        : [...dipilih.value, id];
}

function pilihSemua() {
    dipilih.value = props.peserta.map((row) => row.id_siswa);
}

function kosongkan() {
    dipilih.value = [];
}

function cariSekarang() {
    router.get(
        `/cbt/jadwal/${props.ujian.id_jadwal}/kartu`,
        { q: cari.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function resetCari() {
    cari.value = '';
    cariSekarang();
}

function printPage() {
    window.print();
}
</script>

<template>
    <Head :title="`Kartu Peserta ${ujian.mapel || ''}`" />
    <div class="min-h-screen bg-slate-100 text-black print:bg-white">
        <div class="sticky top-0 z-10 space-y-2 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur print:hidden">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    href="/cbt/jadwal"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    <i class="fas fa-arrow-left h-4 w-4"></i> Kembali
                </Link>

                <form class="flex flex-wrap items-center gap-2" @submit.prevent="cariSekarang">
                    <input
                        v-model="cari"
                        type="search"
                        class="h-9 w-56 rounded-lg border border-slate-300 bg-white px-2.5 text-sm"
                        placeholder="Cari nama / NIS / NISN"
                        aria-label="Cari peserta"
                    />
                    <button type="submit" class="inline-flex h-9 items-center rounded-lg bg-slate-800 px-3 text-sm font-medium text-white">
                        Cari
                    </button>
                    <button
                        v-if="filters.q"
                        type="button"
                        class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700"
                        @click="resetCari"
                    >
                        Reset
                    </button>
                </form>

                <div class="flex items-center gap-2">
                    <a
                        v-if="pdfUrl"
                        :href="pdfUrl"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        <i class="fas fa-file-pdf h-4 w-4"></i> Unduh PDF
                    </a>
                    <span
                        v-else
                        class="inline-flex cursor-not-allowed items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-400 opacity-60"
                    >
                        <i class="fas fa-file-pdf h-4 w-4"></i> Unduh PDF
                    </span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!jumlahDipilih"
                        @click="printPage()"
                    >
                        <i class="fas fa-print h-4 w-4"></i> Cetak / Simpan PDF
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-500">
                <span>
                    Kartu peserta <b class="text-slate-800">{{ ujian.mapel }}</b> ·
                    {{ filters.q ? `${peserta.length} hasil untuk "${filters.q}"` : `${peserta.length} siswa` }}
                </span>
                <span class="font-medium text-slate-700">{{ jumlahDipilih }} dari {{ peserta.length }} dipilih</span>
                <button
                    type="button"
                    class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 disabled:opacity-50"
                    :disabled="semuaDipilih || !peserta.length"
                    @click="pilihSemua"
                >
                    Pilih semua
                </button>
                <button
                    type="button"
                    class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 disabled:opacity-50"
                    :disabled="!jumlahDipilih"
                    @click="kosongkan"
                >
                    Kosongkan
                </button>
            </div>
        </div>

        <div class="mx-auto max-w-[210mm] p-4 print:p-0">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 print:grid-cols-2 print:gap-2">
                <article
                    v-for="row in peserta"
                    :key="row.id_siswa"
                    class="relative break-inside-avoid rounded-lg border-2 border-slate-800 bg-white p-3 text-[11px] transition print:rounded-none"
                    :class="terpilih(row.id_siswa) ? '' : 'opacity-40 print:hidden'"
                >
                    <label
                        class="absolute right-2 top-2 flex cursor-pointer items-center gap-1 rounded border border-slate-300 bg-white px-1.5 py-0.5 text-[9px] text-slate-600 print:hidden"
                    >
                        <input
                            type="checkbox"
                            :checked="terpilih(row.id_siswa)"
                            @change="toggle(row.id_siswa)"
                        />
                        Cetak
                    </label>

                    <header class="border-b border-slate-400 pb-1 pr-14 text-center">
                        <template v-if="kopLines.length">
                            <p
                                v-for="(line, index) in kopLines"
                                :key="index"
                                :class="index === 0 ? 'text-[11px] font-bold uppercase tracking-wide' : 'text-[9px] leading-snug'"
                            >
                                {{ line }}
                            </p>
                        </template>
                        <p v-else class="text-[11px] font-bold uppercase tracking-wide">{{ sekolah || 'Nama Sekolah' }}</p>
                        <p class="text-[9px] uppercase">Kartu Peserta Ujian</p>
                        <p class="text-[10px] font-semibold">{{ ujian.jenis || 'Ujian' }} · {{ tahun }} / {{ semester }}</p>
                    </header>

                    <dl class="mt-2 space-y-1">
                        <div class="flex gap-2">
                            <dt class="w-24 shrink-0 text-slate-600">No. Peserta</dt>
                            <dd class="font-mono text-[12px] font-bold">{{ row.nomor_peserta || '-' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-24 shrink-0 text-slate-600">Nama</dt>
                            <dd class="font-semibold">{{ row.nama }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-24 shrink-0 text-slate-600">NIS / NISN</dt>
                            <dd>{{ row.nis || '-' }} / {{ row.nisn || '-' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-24 shrink-0 text-slate-600">Kelas</dt>
                            <dd>{{ row.kelas }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-24 shrink-0 text-slate-600">Ruang / Sesi</dt>
                            <dd>{{ row.ruang || '-' }} / {{ row.sesi || '-' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-24 shrink-0 text-slate-600">Mata Pelajaran</dt>
                            <dd class="font-medium">{{ ujian.mapel || '-' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-24 shrink-0 text-slate-600">Tanggal</dt>
                            <dd>{{ ujian.tanggal }} · {{ ujian.mulai }} s.d. {{ ujian.selesai }}</dd>
                        </div>
                    </dl>

                    <div class="mt-2 flex items-end justify-between gap-2 border-t border-dashed border-slate-300 pt-2">
                        <div v-if="row.verifikasi" class="flex items-center gap-2">
                            <div v-if="row.verifikasi.svg" class="h-14 w-14 shrink-0" v-html="row.verifikasi.svg"></div>
                            <div class="text-[7px] leading-tight text-slate-500">
                                <p class="font-semibold text-slate-700">Verifikasi kartu</p>
                                <p class="font-mono">{{ row.verifikasi.kode }}</p>
                                <p class="break-all">{{ row.verifikasi.url }}</p>
                            </div>
                        </div>
                        <p v-else class="text-[8px] text-slate-500">Simpan kartu ini selama ujian berlangsung.</p>
                        <div class="text-center text-[9px]">
                            <div class="h-8 w-20 border-b border-slate-400" />
                            <p class="mt-0.5">Tanda tangan</p>
                        </div>
                    </div>
                </article>
            </div>

            <p v-if="!peserta.length" class="rounded-lg border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-500">
                {{ filters.q ? `Tidak ada peserta yang cocok dengan pencarian "${filters.q}".` : 'Belum ada peserta pada jadwal ini.' }}
            </p>
        </div>
    </div>
</template>
