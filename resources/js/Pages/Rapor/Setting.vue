<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import { btnPrimary, btnSecondary, cardClass, inputClass, labelClass } from '@/ui';
import { askAlert } from '@/confirm';

const props = defineProps({
    setting: { type: Object, default: null },
    tahun: { type: String, default: '' },
    semester: { type: String, default: '' },
    semesterNama: { type: String, default: '' },
    classes: { type: Array, default: () => [] },
    gurus: { type: Array, default: () => [] },
    mapels: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
});

const form = useForm({
    kkm_tunggal: props.setting?.kkm_tunggal ?? 0,
    kkm: props.setting?.kkm ?? 75,
    bobot_ph: props.setting?.bobot_ph ?? 40,
    bobot_pts: props.setting?.bobot_pts ?? 30,
    bobot_pas: props.setting?.bobot_pas ?? 30,
    bobot_absen: props.setting?.bobot_absen ?? 10,
    tgl_rapor_pts: props.setting?.tgl_rapor_pts ?? '',
    tgl_rapor_akhir: props.setting?.tgl_rapor_akhir ?? '',
    tgl_rapor_kelas_akhir: props.setting?.tgl_rapor_kelas_akhir ?? '',
    nip_kepsek: Number(props.setting?.nip_kepsek ?? 0),
    nip_walikelas: Number(props.setting?.nip_walikelas ?? 0),
});

const totalBobot = computed(() => Number(form.bobot_ph) + Number(form.bobot_pts) + Number(form.bobot_pas));

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const backupForm = useForm({ file: null, confirm: '' });
const restoreOpen = ref(false);

const restoreName = computed(() => backupForm.file?.name ?? '');
const restoreReady = computed(() => restoreName.value !== '' && backupForm.confirm === restoreName.value);

function openRestore() {
    if (!backupForm.file) {
        askAlert('Pilih berkas backup JSON terlebih dahulu.');
        return;
    }

    backupForm.confirm = '';
    backupForm.clearErrors();
    restoreOpen.value = true;
}

/**
 * Nama berkas harus diketik ulang agar satu klik tidak bisa menimpa seluruh
 * nilai rapor. Server memeriksa ulang konfirmasi yang sama.
 */
function submitRestore() {
    if (!restoreReady.value) {
        return;
    }

    backupForm.post('/rapor/backup', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            restoreOpen.value = false;
            backupForm.reset();
        },
    });
}

const selectedGuru = ref(props.gurus[0]?.id_guru ?? null);

const state = reactive({});
for (const assignment of props.assignments) {
    const mapelState = {};
    for (const item of assignment.mapel) {
        mapelState[item.id_mapel] = [...item.kelas];
    }
    state[assignment.id_guru] = mapelState;
}

const guruAssignments = computed(() => {
    const rows = [];
    for (const assignment of props.assignments) {
        const guru = props.gurus.find((g) => g.id_guru === assignment.id_guru);
        const count = assignment.mapel.reduce((total, mapel) => total + mapel.kelas.length, 0);
        rows.push({
            id_guru: assignment.id_guru,
            nama_guru: guru?.nama_guru ?? `Guru #${assignment.id_guru}`,
            jumlah: count,
            mapel: assignment.mapel.map((mapel) => ({
                nama: props.mapels.find((m) => m.id_mapel === mapel.id_mapel)?.nama_mapel ?? `Mapel #${mapel.id_mapel}`,
                kelas: mapel.kelas.length,
            })),
        });
    }
    return rows;
});

function ensureGuru(id) {
    if (!state[id]) {
        state[id] = {};
    }
    return state[id];
}

function toggle(idGuru, idMapel, idKelas) {
    const row = ensureGuru(idGuru);
    const list = row[idMapel] ?? [];
    const index = list.indexOf(idKelas);

    if (index >= 0) {
        list.splice(index, 1);
    } else {
        list.push(idKelas);
    }

    row[idMapel] = list;
}

function isChecked(idGuru, idMapel, idKelas) {
    return (state[idGuru]?.[idMapel] ?? []).includes(idKelas);
}

