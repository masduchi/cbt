<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import { btnPrimary, btnSecondary, cardClass, inputClass } from '@/ui';
import { askConfirm } from '@/confirm';

const props = defineProps({
    jadwalOptions: { type: Array, default: () => [] },
    selected: { type: Number, default: null },
    jadwal: { type: Object, default: null },
    students: { type: Array, default: () => [] },
    pagination: { type: Object, default: null },
    ringkasan: { type: Object, default: () => ({ peserta: 0, belum: 0, selesai: 0, perlu_tinjau: 0 }) },
    status: { type: String, default: 'semua' },
    periode: { type: String, default: '' },
    isAdmin: { type: Boolean, default: false },
    selisihPersen: { type: Number, default: 15 },
    penilai: { type: Array, default: () => [] },
    guruOptions: { type: Array, default: () => [] },
    rubrikRekap: { type: Array, default: () => [] },
});

const showPenilai = ref(false);
const dipilih = ref([]);

const perPage = computed(() => props.pagination?.per_page ?? 25);

function go(params) {
    router.get(
        '/cbt/koreksi',
        { ...params, ...(perPage.value !== 25 ? { per_page: perPage.value } : {}) },
        { preserveState: false },
    );
}

function changeJadwal(event) {
    const params = { jadwal: event.target.value };
    if (props.status !== 'semua') {
        params.status = props.status;
    }
    go(params);
}

function filter(status) {
    go({ jadwal: props.selected, status });
}

function bukaPenilai() {
    dipilih.value = [...props.penilai];
    showPenilai.value = true;
}

function toggleGuru(id) {
    const index = dipilih.value.indexOf(id);

    if (index === -1) {
        dipilih.value.push(id);
    } else {
        dipilih.value.splice(index, 1);
    }
}

function simpanPenilai() {
    router.post(
        `/cbt/koreksi/${props.selected}/penilai`,
        { guru: dipilih.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showPenilai.value = false;
            },
        },
    );
}

async function markAll() {
    if (!props.selected) return;

    if (
        await askConfirm(
            'Tandai semua jawaban esai pada jadwal ini sudah dikoreksi? Esai yang belum dinilai akan dihitung 0. Tindakan ini tidak membatalkan nilai yang sudah diisi.',
        )
    ) {
        router.post(`/cbt/koreksi/${props.selected}/tandai`, {}, { preserveScroll: true });
    }
}

const bolehMarkAll = computed(() => props.isAdmin && (props.jadwal?.wajib ?? 1) <= 1);
</script>

