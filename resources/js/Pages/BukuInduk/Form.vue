<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ExportLink from '@/Components/ExportLink.vue';
import { btnPrimary, btnSecondary, inputClass, labelClass } from '@/ui';
import { computed } from 'vue';

const props = defineProps({
    siswa: { type: Object, required: true },
    buku: { type: Object, required: true },
    statusOptions: { type: Array, default: () => [] },
});

const value = (input) => input ?? '';

const form = useForm({
    rombel_awal: value(props.buku.rombel_awal),
    nama_panggilan: value(props.buku.nama_panggilan),
    bahasa: value(props.buku.bahasa),
    jml_saudara_kandung: value(props.buku.jml_saudara_kandung ?? 0),
    jml_saudara_tiri: value(props.buku.jml_saudara_tiri ?? 0),
    jml_saudara_angkat: value(props.buku.jml_saudara_angkat ?? 0),
    yatim: String(props.buku.yatim ?? 0),
    tinggal_bersama: String(props.buku.tinggal_bersama ?? '1'),
    jarak: value(props.buku.jarak),
    gol_darah: value(props.buku.gol_darah),
    penyakit: value(props.buku.penyakit),
    kelainan_fisik: value(props.buku.kelainan_fisik),
    kegemaran: value(props.buku.kegemaran),
    beasiswa: value(props.buku.beasiswa),
    no_ijazah_sebelumnya: value(props.buku.no_ijazah_sebelumnya),
    tahun_lulus_sebelumnya: value(props.buku.tahun_lulus_sebelumnya),
    pindahan_dari: value(props.buku.pindahan_dari),
    alasan_kepindahan: value(props.buku.alasan_kepindahan),
    agama_ayah: value(props.buku.agama_ayah),
    tempat_lahir_ayah: value(props.buku.tempat_lahir_ayah),
    wn_ayah: value(props.buku.wn_ayah),
    penghasilan_ayah: value(props.buku.penghasilan_ayah),
    hidup_meninggal_ayah: value(props.buku.hidup_meninggal_ayah),
    agama_ibu: value(props.buku.agama_ibu),
    tempat_lahir_ibu: value(props.buku.tempat_lahir_ibu),
    wn_ibu: value(props.buku.wn_ibu),
    penghasilan_ibu: value(props.buku.penghasilan_ibu),
    hidup_meninggal_ibu: value(props.buku.hidup_meninggal_ibu),
    tempat_lahir_wali: value(props.buku.tempat_lahir_wali),
    agama_wali: value(props.buku.agama_wali),
    wn_wali: value(props.buku.wn_wali),
    penghasilan_wali: value(props.buku.penghasilan_wali),
    status: String(props.buku.status ?? 1),
    tahun_lulus: value(props.buku.tahun_lulus),
    no_ijazah: value(props.buku.no_ijazah),
    kelas_akhir: value(props.buku.kelas_akhir ?? props.siswa.kelas),
    lanjut_ke: value(props.buku.lanjut_ke),
    pindah_ke: value(props.buku.pindah_ke),
    alasan_pindah: value(props.buku.alasan_pindah),
    tgl_pindah: value(props.buku.tgl_pindah),
    bekerja_di: value(props.buku.bekerja_di),
    catatan_penting: value(props.buku.catatan_penting),
});

const errorCount = computed(() => Object.keys(form.errors).length);

const tinggalOptions = [
    { value: '1', label: 'Orang tua' },
    { value: '2', label: 'Saudara' },
    { value: '3', label: 'Wali' },
    { value: '4', label: 'Asrama/pesantren' },
    { value: '5', label: 'Kost' },
    { value: '6', label: 'Lainnya' },
];

