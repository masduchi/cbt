<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, btnSecondary, cardClass, inputClass } from '@/ui';
import { askConfirm } from '@/confirm';
import RichContent from '@/Components/RichContent.vue';

const props = defineProps({
    jadwal: { type: Object, required: true },
    siswa: { type: Object, required: true },
    jawaban: { type: Array, default: () => [] },
    nilai: { type: Object, default: null },
    wajib: { type: Number, default: 1 },
    isAdmin: { type: Boolean, default: false },
    bolehMenilai: { type: Boolean, default: true },
    selisihPersen: { type: Number, default: 15 },
});

const komponenLabel = {
    pg: 'Pilihan Ganda',
    kompleks: 'PG Kompleks',
    jodohkan: 'Menjodohkan',
    isian: 'Isian',
    esai: 'Esai',
};

const form = useForm({ nilai: {}, catatan: {}, rincian: {}, final: {} });

props.jawaban.forEach((row) => {
    const saya = row.koreksi_saya;
    form.nilai[row.id_soal_siswa] = Number(saya?.skor ?? row.agregat?.skor ?? 0);
    form.catatan[row.id_soal_siswa] = saya?.catatan ?? row.final?.catatan ?? '';
    form.final[row.id_soal_siswa] = Boolean(row.final);
    form.rincian[row.id_soal_siswa] = Object.fromEntries(
        (row.rubrik || []).map((item) => [
            item.id_rubrik,
            Number(saya?.rincian?.[item.id_rubrik] ?? 0),
        ])
    );
});

function finalBaru(row) {
    return Boolean(form.final[row.id_soal_siswa]) && !row.final;
}

function selisihBesar(row) {
    return Boolean(row.agregat?.perlu_tinjau) && (row.agregat?.jumlah ?? 0) > 1;
}

function totalRincian(row) {
    const rincian = form.rincian[row.id_soal_siswa] || {};
    return (row.rubrik || []).reduce(
        (total, item) => total + (Number(rincian[item.id_rubrik]) || 0),
        0
    );
}

function totalMaksRubrik(row) {
    return (row.rubrik || []).reduce((total, item) => total + (Number(item.skor_maks) || 0), 0);
}

function skorRubrik(row) {
    const maksRubrik = totalMaksRubrik(row);
    if (maksRubrik <= 0) {
        return 0;
    }

    return Math.round((totalRincian(row) / maksRubrik) * (Number(row.maksimal) || 0) * 100) / 100;
}

function sudahDinilai(row) {
    return Boolean(row.koreksi_saya);
}

/** Menjaga skor kriteria tetap di rentang 0..skor_maks sebelum dikirim. */
function clampRincian(row, item) {
    const rincian = form.rincian[row.id_soal_siswa] || {};
    const batas = Number(item.skor_maks) || 0;
    const nilai = Number(rincian[item.id_rubrik]);

    form.rincian[row.id_soal_siswa][item.id_rubrik] = Number.isFinite(nilai)
        ? Math.min(Math.max(nilai, 0), batas)
        : 0;
}

function clampNilai(row) {
    const batas = Number(row.maksimal) || 0;
    const nilai = Number(form.nilai[row.id_soal_siswa]);

    form.nilai[row.id_soal_siswa] = Number.isFinite(nilai)
        ? Math.min(Math.max(nilai, 0), batas)
        : 0;
}

/** Status penyimpanan skor saya untuk satu jawaban. */
function statusSimpan(row) {
    const saya = row.koreksi_saya;

    if (!saya) {
        return 'belum';
    }

    const skorSekarang = row.rubrik?.length
        ? skorRubrik(row)
        : Number(form.nilai[row.id_soal_siswa]) || 0;

    if (Math.abs(skorSekarang - (Number(saya.skor) || 0)) > 0.01) {
        return 'berubah';
    }

    if ((form.catatan[row.id_soal_siswa] ?? '') !== (saya.catatan ?? '')) {
        return 'berubah';
    }

    const rincianBerubah = (row.rubrik || []).some((item) => {
        const sekarang = Number(form.rincian[row.id_soal_siswa]?.[item.id_rubrik]) || 0;
        const tersimpan = Number(saya.rincian?.[item.id_rubrik]) || 0;

        return Math.abs(sekarang - tersimpan) > 0.001;
    });

    return rincianBerubah ? 'berubah' : 'tersimpan';
}

const inputRefs = new Map();
const fokusId = ref(null);

