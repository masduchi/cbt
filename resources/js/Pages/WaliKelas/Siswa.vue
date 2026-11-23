<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import { btnDanger, btnPrimary, btnSecondary, cardClass, formatTanggal, inputClass, labelClass } from '@/ui';
import { computed, ref } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    classes: { type: Array, default: () => [] },
    selected: { type: Object, default: null },
    students: { type: Array, default: () => [] },
    agamaOptions: { type: Array, default: () => [] },
});

const fields = [
    'nisn', 'nis', 'nama', 'jenis_kelamin', 'tahun_masuk', 'sekolah_asal',
    'tempat_lahir', 'tanggal_lahir', 'agama', 'status_keluarga', 'anak_ke',
    'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kabupaten', 'provinsi', 'kode_pos',
    'hp', 'email',
    'nama_ayah', 'tgl_lahir_ayah', 'pendidikan_ayah', 'pekerjaan_ayah', 'nohp_ayah', 'alamat_ayah',
    'nama_ibu', 'tgl_lahir_ibu', 'pendidikan_ibu', 'pekerjaan_ibu', 'nohp_ibu', 'alamat_ibu',
    'nama_wali', 'tgl_lahir_wali', 'pendidikan_wali', 'pekerjaan_wali', 'nohp_wali', 'alamat_wali',
];

const blank = Object.fromEntries(fields.map((field) => [field, '']));

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ id_kelas: props.selected?.id_kelas ?? '', ...blank });

const photoForm = useForm({ foto: null });
const photoPreview = ref(null);

const errorCount = computed(() => Object.keys(form.errors).length);

function changeClass(event) {
    router.get('/wali/siswa', { kelas: event.target.value }, { preserveScroll: true });
}

function openEdit(row) {
    editing.value = row;
    form.clearErrors();
    Object.assign(form, { id_kelas: props.selected?.id_kelas ?? '', ...blank });

    for (const field of fields) {
        form[field] = row[field] ?? '';
    }

    photoForm.reset();
    photoPreview.value = row.foto_url ?? null;
    showModal.value = true;
}

function submit() {
    form.post(`/wali/siswa/${editing.value.id_siswa}`, {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
        },
    });
}

function choosePhoto(event) {
    const file = event.target.files?.[0] ?? null;
    photoForm.foto = file;
    photoPreview.value = file ? URL.createObjectURL(file) : editing.value?.foto_url ?? null;
}

function uploadPhoto() {
    if (!photoForm.foto) return;

    photoForm.post(`/wali/siswa/${editing.value.id_siswa}/foto`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            editing.value = props.students.find((row) => row.id_siswa === editing.value.id_siswa) ?? editing.value;
            photoPreview.value = editing.value.foto_url ?? null;
            photoForm.reset();
        },
    });
}

async function deletePhoto() {
    if (!await askConfirm(`Hapus foto siswa ${editing.value?.nama ?? ''}?`)) return;

    router.delete(`/wali/siswa/${editing.value.id_siswa}/foto`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = props.students.find((row) => row.id_siswa === editing.value.id_siswa) ?? editing.value;
            photoPreview.value = null;
        },
    });
}
</script>

