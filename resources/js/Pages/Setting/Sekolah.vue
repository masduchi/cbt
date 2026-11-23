<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, cardClass, inputClass, labelClass } from '@/ui';

const props = defineProps({
    setting: { type: Object, required: true },
    logoKanan: { type: String, default: null },
    logoKiri: { type: String, default: null },
});

const form = useForm({
    sekolah: props.setting.sekolah ?? '',
    nama_aplikasi: props.setting.nama_aplikasi ?? '',
    npsn: props.setting.npsn ?? '',
    nss: props.setting.nss ?? '',
    jenjang: props.setting.jenjang ?? '',
    kepsek: props.setting.kepsek ?? '',
    nip: props.setting.nip ?? '',
    alamat: props.setting.alamat ?? '',
    desa: props.setting.desa ?? '',
    kecamatan: props.setting.kecamatan ?? '',
    kota: props.setting.kota ?? '',
    provinsi: props.setting.provinsi ?? '',
    kode_pos: props.setting.kode_pos ?? '',
    telp: props.setting.telp ?? '',
    fax: props.setting.fax ?? '',
    web: props.setting.web ?? '',
    email: props.setting.email ?? '',
    kode_sekolah: props.setting.kode_sekolah ?? '',
    logo_kanan: null,
    logo_kiri: null,
});

function submit() {
    form.post('/setting/sekolah', { forceFormData: true, preserveScroll: true });
}

function pickLogo(event, key) {
    form[key] = event.target.files[0] ?? null;
}
</script>

<template>
    <Head title="Profil Sekolah" />
    <AdminLayout title="Profil Sekolah">
        <form class="space-y-5" @submit.prevent="submit">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Profil Sekolah" description="Identitas sekolah yang tampil pada aplikasi dan dokumen cetak." />
                <button type="submit" :class="btnPrimary" :disabled="form.processing">
                    <i class="fas fa-save h-4 w-4" ></i> {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                </button>
            </div>

            <div :class="cardClass" class="p-5">
                <div class="mb-4 flex items-center gap-2 text-sm font-semibold text-foreground">
                    <i class="fas fa-building h-4 w-4 text-primary" ></i> Identitas
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="nama-sekolah">Nama Sekolah</label>
                        <input v-model="form.sekolah" type="text" :class="inputClass" required id="nama-sekolah" />
                        <p v-if="form.errors.sekolah" class="mt-1 text-xs text-destructive">{{ form.errors.sekolah }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="npsn">NPSN</label>
                        <input v-model="form.npsn" type="text" :class="inputClass" id="npsn" />
                    </div>
                    <div>
                        <label :class="labelClass" for="nss">NSS</label>
                        <input v-model="form.nss" type="text" :class="inputClass" id="nss" />
                    </div>
                    <div>
                        <label :class="labelClass" for="jenjang">Jenjang</label>
                        <select v-model="form.jenjang" :class="inputClass" id="jenjang">
                            <option value="">Pilih jenjang</option>
                            <option :value="1">SD / MI</option>
                            <option :value="2">SMP / MTs</option>
                            <option :value="3">SMA / MA / SMK</option>
                        </select>
                        <p v-if="form.errors.jenjang" class="mt-1 text-xs text-destructive">{{ form.errors.jenjang }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="kode-sekolah">Kode Sekolah</label>
                        <input v-model="form.kode_sekolah" type="text" :class="inputClass" id="kode-sekolah" />
                    </div>
                    <div>
                        <label :class="labelClass" for="kepala-sekolah">Kepala Sekolah</label>
                        <input v-model="form.kepsek" type="text" :class="inputClass" id="kepala-sekolah" />
                    </div>
                    <div>
                        <label :class="labelClass" for="nip-kepala-sekolah">NIP Kepala Sekolah</label>
                        <input v-model="form.nip" type="text" :class="inputClass" id="nip-kepala-sekolah" />
                    </div>
                </div>
            </div>

            <div :class="cardClass" class="p-5">
                <div class="mb-4 flex items-center gap-2 text-sm font-semibold text-foreground">
                    <i class="fas fa-map-marker-alt h-4 w-4 text-primary" ></i> Alamat & Kontak
                </div>
                <div class="grid gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-6">
                        <label :class="labelClass" for="alamat">Alamat</label>
                        <input v-model="form.alamat" type="text" :class="inputClass" id="alamat" />
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="desa-kelurahan">Desa / Kelurahan</label>
                        <input v-model="form.desa" type="text" :class="inputClass" id="desa-kelurahan" />
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="kecamatan">Kecamatan</label>
                        <input v-model="form.kecamatan" type="text" :class="inputClass" id="kecamatan" />
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="kode-pos">Kode Pos</label>
                        <input v-model="form.kode_pos" type="number" :class="inputClass" id="kode-pos" />
                    </div>
                    <div class="sm:col-span-3">
                        <label :class="labelClass" for="kota-kabupaten">Kota / Kabupaten</label>
                        <input v-model="form.kota" type="text" :class="inputClass" id="kota-kabupaten" />
                    </div>
                    <div class="sm:col-span-3">
                        <label :class="labelClass" for="provinsi">Provinsi</label>
                        <input v-model="form.provinsi" type="text" :class="inputClass" id="provinsi" />
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="telepon">Telepon</label>
                        <input v-model="form.telp" type="text" :class="inputClass" id="telepon" />
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="fax">Fax</label>
                        <input v-model="form.fax" type="text" :class="inputClass" id="fax" />
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="email">Email</label>
                        <input v-model="form.email" type="email" :class="inputClass" id="email" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-destructive">{{ form.errors.email }}</p>
                    </div>
                    <div class="sm:col-span-6">
                        <label :class="labelClass" for="website">Website</label>
                        <input v-model="form.web" type="text" :class="inputClass" id="website" />
                    </div>
                </div>
            </div>

            <div :class="cardClass" class="p-5">
                <div class="mb-4 flex items-center gap-2 text-sm font-semibold text-foreground">
                    <i class="fas fa-desktop h-4 w-4 text-primary" ></i> Aplikasi & Logo
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label :class="labelClass" for="nama-aplikasi">Nama Aplikasi</label>
                        <input v-model="form.nama_aplikasi" type="text" :class="inputClass" id="nama-aplikasi" />
                    </div>
                    <div>
                        <label :class="labelClass" for="logo-kiri">Logo Kiri</label>
                        <input type="file" accept="image/*" class="block w-full text-xs text-muted-foreground" @change="pickLogo($event, 'logo_kiri')" id="logo-kiri" />
                        <img v-if="props.logoKiri" :src="props.logoKiri" alt="Logo kiri" class="mt-2 h-14 w-auto rounded border border-border bg-card p-1" />
                    </div>
                    <div>
                        <label :class="labelClass" for="logo-kanan">Logo Kanan</label>
                        <input type="file" accept="image/*" class="block w-full text-xs text-muted-foreground" @change="pickLogo($event, 'logo_kanan')" id="logo-kanan" />
                        <img v-if="props.logoKanan" :src="props.logoKanan" alt="Logo kanan" class="mt-2 h-14 w-auto rounded border border-border bg-card p-1" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" :class="btnPrimary" :disabled="form.processing">
                    <i class="fas fa-save h-4 w-4" ></i> {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