function setInputRef(id, el) {
    if (el) {
        inputRefs.set(id, el);
    } else {
        inputRefs.delete(id);
    }
}

onMounted(async () => {
    if (!props.bolehMenilai) {
        return;
    }

    const target = props.jawaban.find((row) => !row.koreksi_saya);

    if (!target) {
        return;
    }

    fokusId.value = target.id_soal_siswa;
    await nextTick();

    const input = inputRefs.get(target.id_soal_siswa);

    if (input) {
        input.focus({ preventScroll: true });
        input.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
});

const progres = computed(() => {
    const total = props.jawaban.length;
    const selesai = props.jawaban.filter((row) => row.agregat?.lengkap).length;
    const ditinjau = props.jawaban.filter((row) => row.agregat?.perlu_tinjau).length;

    return { total, selesai, ditinjau };
});

async function simpan() {
    props.jawaban.forEach((row) => {
        if (row.rubrik?.length) {
            row.rubrik.forEach((item) => clampRincian(row, item));
            form.nilai[row.id_soal_siswa] = skorRubrik(row);
        } else {
            clampNilai(row);
        }
    });

    const perluKonfirmasi = props.isAdmin
        ? props.jawaban.filter((row) => finalBaru(row) && selisihBesar(row))
        : [];

    if (perluKonfirmasi.length) {
        const nomor = perluKonfirmasi.map((row) => row.nomor).join(', ');
        const lanjut = await askConfirm(
            `Tetapkan nilai final untuk soal ${nomor}? Nilai final mengalahkan rata-rata korektor dan akan dipakai sebagai nilai esai siswa.`,
        );

        if (!lanjut) {
            return;
        }
    }

    form.post(`/cbt/koreksi/${props.jadwal.id_jadwal}/${props.siswa.id_siswa}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Koreksi ${props.siswa.nama}`" />
    <AdminLayout title="Koreksi Esai">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader
                    :title="props.siswa.nama"
                    :back="{ href: `/cbt/koreksi?jadwal=${props.jadwal.id_jadwal}`, label: 'Kembali ke daftar koreksi' }"
                >
                    <template #description>
                        {{ props.siswa.nis }}
                        <span v-if="props.siswa.kelas"> · {{ props.siswa.kelas }}</span>
                        · {{ props.jadwal.bank_nama }}
                    </template>
                </PageHeader>
                <div v-if="props.nilai" class="rounded-xl border border-border bg-card px-4 py-3 text-xs shadow-card">
                    <p class="font-semibold text-foreground">Komponen nilai</p>
                    <div class="mt-1 grid grid-cols-2 gap-x-4 gap-y-0.5 text-muted-foreground">
                        <span v-for="(label, key) in komponenLabel" :key="key">{{ label }}: <b class="text-foreground">{{ props.nilai[key] }}</b></span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <span class="rounded-full border border-border bg-card px-3 py-1">
                    <b class="text-foreground">{{ progres.selesai }}/{{ progres.total }}</b> jawaban lengkap
                </span>
                <span class="rounded-full border border-border bg-card px-3 py-1">
                    {{ props.wajib }} korektor per jawaban
                </span>
                <span
                    v-if="progres.ditinjau"
                    class="rounded-full border border-warning/40 bg-warning/10 px-3 py-1 font-medium text-warning"
                >
                    {{ progres.ditinjau }} jawaban perlu ditinjau
                </span>
            </div>

            <div
                v-if="!props.bolehMenilai"
                class="rounded-xl border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-warning"
                role="status"
            >
                <i class="fas fa-circle-info mr-1"></i>
                Anda tidak ditugaskan sebagai korektor pada jadwal ini. Anda tetap dapat menetapkan
                <b>nilai final</b>; centang "Tetapkan sebagai nilai final" pada soal yang ingin ditengahi.
            </div>

            <form class="space-y-4" @submit.prevent="simpan">
                <div
                    v-for="row in props.jawaban"
                    :key="row.id_soal_siswa"
                    :class="[cardClass, fokusId === row.id_soal_siswa ? 'ring-2 ring-primary/40' : '']"
                    class="p-5"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Soal {{ row.nomor }}</p>
                            <RichContent :html="row.soal" class="prose prose-sm mt-2 max-w-none text-foreground" />
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <span
                                v-if="row.agregat?.perlu_tinjau"
                                class="rounded-full border border-warning/40 bg-warning/10 px-2.5 py-1 text-xs font-medium text-warning"
                                :title="`Selisih korektor mencapai ${props.selisihPersen}% dari skor maksimal`"
                            >
                                <i class="fas fa-triangle-exclamation mr-1 h-3.5 w-3.5"></i> Perlu ditinjau
                            </span>
                            <span
                                v-if="row.final"
                                class="rounded-full border border-primary/30 bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary"
                                :title="`Nilai final oleh ${row.final.nama}`"
                            >
                                <i class="fas fa-gavel mr-1 h-3.5 w-3.5"></i> Final {{ row.final.skor }}
                            </span>
                            <span
                                v-if="props.bolehMenilai"
                                class="rounded-full border px-2.5 py-1 text-xs font-medium"
                                :class="statusSimpan(row) === 'tersimpan'
                                    ? 'border-success/30 bg-success/10 text-success'
                                    : statusSimpan(row) === 'berubah'
                                        ? 'border-warning/40 bg-warning/10 text-warning'
                                        : 'border-border bg-muted text-muted-foreground'"
                            >
                                <template v-if="statusSimpan(row) === 'tersimpan'"><i class="fas fa-check mr-1 h-3.5 w-3.5"></i> Tersimpan</template>
                                <template v-else-if="statusSimpan(row) === 'berubah'"><i class="fas fa-pen mr-1 h-3.5 w-3.5"></i> Belum disimpan</template>
                                <template v-else><i class="fas fa-circle-minus mr-1 h-3.5 w-3.5"></i> Belum dinilai</template>
                            </span>
                            <span class="rounded-full border px-2.5 py-1 text-xs font-medium"
                                :class="row.agregat?.lengkap ? 'border-success/30 bg-success/10 text-success' : 'border-warning/30 bg-warning/10 text-warning'">
                                <span v-if="row.agregat?.lengkap" class="inline-flex items-center gap-1"><i class="fas fa-check-circle h-3.5 w-3.5"></i> Lengkap</span>
                                <span v-else>{{ row.agregat?.jumlah ?? 0 }}/{{ props.wajib }} korektor</span>
                            </span>
                        </div>
                    </div>

                    <div class="mt-3 rounded-lg border border-border bg-muted/60 px-3 py-2 text-sm text-foreground whitespace-pre-line">{{ row.jawaban || '(tidak dijawab)' }}</div>

                    <div v-if="row.rubrik?.length" class="mt-4 space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Rubrik penilaian</p>
                            <p class="text-xs text-muted-foreground">
                                Skor kriteria {{ totalRincian(row) }} / {{ totalMaksRubrik(row) }}
                            </p>
                        </div>
                        <div v-for="item in row.rubrik" :key="item.id_rubrik" class="flex items-center gap-3">
                            <label :for="`rincian-${row.id_soal_siswa}-${item.id_rubrik}`" class="min-w-0 flex-1 text-sm text-foreground">
                                {{ item.kriteria }}
                                <span class="text-xs text-muted-foreground">(maks {{ item.skor_maks }})</span>
                            </label>
                            <div class="w-24 shrink-0">
                                <input
                                    :id="`rincian-${row.id_soal_siswa}-${item.id_rubrik}`"
                                    :ref="(el) => item.id_rubrik === row.rubrik[0].id_rubrik && setInputRef(row.id_soal_siswa, el)"
                                    v-model.number="form.rincian[row.id_soal_siswa][item.id_rubrik]"
                                    type="number"
                                    min="0"
                                    :max="item.skor_maks"
                                    step="0.01"
                                    :class="inputClass"
                                    @change="clampRincian(row, item)"
                                />
                            </div>
                        </div>
                        <p class="text-right text-sm text-foreground">
                            Skor soal: <b>{{ skorRubrik(row) }}</b> / {{ row.maksimal }}
                        </p>
                    </div>

                    <div v-else class="mt-3 flex flex-wrap items-end gap-3">
                        <div class="w-32">
                            <label :for="`nilai-${row.id_soal_siswa}`" class="mb-1 block text-xs font-medium text-muted-foreground">Nilai (maks {{ row.maksimal }})</label>
                            <input
                                :id="`nilai-${row.id_soal_siswa}`"
                                :ref="(el) => setInputRef(row.id_soal_siswa, el)"
                                v-model.number="form.nilai[row.id_soal_siswa]"
                                type="number"
                                min="0"
                                :max="row.maksimal || 100"
                                step="0.01"
                                :class="inputClass"
                                @change="clampNilai(row)"
                            />
                        </div>
                        <p v-if="form.errors[`nilai.${row.id_soal_siswa}`]" class="text-xs text-destructive">{{ form.errors[`nilai.${row.id_soal_siswa}`] }}</p>
                    </div>

                    <div class="mt-3">
                        <label :for="`catatan-${row.id_soal_siswa}`" class="mb-1 block text-xs font-medium text-muted-foreground">Catatan untuk siswa / korektor lain (opsional)</label>
                        <textarea
                            :id="`catatan-${row.id_soal_siswa}`"
                            v-model="form.catatan[row.id_soal_siswa]"
                            rows="2"
                            :class="inputClass"
                            placeholder="Alasan pemberian skor atau hal yang perlu diperiksa"
                        />
                        <p v-if="form.errors[`catatan.${row.id_soal_siswa}`]" class="mt-1 text-xs text-destructive">{{ form.errors[`catatan.${row.id_soal_siswa}`] }}</p>
                    </div>

                    <div v-if="row.koreksi_lain?.length || row.final" class="mt-3 rounded-lg border border-border bg-muted/40 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Koreksi korektor lain</p>
                        <ul v-if="row.koreksi_lain?.length" class="mt-1 space-y-1 text-sm text-foreground">
                            <li v-for="(lain, index) in row.koreksi_lain" :key="index" class="flex flex-wrap items-baseline gap-x-2">
                                <span class="font-medium">{{ lain.nama }}</span>
                                <span class="rounded bg-card px-1.5 py-0.5 text-xs font-semibold">{{ lain.skor }}</span>
                                <span v-if="lain.catatan" class="text-xs text-muted-foreground">— {{ lain.catatan }}</span>
                            </li>
                        </ul>
                        <p v-else class="mt-1 text-sm text-muted-foreground">Belum ada korektor lain.</p>
                        <p v-if="row.final" class="mt-2 rounded border border-primary/30 bg-primary/10 px-2 py-1 text-xs text-primary">
                            <i class="fas fa-gavel mr-1"></i>
                            Nilai final <b>{{ row.final.skor }}</b> oleh {{ row.final.nama }}
                            <span v-if="row.final.catatan">— {{ row.final.catatan }}</span>
                        </p>
                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ row.final ? 'Rata-rata korektor' : 'Nilai saat ini' }}:
                            <b class="text-foreground">{{ row.agregat?.skor ?? '—' }}</b>
                            <span v-if="row.agregat?.selisih !== null && row.agregat?.selisih !== undefined"> · selisih {{ row.agregat.selisih }}</span>
                        </p>
                    </div>

                    <label v-if="props.isAdmin" class="mt-3 flex items-start gap-2 text-sm text-foreground">
                        <input v-model="form.final[row.id_soal_siswa]" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-border text-primary" />
                        <span>
                            Tetapkan skor ini sebagai nilai final (mengalahkan rata-rata korektor)
                            <span v-if="selisihBesar(row) && finalBaru(row)" class="ml-1 rounded bg-warning/10 px-1.5 py-0.5 text-xs font-medium text-warning">
                                selisih korektor besar — periksa kembali
                            </span>
                            <span v-else-if="row.final && !form.final[row.id_soal_siswa]" class="ml-1 rounded bg-destructive/10 px-1.5 py-0.5 text-xs font-medium text-destructive">
                                nilai final akan dihapus, rata-rata korektor berlaku lagi
                            </span>
                        </span>
                    </label>

                    <p v-if="props.bolehMenilai" class="mt-2 text-xs text-muted-foreground">
                        <span v-if="sudahDinilai(row)" class="text-success"><i class="fas fa-check mr-1"></i> Anda sudah menilai jawaban ini; skor Anda dapat diubah.</span>
                        <span v-else>Anda belum menilai jawaban ini.</span>
                    </p>
                </div>

                <div class="sticky bottom-4 flex flex-wrap items-center justify-end gap-2 rounded-xl border border-border bg-card/95 px-4 py-3 shadow-lg backdrop-blur">
                    <span class="mr-auto text-xs text-muted-foreground">{{ props.jawaban.length }} jawaban esai</span>
                    <Link :href="`/cbt/koreksi?jadwal=${props.jadwal.id_jadwal}`" :class="btnSecondary">Batal</Link>
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">
                        <i class="fas fa-save h-4 w-4"></i> {{ form.processing ? 'Menyimpan…' : 'Simpan Nilai' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
