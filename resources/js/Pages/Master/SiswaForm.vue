<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, btnSecondary, inputClass, labelClass } from '@/ui';
import { ref } from 'vue';

const props = defineProps({
    siswa: { type: Object, default: null },
    levels: { type: Array, default: () => [] },
});

const isEdit = !!props.siswa;

const form = useForm({
    nisn: props.siswa?.nisn ?? '',
    nis: props.siswa?.nis ?? '',
    nama: props.siswa?.nama ?? '',
    jenis_kelamin: props.siswa?.jenis_kelamin ?? '',
    username: props.siswa?.username ?? '',
    password: '',
    kelas_awal: props.siswa?.kelas_awal ?? props.levels[props.levels.length - 1]?.id_level ?? 10,
    tahun_masuk: props.siswa?.tahun_masuk ?? '',
    sekolah_asal: props.siswa?.sekolah_asal ?? '',
    tempat_lahir: props.siswa?.tempat_lahir ?? '',
    tanggal_lahir: props.siswa?.tanggal_lahir ?? '',
    agama: props.siswa?.agama ?? '',
    hp: props.siswa?.hp ?? '',
    email: props.siswa?.email ?? '',
    nik: props.siswa?.nik ?? '',
    warga_negara: props.siswa?.warga_negara ?? 'WNI',
    anak_ke: props.siswa?.anak_ke ?? '',
    status_keluarga: props.siswa?.status_keluarga ?? '',
    alamat: props.siswa?.alamat ?? '',
    rt: props.siswa?.rt ?? '',
    rw: props.siswa?.rw ?? '',
    kelurahan: props.siswa?.kelurahan ?? '',
    kecamatan: props.siswa?.kecamatan ?? '',
    kabupaten: props.siswa?.kabupaten ?? '',
    provinsi: props.siswa?.provinsi ?? '',
    kode_pos: props.siswa?.kode_pos ?? '',
    nama_ayah: props.siswa?.nama_ayah ?? '',
    pendidikan_ayah: props.siswa?.pendidikan_ayah ?? '',
    pekerjaan_ayah: props.siswa?.pekerjaan_ayah ?? '',
    nohp_ayah: props.siswa?.nohp_ayah ?? '',
    nama_ibu: props.siswa?.nama_ibu ?? '',
    pendidikan_ibu: props.siswa?.pendidikan_ibu ?? '',
    pekerjaan_ibu: props.siswa?.pekerjaan_ibu ?? '',
    nohp_ibu: props.siswa?.nohp_ibu ?? '',
    nama_wali: props.siswa?.nama_wali ?? '',
    pekerjaan_wali: props.siswa?.pekerjaan_wali ?? '',
    nohp_wali: props.siswa?.nohp_wali ?? '',
    foto: null,
});

const photoPreview = ref(props.siswa?.foto_url ?? null);

function onPhoto(event) {
    const file = event.target.files[0];
    form.foto = file ?? null;
    photoPreview.value = file ? URL.createObjectURL(file) : props.siswa?.foto_url ?? null;
}