function guruMapelCount(idGuru) {
    const row = state[idGuru] ?? {};
    return Object.values(row).reduce((total, kelas) => total + kelas.length, 0);
}

function saveSetting() {
    form.post('/rapor/pengaturan', { preserveScroll: true });
}

function saveWali(kelas, event) {
    router.post(
        '/rapor/pengaturan/wali',
        { id_kelas: kelas.id_kelas, guru_id: event.target.value ? Number(event.target.value) : null },
        { preserveScroll: true },
    );
}

function savePengampu() {
    if (!selectedGuru.value) return;

    const mapel = Object.entries(state[selectedGuru.value] ?? {})
        .filter(([, kelas]) => kelas.length > 0)
        .map(([id_mapel, kelas]) => ({ id_mapel: Number(id_mapel), kelas }));

    router.post('/rapor/pengaturan/pengampu', { id_guru: selectedGuru.value, mapel }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Pengaturan Rapor" />
    <AdminLayout title="Pengaturan Rapor">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Pengaturan Rapor">
                    <template #description>Tahun pelajaran {{ tahun }} · Semester {{ semester }} ({{ semesterNama }}). Pengaturan berlaku untuk periode aktif.</template>
                
                </PageHeader></div>

            <section v-if="isAdmin" :class="cardClass">
                <div class="border-b border-border px-5 py-4">
                    <h2 class="text-sm font-semibold text-foreground">Cadangan Nilai Rapor</h2>
                    <p class="text-xs text-muted-foreground">
                        Unduh seluruh nilai rapor (KKM, KIKD, harian, PTS, PAS, sikap, ekstra, catatan) sebagai JSON, atau pulihkan dari berkas cadangan.
                    </p>
                </div>
                <div class="flex flex-wrap items-end gap-3 p-5">
                    <a href="/rapor/backup" :class="btnSecondary">
                        <i class="fas fa-download h-4 w-4" ></i> Unduh Backup
                    </a>
                    <div class="min-w-56 flex-1">
                        <label :class="labelClass" for="berkas-backup-json">Berkas Backup (.json)</label>
                        <input
 type="file"
 accept="application/json,.json"
 :class="inputClass"
 @input="backupForm.file = $event.target.files[0]"
 id="berkas-backup-json" />
                        <p v-if="backupForm.errors.file" class="mt-1 text-xs text-destructive">{{ backupForm.errors.file }}</p>
                    </div>
                    <button type="button" :class="btnPrimary" :disabled="backupForm.processing" @click="openRestore">
                        <i class="fas fa-upload h-4 w-4" ></i> Pulihkan
                    </button>
                </div>
            </section>

            <Modal :show="restoreOpen" title="Pulihkan Nilai Rapor" @close="restoreOpen = false">
                <form class="space-y-4" @submit.prevent="submitRestore">
                    <div class="rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                        <p class="font-medium">Seluruh nilai rapor periode ini akan diganti dengan isi berkas.</p>
                        <p class="mt-1 text-xs">Tindakan ini tidak dapat dibatalkan. Unduh backup terbaru terlebih dahulu bila ragu.</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="rapor-restore-confirm">Ketik nama berkas untuk konfirmasi</label>
                        <p class="mb-1 break-all rounded bg-muted px-2 py-1 font-mono text-xs text-foreground">{{ restoreName }}</p>
                        <input
                            id="rapor-restore-confirm"
                            v-model="backupForm.confirm"
                            :class="inputClass"
                            autocomplete="off"
                            placeholder="nama-berkas.json"
                            :aria-label="'Konfirmasi pulihkan nilai rapor dari ' + restoreName"
                        />
                        <p v-if="backupForm.errors.confirm" class="mt-1 text-xs text-destructive">{{ backupForm.errors.confirm }}</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" :class="btnSecondary" @click="restoreOpen = false">Batal</button>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-destructive px-3.5 py-2 text-sm font-medium text-white transition hover:bg-destructive/90 disabled:opacity-50"
                            :disabled="backupForm.processing || !restoreReady"
                        >
                            Pulihkan nilai rapor
                        </button>
                    </div>
                </form>
            </Modal>

            <form :class="cardClass" class="space-y-5 p-5" @submit.prevent="saveSetting">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">KKM & Bobot Global</h2>
                        <p class="text-xs text-muted-foreground">Dipakai saat nilai KKM tunggal diaktifkan.</p>
                    </div>
                    <button type="submit" :class="btnPrimary"><i class="fas fa-save h-4 w-4" ></i> Simpan</button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label :class="labelClass" for="kkm-tunggal">KKM Tunggal</label>
                        <select v-model.number="form.kkm_tunggal" :class="inputClass" id="kkm-tunggal">
                            <option :value="0">Tidak (per mata pelajaran)</option>
                            <option :value="1">Ya (satu KKM untuk semua)</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelClass" for="kkm">KKM</label>
                        <input v-model.number="form.kkm" type="number" min="0" max="100" :class="inputClass" id="kkm" />
                    </div>
                    <div>
                        <label :class="labelClass" for="bobot-absensi">Bobot Absensi (%)</label>
                        <input v-model.number="form.bobot_absen" type="number" min="0" max="100" :class="inputClass" id="bobot-absensi" />
                    </div>
                    <div>
                        <label :class="labelClass" for="bobot-ph">Bobot PH (%)</label>
                        <input v-model.number="form.bobot_ph" type="number" min="0" max="100" :class="inputClass" id="bobot-ph" />
                    </div>
                    <div>
                        <label :class="labelClass" for="bobot-pts">Bobot PTS (%)</label>
                        <input v-model.number="form.bobot_pts" type="number" min="0" max="100" :class="inputClass" id="bobot-pts" />
                    </div>
                    <div>
                        <label :class="labelClass" for="bobot-pas">Bobot PAS (%)</label>
                        <input v-model.number="form.bobot_pas" type="number" min="0" max="100" :class="inputClass" id="bobot-pas" />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <p class="text-xs" :class="totalBobot === 100 ? 'text-success' : 'text-destructive'">
                            Total bobot PH + PTS + PAS: {{ totalBobot }}% {{ totalBobot === 100 ? '' : '(harus 100%)' }}
                        </p>
                        <p v-if="form.errors.bobot_pas" class="mt-1 text-xs text-destructive">{{ form.errors.bobot_pas }}</p>
                    </div>
                </div>

                <div class="grid gap-4 border-t border-border pt-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label :class="labelClass" for="tanggal-rapor-pts">Tanggal Rapor PTS</label>
                        <input v-model="form.tgl_rapor_pts" type="text" :class="inputClass" placeholder="Contoh: 12 Oktober 2026" id="tanggal-rapor-pts" />
                    </div>
                    <div>
                        <label :class="labelClass" for="tanggal-rapor-akhir">Tanggal Rapor Akhir</label>
                        <input v-model="form.tgl_rapor_akhir" type="text" :class="inputClass" placeholder="Contoh: 20 Desember 2026" id="tanggal-rapor-akhir" />
                    </div>
                    <div>
                        <label :class="labelClass" for="tanggal-rapor-kelas-akhir">Tanggal Rapor Kelas Akhir</label>
                        <input v-model="form.tgl_rapor_kelas_akhir" type="text" :class="inputClass" placeholder="Contoh: 22 Juni 2027" id="tanggal-rapor-kelas-akhir" />
                    </div>
                    <div>
                        <label :class="labelClass" for="nip-kepala-sekolah-di-rapor">NIP Kepala Sekolah di Rapor</label>
                        <select v-model.number="form.nip_kepsek" :class="inputClass" id="nip-kepala-sekolah-di-rapor">
                            <option :value="0">Tidak dicantumkan</option>
                            <option :value="1">Ya, ambil dari profil sekolah</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelClass" for="nip-wali-kelas-di-rapor">NIP Wali Kelas di Rapor</label>
                        <select v-model.number="form.nip_walikelas" :class="inputClass" id="nip-wali-kelas-di-rapor">
                            <option :value="0">Tidak dicantumkan</option>
                            <option :value="1">Ya, ambil dari data guru</option>
                        </select>
                    </div>
                </div>
            </form>

            <div :class="cardClass" class="overflow-hidden">
                <div class="flex items-center gap-2 border-b border-border px-5 py-4">
                    <i class="fas fa-user-check h-4 w-4 text-primary" ></i>
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Wali Kelas</h2>
                        <p class="text-xs text-muted-foreground">Wali kelas dapat mengisi sikap, catatan, prestasi, fisik, ekstra, dan kenaikan.</p>
                    </div>
                </div>
                <div class="divide-y divide-border">
                    <div v-for="kelas in classes" :key="kelas.id_kelas" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-40">
                            <p class="text-sm font-medium text-foreground">{{ kelas.nama_kelas }}</p>
                            <p class="text-xs text-muted-foreground">{{ kelas.wali ?? 'Belum ada wali kelas' }}</p>
                        </div>
                        <select :class="inputClass" class="max-w-56" :value="kelas.guru_id ?? ''" @change="saveWali(kelas, $event)" :aria-label="`Wali kelas ${kelas.nama_kelas}`">
                            <option value="">Pilih wali kelas</option>
                            <option v-for="guru in gurus" :key="guru.id_guru" :value="guru.id_guru">{{ guru.nama_guru }}</option>
                        </select>
                    </div>
                    <p v-if="!classes.length" class="px-5 py-6 text-center text-sm text-muted-foreground">Belum ada kelas pada periode aktif.</p>
                </div>
            </div>

            <div :class="cardClass" class="overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-users h-4 w-4 text-primary" ></i>
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">Guru Pengampu</h2>
                            <p class="text-xs text-muted-foreground">Tentukan mata pelajaran dan kelas yang diampu setiap guru.</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <select v-model.number="selectedGuru" :class="inputClass" class="max-w-56" aria-label="Pilih guru pengampu">
                            <option v-for="guru in gurus" :key="guru.id_guru" :value="guru.id_guru">{{ guru.nama_guru }}</option>
                        </select>
                        <button type="button" :class="btnPrimary" @click="savePengampu"><i class="fas fa-save h-4 w-4" ></i> Simpan</button>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 border-b border-border bg-muted/60 px-5 py-3">
                    <span v-for="row in guruAssignments" :key="row.id_guru" class="inline-flex items-center gap-1.5 rounded-full bg-card px-3 py-1 text-xs text-muted-foreground ring-1 ring-border">
                        <span class="font-medium text-foreground">{{ row.nama_guru }}</span>
                        {{ row.jumlah }} kelas
                    </span>
                    <span v-if="!guruAssignments.length" class="text-xs text-muted-foreground">Belum ada penugasan.</span>
                </div>

                <div v-if="selectedGuru" class="overflow-x-auto">
                    <table class="data-table w-full min-w-[760px] divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">Mata Pelajaran</th>
                                <th v-for="kelas in classes" :key="kelas.id_kelas" class="px-3 py-3 text-center">{{ kelas.nama_kelas }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="mapel in mapels" :key="mapel.id_mapel" class="hover:bg-muted/40">
                                <td class="px-4 py-2.5">
                                    <p class="font-medium text-foreground">{{ mapel.nama_mapel }}</p>
                                    <p class="text-xs text-muted-foreground">Kelompok {{ mapel.kelompok ?? '-' }}</p>
                                </td>
                                <td v-for="kelas in classes" :key="kelas.id_kelas" class="px-3 py-2.5 text-center">
                                    <input
 type="checkbox"
 class="h-4 w-4 rounded border-border text-primary focus:ring-ring"
 :checked="isChecked(selectedGuru, mapel.id_mapel, kelas.id_kelas)"
 @change="toggle(selectedGuru, mapel.id_mapel, kelas.id_kelas)" :aria-label="`Pengampu ${mapel.nama_mapel} ${kelas.nama_kelas}`" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center gap-2 border-t border-border px-5 py-3 text-xs text-muted-foreground">
                    <i class="fas fa-info-circle h-3.5 w-3.5" ></i>
                    {{ guruMapelCount(selectedGuru) }} penugasan kelas dipilih untuk guru ini.
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