function submit() {
    form.post(`/master/buku-induk/${props.siswa.id_siswa}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Buku Induk ${props.siswa.nama}`" />
    <AdminLayout title="Buku Induk">
        <div class="page-stack space-y-5">
            <div class="sticky top-[70px] z-20 -mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:top-[60px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader :title="props.siswa.nama" :back="{ href: '/master/buku-induk', label: 'Kembali ke daftar' }">
                    <template #description>
                        NIS {{ props.siswa.nis || '-' }} · NISN {{ props.siswa.nisn || '-' }} · {{ props.siswa.kelas || 'Belum ada kelas' }}
                    </template>
                </PageHeader>
                <div class="flex items-center gap-2">
                    <ExportLink
                        :href="`/master/buku-induk/${props.siswa.id_siswa}/pdf`"
                        label="Unduh PDF"
                        icon="file-download"
                    />
                    <button type="submit" form="buku-induk-form" :class="btnPrimary" :disabled="form.processing">
                        <i class="fas fa-save h-4 w-4" ></i> {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                    </button>
                </div>
            </div>

            <div
                v-if="errorCount"
                role="alert"
                class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
            >
                Periksa kembali {{ errorCount }} isian yang belum sesuai.
            </div>

            <form id="buku-induk-form" class="space-y-5" @submit.prevent="submit">
                <section class="rounded-xl border border-border bg-card p-5 shadow-card">
                    <h2 class="text-sm font-semibold text-foreground">Keterangan siswa</h2>
                    <p class="text-xs text-muted-foreground">Data pelengkap di luar identitas utama siswa.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label for="nama_panggilan" :class="labelClass">Nama panggilan</label>
                            <input id="nama_panggilan" v-model="form.nama_panggilan" :class="inputClass" />
                            <p v-if="form.errors.nama_panggilan" class="mt-1 text-xs text-destructive">{{ form.errors.nama_panggilan }}</p>
                        </div>
                        <div>
                            <label for="rombel_awal" :class="labelClass">Rombel awal</label>
                            <input id="rombel_awal" v-model="form.rombel_awal" :class="inputClass" />
                            <p v-if="form.errors.rombel_awal" class="mt-1 text-xs text-destructive">{{ form.errors.rombel_awal }}</p>
                        </div>
                        <div>
                            <label for="bahasa" :class="labelClass">Bahasa sehari-hari</label>
                            <input id="bahasa" v-model="form.bahasa" :class="inputClass" />
                            <p v-if="form.errors.bahasa" class="mt-1 text-xs text-destructive">{{ form.errors.bahasa }}</p>
                        </div>
                        <div>
                            <label for="jml_saudara_kandung" :class="labelClass">Saudara kandung</label>
                            <input id="jml_saudara_kandung" v-model="form.jml_saudara_kandung" type="number" min="0" max="20" :class="inputClass" />
                            <p v-if="form.errors.jml_saudara_kandung" class="mt-1 text-xs text-destructive">{{ form.errors.jml_saudara_kandung }}</p>
                        </div>
                        <div>
                            <label for="jml_saudara_tiri" :class="labelClass">Saudara tiri</label>
                            <input id="jml_saudara_tiri" v-model="form.jml_saudara_tiri" type="number" min="0" max="20" :class="inputClass" />
                            <p v-if="form.errors.jml_saudara_tiri" class="mt-1 text-xs text-destructive">{{ form.errors.jml_saudara_tiri }}</p>
                        </div>
                        <div>
                            <label for="jml_saudara_angkat" :class="labelClass">Saudara angkat</label>
                            <input id="jml_saudara_angkat" v-model="form.jml_saudara_angkat" type="number" min="0" max="20" :class="inputClass" />
                            <p v-if="form.errors.jml_saudara_angkat" class="mt-1 text-xs text-destructive">{{ form.errors.jml_saudara_angkat }}</p>
                        </div>
                        <div>
                            <label for="yatim" :class="labelClass">Status orang tua</label>
                            <select id="yatim" v-model="form.yatim" :class="inputClass">
                                <option value="0">Ada orang tua</option>
                                <option value="1">Yatim</option>
                                <option value="2">Yatim piatu</option>
                            </select>
                            <p v-if="form.errors.yatim" class="mt-1 text-xs text-destructive">{{ form.errors.yatim }}</p>
                        </div>
                        <div>
                            <label for="tinggal_bersama" :class="labelClass">Tinggal bersama</label>
                            <select id="tinggal_bersama" v-model="form.tinggal_bersama" :class="inputClass">
                                <option v-for="option in tinggalOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <p v-if="form.errors.tinggal_bersama" class="mt-1 text-xs text-destructive">{{ form.errors.tinggal_bersama }}</p>
                        </div>
                        <div>
                            <label for="jarak" :class="labelClass">Jarak ke sekolah (km)</label>
                            <input id="jarak" v-model="form.jarak" :class="inputClass" />
                            <p v-if="form.errors.jarak" class="mt-1 text-xs text-destructive">{{ form.errors.jarak }}</p>
                        </div>
                        <div>
                            <label for="gol_darah" :class="labelClass">Golongan darah</label>
                            <input id="gol_darah" v-model="form.gol_darah" :class="inputClass" />
                            <p v-if="form.errors.gol_darah" class="mt-1 text-xs text-destructive">{{ form.errors.gol_darah }}</p>
                        </div>
                        <div>
                            <label for="kelainan_fisik" :class="labelClass">Kelainan fisik</label>
                            <input id="kelainan_fisik" v-model="form.kelainan_fisik" :class="inputClass" />
                            <p v-if="form.errors.kelainan_fisik" class="mt-1 text-xs text-destructive">{{ form.errors.kelainan_fisik }}</p>
                        </div>
                        <div>
                            <label for="beasiswa" :class="labelClass">Beasiswa</label>
                            <input id="beasiswa" v-model="form.beasiswa" :class="inputClass" />
                            <p v-if="form.errors.beasiswa" class="mt-1 text-xs text-destructive">{{ form.errors.beasiswa }}</p>
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <label for="penyakit" :class="labelClass">Riwayat penyakit</label>
                            <textarea id="penyakit" v-model="form.penyakit" rows="2" :class="inputClass" />
                            <p v-if="form.errors.penyakit" class="mt-1 text-xs text-destructive">{{ form.errors.penyakit }}</p>
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <label for="kegemaran" :class="labelClass">Kegemaran</label>
                            <textarea id="kegemaran" v-model="form.kegemaran" rows="2" :class="inputClass" />
                            <p v-if="form.errors.kegemaran" class="mt-1 text-xs text-destructive">{{ form.errors.kegemaran }}</p>
                        </div>
                        <div>
                            <label for="no_ijazah_sebelumnya" :class="labelClass">Nomor ijazah sebelumnya</label>
                            <input id="no_ijazah_sebelumnya" v-model="form.no_ijazah_sebelumnya" :class="inputClass" />
                            <p v-if="form.errors.no_ijazah_sebelumnya" class="mt-1 text-xs text-destructive">{{ form.errors.no_ijazah_sebelumnya }}</p>
                        </div>
                        <div>
                            <label for="tahun_lulus_sebelumnya" :class="labelClass">Tahun lulus sebelumnya</label>
                            <input id="tahun_lulus_sebelumnya" v-model="form.tahun_lulus_sebelumnya" :class="inputClass" />
                            <p v-if="form.errors.tahun_lulus_sebelumnya" class="mt-1 text-xs text-destructive">{{ form.errors.tahun_lulus_sebelumnya }}</p>
                        </div>
                        <div>
                            <label for="pindahan_dari" :class="labelClass">Pindahan dari</label>
                            <input id="pindahan_dari" v-model="form.pindahan_dari" :class="inputClass" />
                            <p v-if="form.errors.pindahan_dari" class="mt-1 text-xs text-destructive">{{ form.errors.pindahan_dari }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="alasan_kepindahan" :class="labelClass">Alasan kepindahan</label>
                            <input id="alasan_kepindahan" v-model="form.alasan_kepindahan" :class="inputClass" />
                            <p v-if="form.errors.alasan_kepindahan" class="mt-1 text-xs text-destructive">{{ form.errors.alasan_kepindahan }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-border bg-card p-5 shadow-card">
                    <h2 class="text-sm font-semibold text-foreground">Data orang tua dan wali</h2>
                    <p class="text-xs text-muted-foreground">
                        Nama dan pekerjaan diambil dari data siswa: {{ props.siswa.nama_ayah || '-' }} / {{ props.siswa.nama_ibu || '-' }} / {{ props.siswa.nama_wali || '-' }}.
                    </p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label for="agama_ayah" :class="labelClass">Agama ayah</label>
                            <input id="agama_ayah" v-model="form.agama_ayah" :class="inputClass" />
                            <p v-if="form.errors.agama_ayah" class="mt-1 text-xs text-destructive">{{ form.errors.agama_ayah }}</p>
                        </div>
                        <div>
                            <label for="tempat_lahir_ayah" :class="labelClass">Tempat lahir ayah</label>
                            <input id="tempat_lahir_ayah" v-model="form.tempat_lahir_ayah" :class="inputClass" />
                            <p v-if="form.errors.tempat_lahir_ayah" class="mt-1 text-xs text-destructive">{{ form.errors.tempat_lahir_ayah }}</p>
                        </div>
                        <div>
                            <label for="wn_ayah" :class="labelClass">Kewarganegaraan ayah</label>
                            <input id="wn_ayah" v-model="form.wn_ayah" :class="inputClass" />
                            <p v-if="form.errors.wn_ayah" class="mt-1 text-xs text-destructive">{{ form.errors.wn_ayah }}</p>
                        </div>
                        <div>
                            <label for="penghasilan_ayah" :class="labelClass">Penghasilan ayah</label>
                            <input id="penghasilan_ayah" v-model="form.penghasilan_ayah" :class="inputClass" />
                            <p v-if="form.errors.penghasilan_ayah" class="mt-1 text-xs text-destructive">{{ form.errors.penghasilan_ayah }}</p>
                        </div>
                        <div>
                            <label for="hidup_meninggal_ayah" :class="labelClass">Status ayah</label>
                            <input id="hidup_meninggal_ayah" v-model="form.hidup_meninggal_ayah" :class="inputClass" placeholder="Hidup / meninggal" />
                            <p v-if="form.errors.hidup_meninggal_ayah" class="mt-1 text-xs text-destructive">{{ form.errors.hidup_meninggal_ayah }}</p>
                        </div>
                        <div>
                            <label for="agama_ibu" :class="labelClass">Agama ibu</label>
                            <input id="agama_ibu" v-model="form.agama_ibu" :class="inputClass" />
                            <p v-if="form.errors.agama_ibu" class="mt-1 text-xs text-destructive">{{ form.errors.agama_ibu }}</p>
                        </div>
                        <div>
                            <label for="tempat_lahir_ibu" :class="labelClass">Tempat lahir ibu</label>
                            <input id="tempat_lahir_ibu" v-model="form.tempat_lahir_ibu" :class="inputClass" />
                            <p v-if="form.errors.tempat_lahir_ibu" class="mt-1 text-xs text-destructive">{{ form.errors.tempat_lahir_ibu }}</p>
                        </div>
                        <div>
                            <label for="wn_ibu" :class="labelClass">Kewarganegaraan ibu</label>
                            <input id="wn_ibu" v-model="form.wn_ibu" :class="inputClass" />
                            <p v-if="form.errors.wn_ibu" class="mt-1 text-xs text-destructive">{{ form.errors.wn_ibu }}</p>
                        </div>
                        <div>
                            <label for="penghasilan_ibu" :class="labelClass">Penghasilan ibu</label>
                            <input id="penghasilan_ibu" v-model="form.penghasilan_ibu" :class="inputClass" />
                            <p v-if="form.errors.penghasilan_ibu" class="mt-1 text-xs text-destructive">{{ form.errors.penghasilan_ibu }}</p>
                        </div>
                        <div>
                            <label for="hidup_meninggal_ibu" :class="labelClass">Status ibu</label>
                            <input id="hidup_meninggal_ibu" v-model="form.hidup_meninggal_ibu" :class="inputClass" placeholder="Hidup / meninggal" />
                            <p v-if="form.errors.hidup_meninggal_ibu" class="mt-1 text-xs text-destructive">{{ form.errors.hidup_meninggal_ibu }}</p>
                        </div>
                        <div>
                            <label for="tempat_lahir_wali" :class="labelClass">Tempat lahir wali</label>
                            <input id="tempat_lahir_wali" v-model="form.tempat_lahir_wali" :class="inputClass" />
                            <p v-if="form.errors.tempat_lahir_wali" class="mt-1 text-xs text-destructive">{{ form.errors.tempat_lahir_wali }}</p>
                        </div>
                        <div>
                            <label for="agama_wali" :class="labelClass">Agama wali</label>
                            <input id="agama_wali" v-model="form.agama_wali" :class="inputClass" />
                            <p v-if="form.errors.agama_wali" class="mt-1 text-xs text-destructive">{{ form.errors.agama_wali }}</p>
                        </div>
                        <div>
                            <label for="wn_wali" :class="labelClass">Kewarganegaraan wali</label>
                            <input id="wn_wali" v-model="form.wn_wali" :class="inputClass" />
                            <p v-if="form.errors.wn_wali" class="mt-1 text-xs text-destructive">{{ form.errors.wn_wali }}</p>
                        </div>
                        <div>
                            <label for="penghasilan_wali" :class="labelClass">Penghasilan wali</label>
                            <input id="penghasilan_wali" v-model="form.penghasilan_wali" :class="inputClass" />
                            <p v-if="form.errors.penghasilan_wali" class="mt-1 text-xs text-destructive">{{ form.errors.penghasilan_wali }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-border bg-card p-5 shadow-card">
                    <h2 class="text-sm font-semibold text-foreground">Kelulusan dan mutasi</h2>
                    <p class="text-xs text-muted-foreground">Status selain aktif akan menampilkan siswa di halaman alumni.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label for="status" :class="labelClass">Status siswa</label>
                            <select id="status" v-model="form.status" :class="inputClass" required>
                                <option v-for="option in props.statusOptions" :key="option.value" :value="String(option.value)">
                                    {{ option.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.status" class="mt-1 text-xs text-destructive">{{ form.errors.status }}</p>
                        </div>
                        <div>
                            <label for="tahun_lulus" :class="labelClass">Tahun lulus</label>
                            <input id="tahun_lulus" v-model="form.tahun_lulus" type="number" min="1900" max="2200" :class="inputClass" />
                            <p v-if="form.errors.tahun_lulus" class="mt-1 text-xs text-destructive">{{ form.errors.tahun_lulus }}</p>
                        </div>
                        <div>
                            <label for="no_ijazah" :class="labelClass">Nomor ijazah</label>
                            <input id="no_ijazah" v-model="form.no_ijazah" :class="inputClass" />
                            <p v-if="form.errors.no_ijazah" class="mt-1 text-xs text-destructive">{{ form.errors.no_ijazah }}</p>
                        </div>
                        <div>
                            <label for="kelas_akhir" :class="labelClass">Kelas akhir</label>
                            <input id="kelas_akhir" v-model="form.kelas_akhir" :class="inputClass" />
                            <p v-if="form.errors.kelas_akhir" class="mt-1 text-xs text-destructive">{{ form.errors.kelas_akhir }}</p>
                        </div>
                        <div>
                            <label for="lanjut_ke" :class="labelClass">Melanjutkan ke</label>
                            <input id="lanjut_ke" v-model="form.lanjut_ke" :class="inputClass" />
                            <p v-if="form.errors.lanjut_ke" class="mt-1 text-xs text-destructive">{{ form.errors.lanjut_ke }}</p>
                        </div>
                        <div>
                            <label for="bekerja_di" :class="labelClass">Bekerja di</label>
                            <input id="bekerja_di" v-model="form.bekerja_di" :class="inputClass" />
                            <p v-if="form.errors.bekerja_di" class="mt-1 text-xs text-destructive">{{ form.errors.bekerja_di }}</p>
                        </div>
                        <div>
                            <label for="pindah_ke" :class="labelClass">Pindah ke</label>
                            <input id="pindah_ke" v-model="form.pindah_ke" :class="inputClass" />
                            <p v-if="form.errors.pindah_ke" class="mt-1 text-xs text-destructive">{{ form.errors.pindah_ke }}</p>
                        </div>
                        <div>
                            <label for="alasan_pindah" :class="labelClass">Alasan pindah</label>
                            <input id="alasan_pindah" v-model="form.alasan_pindah" :class="inputClass" />
                            <p v-if="form.errors.alasan_pindah" class="mt-1 text-xs text-destructive">{{ form.errors.alasan_pindah }}</p>
                        </div>
                        <div>
                            <label for="tgl_pindah" :class="labelClass">Tanggal pindah</label>
                            <input id="tgl_pindah" v-model="form.tgl_pindah" :class="inputClass" placeholder="YYYY-MM-DD" />
                            <p v-if="form.errors.tgl_pindah" class="mt-1 text-xs text-destructive">{{ form.errors.tgl_pindah }}</p>
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <label for="catatan_penting" :class="labelClass">Catatan penting</label>
                            <textarea id="catatan_penting" v-model="form.catatan_penting" rows="3" :class="inputClass" />
                            <p v-if="form.errors.catatan_penting" class="mt-1 text-xs text-destructive">{{ form.errors.catatan_penting }}</p>
                        </div>
                    </div>
                </section>

                <div class="flex items-center justify-end gap-2">
                    <Link href="/master/buku-induk" :class="btnSecondary">Batal</Link>
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">
                        <i class="fas fa-save h-4 w-4" ></i> {{ form.processing ? 'Menyimpan…' : 'Simpan buku induk' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