function submit() {
    const options = { forceFormData: true, preserveScroll: true };
    if (isEdit) {
        form.post(`/master/siswa/${props.siswa.id_siswa}`, options);
    } else {
        form.post('/master/siswa', options);
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Siswa' : 'Tambah Siswa'" />
    <AdminLayout :title="isEdit ? 'Edit Siswa' : 'Tambah Siswa'">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <Link href="/master/siswa" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-border bg-card p-2 text-muted-foreground transition hover:bg-muted/40 sm:h-9 sm:w-9">
                    <i class="fas fa-arrow-left h-4 w-4" ></i>
                </Link>
                <PageHeader :title="isEdit ? 'Edit Data Siswa' : 'Tambah Siswa'">
                    <template #description>Lengkapi identitas siswa. Password kosong berarti menggunakan NISN.</template>
                
                </PageHeader>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <section class="rounded-xl border border-border bg-card p-5 shadow-card">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">Identitas & Akun</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
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
                            <input v-model="form.nisn" :class="inputClass" id="nisn" />
                            <p v-if="form.errors.nisn" class="mt-1 text-xs text-destructive">{{ form.errors.nisn }}</p>
                        </div>
                        <div>
                            <label :class="labelClass" for="nis">NIS *</label>
                            <input v-model="form.nis" :class="inputClass" id="nis" />
                            <p v-if="form.errors.nis" class="mt-1 text-xs text-destructive">{{ form.errors.nis }}</p>
                        </div>
                        <div>
                            <label :class="labelClass" for="username">Username *</label>
                            <input v-model="form.username" :class="inputClass" autocomplete="off" id="username" />
                            <p v-if="form.errors.username" class="mt-1 text-xs text-destructive">{{ form.errors.username }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Password {{ isEdit ? '(opsional)' : '' }}</label>
                            <input v-model="form.password" type="password" :class="inputClass" autocomplete="new-password" aria-label="Password baru" />
                            <p v-if="form.errors.password" class="mt-1 text-xs text-destructive">{{ form.errors.password }}</p>
                        </div>
                        <div>
                            <label :class="labelClass" for="kelas-awal">Kelas Awal *</label>
                            <select v-model.number="form.kelas_awal" :class="inputClass" id="kelas-awal">
                                <option v-for="level in props.levels" :key="level.id_level" :value="level.id_level">
                                    Kelas {{ level.level }}
                                </option>
                            </select>
                            <p v-if="form.errors.kelas_awal" class="mt-1 text-xs text-destructive">{{ form.errors.kelas_awal }}</p>
                        </div>
                        <div>
                            <label :class="labelClass" for="tahun-masuk">Tahun Masuk</label>
                            <input v-model="form.tahun_masuk" :class="inputClass" placeholder="Contoh: 2026/2027" id="tahun-masuk" />
                        </div>
                        <div>
                            <label :class="labelClass" for="nik">NIK</label>
                            <input v-model="form.nik" :class="inputClass" id="nik" />
                        </div>
                        <div>
                            <label :class="labelClass" for="warga-negara">Warga Negara</label>
                            <input v-model="form.warga_negara" :class="inputClass" id="warga-negara" />
                        </div>
                        <div>
                            <label :class="labelClass" for="sekolah-asal">Sekolah Asal</label>
                            <input v-model="form.sekolah_asal" :class="inputClass" id="sekolah-asal" />
                        </div>
                        <div>
                            <label :class="labelClass" for="agama">Agama</label>
                            <input v-model="form.agama" :class="inputClass" id="agama" />
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-border bg-card p-5 shadow-card">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">Data Pribadi</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label :class="labelClass" for="tempat-lahir">Tempat Lahir</label>
                            <input v-model="form.tempat_lahir" :class="inputClass" id="tempat-lahir" />
                        </div>
                        <div>
                            <label :class="labelClass" for="tanggal-lahir">Tanggal Lahir</label>
                            <input v-model="form.tanggal_lahir" type="date" :class="inputClass" id="tanggal-lahir" />
                        </div>
                        <div>
                            <label :class="labelClass" for="no-hp">No. HP</label>
                            <input v-model="form.hp" :class="inputClass" id="no-hp" />
                        </div>
                        <div>
                            <label :class="labelClass" for="email">Email</label>
                            <input v-model="form.email" type="email" :class="inputClass" id="email" />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-destructive">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label :class="labelClass" for="anak-ke">Anak Ke</label>
                            <input v-model.number="form.anak_ke" type="number" min="1" :class="inputClass" id="anak-ke" />
                        </div>
                        <div>
                            <label :class="labelClass" for="status-keluarga">Status Keluarga</label>
                            <select v-model="form.status_keluarga" :class="inputClass" id="status-keluarga">
                                <option value="">Pilih</option>
                                <option value="A">Anak</option>
                                <option value="Y">Yatim</option>
                                <option value="P">Piatu</option>
                                <option value="T">Yatim Piatu</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-border bg-card p-5 shadow-card">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">Alamat</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label :class="labelClass" for="alamat-jalan">Alamat Jalan</label>
                            <textarea v-model="form.alamat" rows="2" :class="inputClass" id="alamat-jalan" />
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
                            <input v-model.number="form.kode_pos" type="number" :class="inputClass" id="kode-pos" />
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-border bg-card p-5 shadow-card">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">Orang Tua / Wali</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label :class="labelClass" for="nama-ayah">Nama Ayah</label>
                            <input v-model="form.nama_ayah" :class="inputClass" id="nama-ayah" />
                        </div>
                        <div>
                            <label :class="labelClass" for="no-hp-ayah">No. HP Ayah</label>
                            <input v-model="form.nohp_ayah" :class="inputClass" id="no-hp-ayah" />
                        </div>
                        <div>
                            <label :class="labelClass" for="nama-ibu">Nama Ibu</label>
                            <input v-model="form.nama_ibu" :class="inputClass" id="nama-ibu" />
                        </div>
                        <div>
                            <label :class="labelClass" for="no-hp-ibu">No. HP Ibu</label>
                            <input v-model="form.nohp_ibu" :class="inputClass" id="no-hp-ibu" />
                        </div>
                        <div>
                            <label :class="labelClass" for="nama-wali">Nama Wali</label>
                            <input v-model="form.nama_wali" :class="inputClass" id="nama-wali" />
                        </div>
                        <div>
                            <label :class="labelClass" for="no-hp-wali">No. HP Wali</label>
                            <input v-model="form.nohp_wali" :class="inputClass" id="no-hp-wali" />
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-border bg-card p-5 shadow-card">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">Foto Siswa</h2>
                    <div class="flex items-center gap-4">
                        <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-xl bg-muted text-muted-foreground">
                            <img v-if="photoPreview" :src="photoPreview" alt="Foto siswa" class="h-full w-full object-cover" />
                            <i v-else class="fas fa-image h-6 w-6" ></i>
                        </div>
                        <input type="file" accept="image/*" class="block text-sm text-muted-foreground file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary hover:file:bg-primary/20" @input="onPhoto" aria-label="Foto siswa" />
                    </div>
                    <p v-if="form.errors.foto" class="mt-1 text-xs text-destructive">{{ form.errors.foto }}</p>
                </section>

                <div class="sticky bottom-0 z-10 -mx-4 -mb-5 flex items-center justify-end gap-2 border-t border-border bg-card px-4 py-3 sm:-mx-6 sm:px-6 lg:-mx-[30px] lg:px-[30px]">
                    <Link href="/master/siswa" :class="btnSecondary">Batal</Link>
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">
                        <i class="fas fa-save h-4 w-4" ></i> {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
