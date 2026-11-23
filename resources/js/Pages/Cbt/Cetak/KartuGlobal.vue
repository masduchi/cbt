<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    kartu: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ kelas: null, jenis: null }) },
    kelasOptions: { type: Array, default: () => [] },
    jenisOptions: { type: Array, default: () => [] },
    kop: { type: Object, default: null },
    logo: { type: String, default: null },
    sekolah: { type: String, default: '' },
    alamat: { type: String, default: '' },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
    ringkasan: { type: Object, default: () => ({ siswa: 0, mapel: 0, tanpa_nomor: 0, tanpa_ruang: 0 }) },
});

const kopLines = computed(() =>
    ['header_1', 'header_2', 'header_3', 'header_4'].map((key) => props.kop?.[key]).filter(Boolean),
);

const pdfUrl = computed(() => {
    const params = new URLSearchParams();
    if (props.filters.kelas) params.set('kelas', props.filters.kelas);
    if (props.filters.jenis) params.set('jenis', props.filters.jenis);
    const query = params.toString();

    return `/cbt/kartu-global/pdf${query ? `?${query}` : ''}`;
});

const peringatan = computed(() => {
    const pesan = [];
    if (props.ringkasan.tanpa_nomor) pesan.push(`${props.ringkasan.tanpa_nomor} siswa belum punya nomor peserta`);
    if (props.ringkasan.tanpa_ruang) pesan.push(`${props.ringkasan.tanpa_ruang} siswa belum punya ruang/sesi`);
    return pesan;
});

function terapkan(field, value) {
    router.get(
        '/cbt/kartu-global',
        {
            kelas: field === 'kelas' ? value || undefined : props.filters.kelas || undefined,
            jenis: field === 'jenis' ? value || undefined : props.filters.jenis || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function printPage() {
    window.print();
}
</script>

<template>
    <Head title="Kartu Ujian Global" />
    <div class="min-h-screen bg-slate-100 text-black print:bg-white">
        <div class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur print:hidden">
            <Link
                href="/cbt/jadwal"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            >
                <i class="fas fa-arrow-left h-4 w-4"></i> Kembali
            </Link>

            <div class="flex flex-wrap items-center gap-2">
                <label class="text-xs font-medium text-slate-500" for="global-kelas">Kelas</label>
                <select
                    id="global-kelas"
                    :value="filters.kelas || ''"
                    class="h-9 rounded-lg border border-slate-300 bg-white px-2 text-sm"
                    @change="terapkan('kelas', $event.target.value)"
                >
                    <option value="">Semua kelas</option>
                    <option v-for="item in kelasOptions" :key="item.id_kelas" :value="item.id_kelas">{{ item.nama_kelas }}</option>
                </select>

                <label class="ml-2 text-xs font-medium text-slate-500" for="global-jenis">Jenis ujian</label>
                <select
                    id="global-jenis"
                    :value="filters.jenis || ''"
                    class="h-9 rounded-lg border border-slate-300 bg-white px-2 text-sm"
                    @change="terapkan('jenis', $event.target.value)"
                >
                    <option value="">Semua jenis</option>
                    <option v-for="item in jenisOptions" :key="item.id_jenis" :value="item.id_jenis">{{ item.nama_jenis }}</option>
                </select>
            </div>

            <div class="flex items-center gap-3">
                <p class="text-sm text-slate-500">
                    <b class="text-slate-800">{{ ringkasan.siswa }}</b> siswa
                </p>
                <a
                    :href="pdfUrl"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    <i class="fas fa-file-pdf h-4 w-4"></i> Unduh PDF
                </a>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                    @click="printPage()"
                >
                    <i class="fas fa-print h-4 w-4"></i> Cetak / Simpan PDF
                </button>
            </div>
        </div>

        <div class="mx-auto max-w-[210mm] p-4 print:p-0">
            <div v-if="peringatan.length" class="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 print:hidden">
                Periksa sebelum mencetak: {{ peringatan.join(' · ') }}.
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 print:grid-cols-2 print:gap-2">
                <article
                    v-for="siswa in kartu"
                    :key="siswa.id_siswa"
                    class="break-inside-avoid rounded-lg border-2 border-slate-800 bg-white p-3 text-[11px] print:rounded-none"
                >
                    <header class="border-b border-slate-400 pb-1 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <img v-if="logo" :src="logo" alt="" class="h-10 w-10 object-contain" />
                            <div>
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
                            </div>
                        </div>
                        <p class="mt-1 text-[9px] uppercase">Kartu Peserta Ujian</p>
                        <p class="text-[10px] font-semibold">{{ tahun }} / {{ semester }}</p>
                    </header>

                    <dl class="mt-2 grid grid-cols-2 gap-x-2 gap-y-1">
                        <div class="flex gap-1.5">
                            <dt class="w-16 shrink-0 text-slate-600">No. Peserta</dt>
                            <dd class="font-mono text-[12px] font-bold">{{ siswa.nomor_peserta || '-' }}</dd>
                        </div>
                        <div class="flex gap-1.5">
                            <dt class="w-10 shrink-0 text-slate-600">Kelas</dt>
                            <dd class="font-medium">{{ siswa.kelas }}</dd>
                        </div>
                        <div class="col-span-2 flex gap-1.5">
                            <dt class="w-16 shrink-0 text-slate-600">Nama</dt>
                            <dd class="font-semibold">{{ siswa.nama }}</dd>
                        </div>
                        <div class="flex gap-1.5">
                            <dt class="w-16 shrink-0 text-slate-600">NIS / NISN</dt>
                            <dd>{{ siswa.nis || '-' }} / {{ siswa.nisn || '-' }}</dd>
                        </div>
                        <div class="flex gap-1.5">
                            <dt class="w-14 shrink-0 text-slate-600">Ruang / Sesi</dt>
                            <dd>{{ siswa.ruang || '-' }} / {{ siswa.sesi || '-' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-2 flex items-end justify-between gap-2 border-t border-dashed border-slate-300 pt-2">
                        <div v-if="siswa.verifikasi" class="flex items-center gap-2">
                            <div v-if="siswa.verifikasi.svg" class="qr h-20 w-20 shrink-0 rounded bg-white p-1.5" v-html="siswa.verifikasi.svg"></div>
                            <div class="text-[7px] leading-tight text-slate-500">
                                <p class="font-semibold text-slate-700">Verifikasi dokumen</p>
                                <p class="font-mono">{{ siswa.verifikasi.kode }}</p>
                                <p class="break-all">{{ siswa.verifikasi.url }}</p>
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

            <p v-if="!kartu.length" class="rounded-lg border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-500">
                Tidak ada siswa dengan jadwal ujian pada filter ini.
            </p>
        </div>
    </div>
</template>

<style scoped>
.qr :deep(svg) {
    display: block;
    width: 100%;
    height: 100%;
}
</style>