<template>
    <Head title="Koreksi Esai" />
    <AdminLayout title="Koreksi Esai">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Koreksi Jawaban Esai">
                    <template #description>Beri nilai jawaban uraian siswa. Nilai langsung diperbarui setelah disimpan. Periode {{ props.periode }}.</template>
                
                </PageHeader>
                <div class="w-full max-w-sm">
                    <label class="mb-1 block text-xs font-medium text-muted-foreground" for="jadwal-ujian">Jadwal Ujian</label>
                    <select :class="inputClass" :value="props.selected ?? ''" @change="changeJadwal" id="jadwal-ujian">
                        <option v-for="item in props.jadwalOptions" :key="item.id_jadwal" :value="item.id_jadwal">
                            {{ item.label }} · {{ item.tgl_mulai }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="!props.jadwal" :class="cardClass" class="p-10 text-center">
                <i class="fas fa-pen mx-auto h-10 w-10 text-muted-foreground/50" ></i>
                <p class="mt-3 text-sm font-medium text-foreground">Belum ada jadwal ujian pada periode aktif.</p>
            </div>

            <template v-else>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div :class="cardClass" class="p-4">
                        <div class="text-xs font-medium text-muted-foreground">Peserta esai</div>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ props.ringkasan.peserta }}</p>
                    </div>
                    <div :class="cardClass" class="p-4">
                        <div class="text-xs font-medium text-muted-foreground">Sudah dikoreksi</div>
                        <p class="mt-1 text-xl font-semibold text-success">{{ props.ringkasan.selesai }}</p>
                    </div>
                    <div :class="cardClass" class="p-4">
                        <div class="text-xs font-medium text-muted-foreground">Belum dikoreksi</div>
                        <p class="mt-1 text-xl font-semibold" :class="props.ringkasan.belum ? 'text-warning' : 'text-foreground'">{{ props.ringkasan.belum }}</p>
                    </div>
                    <div :class="cardClass" class="p-4">
                        <div class="text-xs font-medium text-muted-foreground">Perlu ditinjau</div>
                        <p class="mt-1 text-xl font-semibold" :class="props.ringkasan.perlu_tinjau ? 'text-warning' : 'text-foreground'">{{ props.ringkasan.perlu_tinjau }}</p>
                        <p class="text-xs text-muted-foreground">Selisih ≥ {{ props.selisihPersen }}% skor maksimal</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" :class="props.status === 'semua' ? btnPrimary : btnSecondary" @click="filter('semua')">Semua</button>
                    <button type="button" :class="props.status === 'belum' ? btnPrimary : btnSecondary" @click="filter('belum')">
                        Belum dikoreksi ({{ props.ringkasan.belum }})
                    </button>
                    <button type="button" :class="props.status === 'selesai' ? btnPrimary : btnSecondary" @click="filter('selesai')">Selesai</button>
                    <button
                        type="button"
                        :class="props.status === 'tinjau' ? btnPrimary : btnSecondary"
                        @click="filter('tinjau')"
                    >
                        <i class="fas fa-triangle-exclamation h-3.5 w-3.5"></i>
                        Perlu ditinjau ({{ props.ringkasan.perlu_tinjau }})
                    </button>
                </div>

                <div v-if="props.rubrikRekap.length" :class="cardClass" class="p-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="text-sm font-semibold text-foreground">Rekap rata-rata per kriteria rubrik</p>
                        <p class="text-xs text-muted-foreground">Seluruh jawaban esai pada jadwal ini</p>
                    </div>
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-muted-foreground">
                                    <th class="pb-2 pr-4 font-semibold">Kriteria</th>
                                    <th class="pb-2 pr-4 text-right font-semibold">Skor maks</th>
                                    <th class="pb-2 pr-4 text-right font-semibold">Penilaian</th>
                                    <th class="pb-2 pr-4 text-right font-semibold">Rata-rata</th>
                                    <th class="pb-2 font-semibold">Capaian</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="item in props.rubrikRekap" :key="item.id_rubrik">
                                    <td class="py-2 pr-4 text-foreground">{{ item.kriteria }}</td>
                                    <td class="py-2 pr-4 text-right text-muted-foreground">{{ item.skor_maks }}</td>
                                    <td class="py-2 pr-4 text-right text-muted-foreground">{{ item.jumlah }}</td>
                                    <td class="py-2 pr-4 text-right font-semibold text-foreground">{{ item.rata_rata }}</td>
                                    <td class="py-2">
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-muted">
                                                <div class="h-full rounded-full bg-primary" :style="{ width: `${Math.min(item.persen, 100)}%` }" />
                                            </div>
                                            <span class="text-xs text-muted-foreground">{{ item.persen }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div :class="cardClass" class="flex flex-wrap items-center justify-between gap-4 p-4">
                    <div>
                        <p class="text-sm font-semibold text-foreground">{{ props.jadwal.bank_nama }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ props.jadwal.jenis }} · {{ props.jadwal.tgl_mulai }} ·
                            {{ props.jadwal.tampil_esai }} soal esai · bobot {{ props.jadwal.bobot_esai }} ·
                            {{ props.jadwal.penilai }} korektor ditugaskan
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" :class="btnSecondary" @click="bukaPenilai">
                            <i class="fas fa-users h-4 w-4"></i> Atur korektor
                        </button>
                        <button
                            v-if="bolehMarkAll"
                            type="button"
                            :class="btnSecondary"
                            @click="markAll"
                        >
                            <i class="fas fa-check-circle h-4 w-4"></i> Tandai semua terkoreksi
                        </button>
                    </div>
                </div>

                <div :class="cardClass" class="overflow-x-auto">
                    <table class="data-table min-w-full divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">Peserta</th>
                                <th class="px-4 py-3">Kelas</th>
                                <th class="px-4 py-3">Progres</th>
                                <th class="px-4 py-3">Nilai Esai</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in props.students" :key="row.id_siswa" class="hover:bg-muted/40">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-foreground">{{ row.nama }}</p>
                                    <p class="text-xs text-muted-foreground">{{ row.nis }}</p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.kelas || '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-24 overflow-hidden rounded-full bg-muted">
                                            <div
                                                class="h-full rounded-full bg-primary/100"
                                                :style="{ width: `${row.jumlah_esai ? (row.terkoreksi / row.jumlah_esai) * 100 : 0}%` }"
                                            />
                                        </div>
                                        <span class="text-xs text-muted-foreground">{{ row.terkoreksi }}/{{ row.jumlah_esai }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Anda: {{ row.saya }}/{{ row.jumlah_esai }} soal
                                        <span v-if="row.wajib > 1"> · {{ row.wajib }} korektor</span>
                                    </p>
                                    <span
                                        v-if="row.perlu_tinjau"
                                        class="mt-1 inline-flex items-center gap-1 rounded-full border border-warning/40 bg-warning/10 px-2 py-0.5 text-xs font-medium text-warning"
                                    >
                                        <i class="fas fa-triangle-exclamation h-3 w-3"></i>
                                        {{ row.perlu_tinjau }} soal perlu ditinjau
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-foreground">
                                    <span v-if="row.dikoreksi" class="inline-flex items-center gap-1 text-success">
                                        <i class="fas fa-check-circle h-4 w-4" ></i> {{ row.nilai_esai }}
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 text-warning">
                                        <i class="fas fa-hourglass-half h-4 w-4" ></i> {{ row.nilai_esai }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="`/cbt/koreksi/${props.selected}/${row.id_siswa}`" :class="btnPrimary">
                                        <i class="fas fa-pen h-4 w-4" ></i> Koreksi
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!props.students.length">
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                    <template v-if="props.status === 'belum'">Tidak ada jawaban esai yang belum dikoreksi.</template>
                                    <template v-else-if="props.status === 'selesai'">Belum ada peserta yang tuntas dikoreksi.</template>
                                    <template v-else-if="props.status === 'tinjau'">Tidak ada jawaban dengan selisih korektor besar.</template>
                                    <template v-else>Belum ada jawaban esai pada jadwal ini.</template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-if="props.pagination" :paginator="props.pagination" />
            </template>
        </div>

        <Modal :show="showPenilai" title="Atur korektor esai" max-width="max-w-xl" @close="showPenilai = false">
            <p class="text-sm text-muted-foreground">
                Pilih guru yang ikut menilai esai pada jadwal ini. Nilai akhir adalah rata-rata seluruh korektor,
                dan selisih ≥ {{ props.selisihPersen }}% dari skor maksimal ditandai perlu ditinjau.
                Tanpa pilihan, pemilik bank soal (atau admin) menjadi korektor tunggal.
                Hanya guru dengan akun pengguna aktif yang dapat ditugaskan; guru tanpa akun
                tidak akan muncul di daftar ini.
            </p>

            <div class="mt-4 max-h-72 space-y-1 overflow-y-auto rounded-lg border border-border p-2">
                <label
                    v-for="guru in props.guruOptions"
                    :key="guru.id_guru"
                    class="flex items-center gap-3 rounded-md px-2 py-1.5 text-sm text-foreground hover:bg-muted"
                >
                    <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-border text-primary"
                        :checked="dipilih.includes(guru.id_guru)"
                        @change="toggleGuru(guru.id_guru)"
                    />
                    {{ guru.nama }}
                </label>
                <p v-if="!props.guruOptions.length" class="p-3 text-sm text-muted-foreground">Belum ada data guru.</p>
            </div>

            <p class="mt-2 text-xs text-muted-foreground">{{ dipilih.length }} guru dipilih.</p>

            <template #footer>
                <button type="button" :class="btnSecondary" @click="showPenilai = false">Batal</button>
                <button type="button" :class="btnPrimary" @click="simpanPenilai">Simpan penugasan</button>
            </template>
        </Modal>
    </AdminLayout>
</template>