<template>
    <Head title="Data Siswa Wali Kelas" />
    <AdminLayout title="Data Siswa Wali Kelas">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Data Siswa Kelas" description="Perbarui identitas siswa dan foto untuk kelas yang Anda ampu sebagai wali kelas." /></div>

            <div v-if="!props.classes.length" class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm font-medium text-warning">
                Belum ada kelas yang Anda ampu sebagai wali kelas pada periode ini.
            </div>

            <template v-else>
                <div class="max-w-xs">
                    <label for="kelas" :class="labelClass">Kelas</label>
                    <select id="kelas" :value="props.selected?.id_kelas" :class="inputClass" @change="changeClass">
                        <option v-for="kelas in props.classes" :key="kelas.id_kelas" :value="kelas.id_kelas">
                            {{ kelas.nama_kelas }}
                        </option>
                    </select>
                    <p v-if="props.selected?.wali" class="mt-1 text-xs text-muted-foreground">Wali kelas: {{ props.selected.wali }}</p>
                </div>

                <div :class="cardClass" class="overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="data-table w-full min-w-[720px] divide-y divide-border text-sm">
                            <thead>
                                <tr>
                                    <th class="w-12 px-4 py-2">No</th>
                                    <th class="w-16 px-2 py-2">Foto</th>
                                    <th class="px-3 py-2">Nama</th>
                                    <th class="px-3 py-2">NIS / NISN</th>
                                    <th class="w-20 px-2 py-2 text-center">JK</th>
                                    <th class="px-3 py-2">Kontak</th>
                                    <th class="w-28 px-3 py-2 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="(row, index) in props.students" :key="row.id_siswa">
                                    <td class="px-4 py-2 text-muted-foreground">{{ index + 1 }}</td>
                                    <td class="px-2 py-2">
                                        <img v-if="row.foto_url" :src="row.foto_url" :alt="`Foto ${row.nama}`" class="h-9 w-9 rounded-full object-cover ring-1 ring-border" />
                                        <span v-else class="flex h-9 w-9 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                            <i class="fas fa-user h-4 w-4" ></i>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <p class="font-medium text-foreground">{{ row.nama }}</p>
                                        <p class="text-xs text-muted-foreground">{{ row.tempat_lahir || '-' }}{{ row.tanggal_lahir ? ', ' + formatTanggal(row.tanggal_lahir) : '' }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-muted-foreground">
                                        <span class="block">{{ row.nis || '-' }}</span>
                                        <span class="block text-xs text-muted-foreground">{{ row.nisn || '-' }}</span>
                                    </td>
                                    <td class="px-2 py-2 text-center text-muted-foreground">{{ row.jenis_kelamin || '-' }}</td>
                                    <td class="px-3 py-2 text-muted-foreground">
                                        <span class="block">{{ row.hp || '-' }}</span>
                                        <span class="block text-xs text-muted-foreground">{{ row.email || '-' }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button type="button" :class="btnSecondary" @click="openEdit(row)">
                                            <i class="fas fa-pencil-alt h-3.5 w-3.5" ></i> Edit
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!props.students.length">
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada siswa di kelas ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>

        <Modal :show="showModal" title="Edit Data Siswa" max-width="max-w-4xl" @close="showModal = false">
            <form class="space-y-5" @submit.prevent="submit">
                <div class="flex flex-wrap items-center gap-4 rounded-xl border border-border bg-muted/60 px-4 py-3">
                    <img v-if="photoPreview" :src="photoPreview" alt="Pratinjau foto siswa" class="h-16 w-16 rounded-full object-cover ring-1 ring-border" />
                    <span v-else class="flex h-16 w-16 items-center justify-center rounded-full bg-card text-muted-foreground ring-1 ring-border">
                        <i class="fas fa-user h-6 w-6" ></i>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-foreground">{{ editing?.nama }}</p>
                        <p class="text-xs text-muted-foreground">Foto JPG/PNG maksimal 2 MB.</p>
                        <p v-if="photoForm.errors.foto" class="mt-1 text-xs text-destructive">{{ photoForm.errors.foto }}</p>
                    </div>
                    <div class="ml-auto flex flex-wrap gap-2">
                        <label class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border bg-card px-3 py-2 text-xs font-medium text-foreground transition hover:bg-muted">
                            <i class="fas fa-camera h-3.5 w-3.5" ></i> Pilih foto
                            <input type="file" accept="image/*" class="sr-only" @change="choosePhoto" />
                        </label>
                        <button type="button" :class="btnSecondary" :disabled="!photoForm.foto || photoForm.processing" @click="uploadPhoto">
                            <i class="fas fa-camera h-3.5 w-3.5" ></i> Unggah
                        </button>
                        <button v-if="editing?.foto_url" type="button" :class="btnDanger" @click="deletePhoto">
                            <i class="fas fa-trash-alt h-3.5 w-3.5" ></i> Hapus foto
                        </button>
                    </div>
                </div>

                <div v-if="errorCount" role="alert" class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive">
                    Periksa kembali {{ errorCount }} isian yang belum sesuai.
                </div>

                <fieldset class="space-y-4">
                    <legend class="text-sm font-semibold text-foreground">Data pribadi</legend>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="lg:col-span-2">
                            <label :class="labelClass" for="nama-lengkap">Nama Lengkap *</label>
                            <input v-model="form.nama" :class="inputClass" id="nama-lengkap" />
                            <p v-if="form.errors.nama" class="mt-1 text-xs text-destructive">{{ form.errors.nama }}</p>
                        </div>
                        <div>
                            <label :class="labelClass" for="jenis-kelamin">Jenis Kelamin</label>
                            <select v-model="form.jenis_kelamin" :class="inputClass" id="jenis-kelamin">
                                <option value="">Pilih</option>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label :class="labelClass" for="nisn">NISN *</label>
                            <input v-model="form.nisn" :class="inputClass" inputmode="numeric" id="nisn" />
                            <p v-if="form.errors.nisn" class="mt-1 text-xs text-destructive">{{ form.errors.nisn }}</p>
                        </div>
                        <div>
                            <label :class="labelClass" for="nis">NIS *</label>
                            <input v-model="form.nis" :class="inputClass" id="nis" />
                            <p v-if="form.errors.nis" class="mt-1 text-xs text-destructive">{{ form.errors.nis }}</p>
                        </div>
                        <div>
                            <label :class="labelClass" for="agama">Agama</label>
                            <select v-model="form.agama" :class="inputClass" id="agama">
                                <option value="">Pilih</option>
                                <option v-for="agama in props.agamaOptions" :key="agama" :value="agama">{{ agama }}</option>
                            </select>
                        </div>
                        <div>
                            <label :class="labelClass">Tempat Lahir</label>
                            <input v-model="form.tempat_lahir" :class="inputClass" aria-label="Tempat lahir" />
                        </div>
                        <div>
                            <label :class="labelClass" for="tanggal-lahir">Tanggal Lahir</label>
                            <input v-model="form.tanggal_lahir" :class="inputClass" placeholder="YYYY-MM-DD" id="tanggal-lahir" />
                        </div>
                        <div>
                            <label :class="labelClass" for="status-dalam-keluarga">Status dalam Keluarga</label>
                            <select v-model="form.status_keluarga" :class="inputClass" id="status-dalam-keluarga">
                                <option value="">Pilih</option>
                                <option value="1">Anak kandung</option>
                                <option value="2">Anak angkat</option>
                                <option value="3">Anak tiri</option>
                            </select>
                        </div>
                        <div>
                            <label :class="labelClass" for="anak-ke">Anak Ke</label>
                            <input v-model.number="form.anak_ke" type="number" min="0" :class="inputClass" id="anak-ke" />
                        </div>
                        <div>
                            <label :class="labelClass" for="tahun-masuk">Tahun Masuk</label>
                            <input v-model="form.tahun_masuk" :class="inputClass" id="tahun-masuk" />
                        </div>
                        <div>
                            <label :class="labelClass" for="sekolah-asal">Sekolah Asal</label>
                            <input v-model="form.sekolah_asal" :class="inputClass" id="sekolah-asal" />
                        </div>
                        <div>
                            <label :class="labelClass" for="no-hp">No. HP</label>
                            <input v-model="form.hp" :class="inputClass" id="no-hp" />
                        </div>
                        <div>
                            <label :class="labelClass" for="email">Email</label>
                            <input v-model="form.email" type="email" :class="inputClass" id="email" />
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <label :class="labelClass" for="alamat">Alamat</label>
                            <textarea v-model="form.alamat" rows="2" :class="inputClass" id="alamat" />
                        </div>
                        <div>
                            <label :class="labelClass" for="rt">RT</label>
                            <input v-model="form.rt" :class="inputClass" id="rt" />
                        </div>
                        <div>
                            <label :class="labelClass" for="rw">RW</label>
                            <input v-model="form.rw" :class="inputClass" id="rw" />
                        </div>
                        <div>
                            <label :class="labelClass" for="kelurahan-desa">Kelurahan / Desa</label>
                            <input v-model="form.kelurahan" :class="inputClass" id="kelurahan-desa" />
                        </div>
                        <div>
                            <label :class="labelClass" for="kecamatan">Kecamatan</label>
                            <input v-model="form.kecamatan" :class="inputClass" id="kecamatan" />
                        </div>
                        <div>
                            <label :class="labelClass" for="kabupaten-kota">Kabupaten / Kota</label>
                            <input v-model="form.kabupaten" :class="inputClass" id="kabupaten-kota" />
                        </div>
                        <div>
                            <label :class="labelClass" for="provinsi">Provinsi</label>
                            <input v-model="form.provinsi" :class="inputClass" id="provinsi" />
                        </div>
                        <div>
                            <label :class="labelClass" for="kode-pos">Kode Pos</label>
                            <input v-model.number="form.kode_pos" type="number" min="0" :class="inputClass" id="kode-pos" />
                        </div>
                    </div>
                </fieldset>

                <fieldset class="space-y-4">
                    <legend class="text-sm font-semibold text-foreground">Data Ayah</legend>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label :class="labelClass" for="nama-ayah">Nama Ayah</label>
                            <input v-model="form.nama_ayah" :class="inputClass" id="nama-ayah" />
                        </div>
                        <div>
                            <label :class="labelClass" for="tanggal-lahir-2">Tanggal Lahir</label>
                            <input v-model="form.tgl_lahir_ayah" :class="inputClass" id="tanggal-lahir-2" />
                        </div>
                        <div>
                            <label :class="labelClass" for="pendidikan">Pendidikan</label>
                            <input v-model="form.pendidikan_ayah" :class="inputClass" id="pendidikan" />
                        </div>
                        <div>
                            <label :class="labelClass" for="pekerjaan">Pekerjaan</label>
                            <input v-model="form.pekerjaan_ayah" :class="inputClass" id="pekerjaan" />
                        </div>
                        <div>
                            <label :class="labelClass" for="no-hp-2">No. HP</label>
                            <input v-model="form.nohp_ayah" :class="inputClass" id="no-hp-2" />
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <label :class="labelClass" for="alamat-2">Alamat</label>
                            <textarea v-model="form.alamat_ayah" rows="2" :class="inputClass" id="alamat-2" />
                        </div>
                    </div>
                </fieldset>

                <fieldset class="space-y-4">
                    <legend class="text-sm font-semibold text-foreground">Data Ibu</legend>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label :class="labelClass" for="nama-ibu">Nama Ibu</label>
                            <input v-model="form.nama_ibu" :class="inputClass" id="nama-ibu" />
                        </div>
                        <div>
                            <label :class="labelClass" for="tanggal-lahir-3">Tanggal Lahir</label>
                            <input v-model="form.tgl_lahir_ibu" :class="inputClass" id="tanggal-lahir-3" />
                        </div>
                        <div>
                            <label :class="labelClass" for="pendidikan-2">Pendidikan</label>
                            <input v-model="form.pendidikan_ibu" :class="inputClass" id="pendidikan-2" />
                        </div>
                        <div>
                            <label :class="labelClass" for="pekerjaan-2">Pekerjaan</label>
                            <input v-model="form.pekerjaan_ibu" :class="inputClass" id="pekerjaan-2" />
                        </div>
                        <div>
                            <label :class="labelClass" for="no-hp-3">No. HP</label>
                            <input v-model="form.nohp_ibu" :class="inputClass" id="no-hp-3" />
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <label :class="labelClass" for="alamat-3">Alamat</label>
                            <textarea v-model="form.alamat_ibu" rows="2" :class="inputClass" id="alamat-3" />
                        </div>
                    </div>
                </fieldset>

                <fieldset class="space-y-4">
                    <legend class="text-sm font-semibold text-foreground">Data Wali</legend>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label :class="labelClass" for="nama-wali">Nama Wali</label>
                            <input v-model="form.nama_wali" :class="inputClass" id="nama-wali" />
                        </div>
                        <div>
                            <label :class="labelClass" for="tanggal-lahir-4">Tanggal Lahir</label>
                            <input v-model="form.tgl_lahir_wali" :class="inputClass" id="tanggal-lahir-4" />
                        </div>
                        <div>
                            <label :class="labelClass" for="pendidikan-3">Pendidikan</label>
                            <input v-model="form.pendidikan_wali" :class="inputClass" id="pendidikan-3" />
                        </div>
                        <div>
                            <label :class="labelClass" for="pekerjaan-3">Pekerjaan</label>
                            <input v-model="form.pekerjaan_wali" :class="inputClass" id="pekerjaan-3" />
                        </div>
                        <div>
                            <label :class="labelClass" for="no-hp-4">No. HP</label>
                            <input v-model="form.nohp_wali" :class="inputClass" id="no-hp-4" />
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <label :class="labelClass" for="alamat-4">Alamat</label>
                            <textarea v-model="form.alamat_wali" rows="2" :class="inputClass" id="alamat-4" />
                        </div>
                    </div>
                </fieldset>
            </form>

            <template #footer>
                <button type="button" :class="btnSecondary" @click="showModal = false">Batal</button>
                <button type="button" :class="btnPrimary" :disabled="form.processing" @click="submit">
                    <i class="fas fa-save h-4 w-4" ></i> {{ form.processing ? 'Menyimpan…' : 'Simpan Data' }}
                </button>
            </template>
        </Modal>
    </AdminLayout>
</template>
