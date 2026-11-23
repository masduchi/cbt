<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { btnPrimary, btnSecondary, inputClass } from '@/ui';

const props = defineProps({
    step: { type: Number, default: 1 },
    databaseReady: { type: Boolean, default: false },
    database: { type: Object, default: () => ({}) },
    admin: { type: Object, default: null },
    sekolah: { type: Object, default: null },
    jenjang: { type: Object, default: () => ({}) },
    satuan: { type: Object, default: () => ({}) },
});

const active = ref(Math.min(Math.max(props.step, 1), 4));

const steps = [
    { id: 1, label: 'Database', icon: 'database' },
    { id: 2, label: 'Administrator', icon: 'user' },
    { id: 3, label: 'Sekolah', icon: 'school' },
    { id: 4, label: 'Selesai', icon: 'check' },
];

const databaseForm = useForm({
    host: props.database.host ?? '127.0.0.1',
    port: props.database.port ?? '3306',
    database: props.database.database ?? 'cbt_laravel',
    username: props.database.username ?? 'root',
    password: '',
});

const adminForm = useForm({
    nama_lengkap: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const schoolForm = useForm({
    nama_aplikasi: props.sekolah?.nama_aplikasi ?? 'CBT Sekolah',
    sekolah: props.sekolah?.sekolah ?? '',
    npsn: props.sekolah?.npsn ?? '',
    kepsek: props.sekolah?.kepsek ?? '',
    jenjang: props.sekolah?.jenjang ?? '',
    satuan_pendidikan: props.sekolah?.satuan_pendidikan ?? '',
    alamat: props.sekolah?.alamat ?? '',
    desa: props.sekolah?.desa ?? '',
    kecamatan: props.sekolah?.kecamatan ?? '',
    kota: props.sekolah?.kota ?? '',
    provinsi: props.sekolah?.provinsi ?? '',
    telp: props.sekolah?.telp ?? '',
    email: props.sekolah?.email ?? '',
});

const finishForm = useForm({});

const satuanOptions = computed(() => props.satuan?.[schoolForm.jenjang] ?? []);
const satuanLabel = computed(() => {
    const jenjang = props.sekolah?.jenjang ?? schoolForm.jenjang;
    const kode = Number(props.sekolah?.satuan_pendidikan ?? schoolForm.satuan_pendidikan);

    return props.satuan?.[jenjang]?.[kode - 1] ?? '-';
});
const adminDone = computed(() => Boolean(props.admin));
const schoolDone = computed(() => Boolean(props.sekolah));

const summary = computed(() => [
    ['Database', databaseForm.database],
    ['Host', `${databaseForm.host}:${databaseForm.port}`],
    ['Username database', databaseForm.username],
    ['Nama aplikasi', props.sekolah?.nama_aplikasi ?? schoolForm.nama_aplikasi],
    ['Nama administrator', props.admin?.nama_lengkap ?? adminForm.nama_lengkap],
    ['Username administrator', props.admin?.username ?? adminForm.username],
    ['Nama sekolah', props.sekolah?.sekolah ?? schoolForm.sekolah],
    ['Kepala sekolah', props.sekolah?.kepsek ?? schoolForm.kepsek],
    ['Jenjang', props.jenjang?.[props.sekolah?.jenjang ?? schoolForm.jenjang] ?? '-'],
    ['Satuan pendidikan', satuanLabel.value],
    [
        'Alamat',
        [props.sekolah?.alamat ?? schoolForm.alamat, props.sekolah?.desa ?? schoolForm.desa, props.sekolah?.kecamatan ?? schoolForm.kecamatan, props.sekolah?.kota ?? schoolForm.kota, props.sekolah?.provinsi ?? schoolForm.provinsi]
            .filter(Boolean)
            .join(', '),
    ],
]);

function saveDatabase() {
    databaseForm.post('/install/database', {
        preserveScroll: true,
        onSuccess: () => {
            if (!databaseForm.hasErrors) {
                active.value = Math.max(active.value, 2);
            }
        },
    });
}

function saveAdmin() {
    adminForm.post('/install/administrator', {
        preserveScroll: true,
        onSuccess: () => {
            if (!adminForm.hasErrors) {
                active.value = Math.max(active.value, 3);
            }
        },
    });
}

function saveSchool() {
    schoolForm.post('/install/sekolah', {
        preserveScroll: true,
        onSuccess: () => {
            if (!schoolForm.hasErrors) {
                active.value = 4;
            }
        },
    });
}

function finish() {
    finishForm.post('/install/selesai');
}
</script>

<template>
    <Head title="Instalasi Aplikasi" />

    <div class="min-h-screen bg-muted/50">
        <div class="mx-auto max-w-5xl px-4 py-10">
            <header class="mb-8 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-primary text-white">
                        <i class="fas fa-graduation-cap h-6 w-6" aria-hidden="true" ></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-foreground">Instalasi Aplikasi CBT</p>
                        <p class="text-xs text-muted-foreground">Gabungan aplikasi rapor, ujian, dan e-learning</p>
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">Langkah {{ active }} dari 4</p>
            </header>

            <nav aria-label="Langkah instalasi" class="mb-6 grid gap-2 sm:grid-cols-4">
                <div
                    v-for="item in steps"
                    :key="item.id"
                    class="flex items-center gap-2 rounded-xl border px-3 py-2.5 text-sm transition-colors"
                    :class="[
                        active === item.id ? 'border-primary bg-primary/5 text-foreground' : 'border-border bg-card text-muted-foreground',
                        item.id < active ? 'border-success/40 text-foreground' : '',
                    ]"
                >
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                        :class="item.id <= active ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'"
                    >
                        <i v-if="item.id < active" class="fas fa-check h-4 w-4" aria-hidden="true" ></i>
                        <template v-else>{{ item.id }}</template>
                    </span>
                    <span class="truncate font-medium">{{ item.label }}</span>
                </div>
            </nav>

            <div v-if="$page.props.flash?.success" class="mb-4 rounded-lg border border-success/30 bg-success/10 px-3.5 py-2.5 text-sm text-success">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="mb-4 rounded-lg border border-destructive/30 bg-destructive/10 px-3.5 py-2.5 text-sm text-destructive">
                {{ $page.props.flash.error }}
            </div>

            <section class="rounded-xl border border-border bg-card p-5 shadow-card sm:p-6">
                <div v-if="active === 1" class="space-y-5">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">Koneksi database</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Masukkan kredensial MySQL. Wizard akan membuat database bila belum ada, menjalankan migrasi, dan mengisi data referensi.
                        </p>
                    </div>

                    <div v-if="databaseForm.errors.database" class="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                        {{ databaseForm.errors.database }}
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-host">Host name</label>
                            <input id="install-host" v-model="databaseForm.host" :class="inputClass" autocomplete="off" />
                            <p v-if="databaseForm.errors.host" class="mt-1 text-xs text-destructive">{{ databaseForm.errors.host }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-port">Port</label>
                            <input id="install-port" v-model="databaseForm.port" :class="inputClass" inputmode="numeric" autocomplete="off" />
                            <p v-if="databaseForm.errors.port" class="mt-1 text-xs text-destructive">{{ databaseForm.errors.port }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-db">Nama database</label>
                            <input id="install-db" v-model="databaseForm.database" :class="inputClass" autocomplete="off" />
                            <p class="mt-1 text-xs text-muted-foreground">Tanpa spasi. Database dibuat otomatis bila belum ada.</p>
                            <p v-if="databaseForm.errors.database" class="mt-1 text-xs text-destructive">{{ databaseForm.errors.database }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-user">Username database</label>
                            <input id="install-user" v-model="databaseForm.username" :class="inputClass" autocomplete="off" />
                            <p v-if="databaseForm.errors.username" class="mt-1 text-xs text-destructive">{{ databaseForm.errors.username }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-pass">Password database</label>
                            <input id="install-pass" v-model="databaseForm.password" type="password" :class="inputClass" autocomplete="new-password" />
                            <p class="mt-1 text-xs text-muted-foreground">Kosongkan bila server MySQL tanpa password.</p>
                            <p v-if="databaseForm.errors.password" class="mt-1 text-xs text-destructive">{{ databaseForm.errors.password }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" :class="btnPrimary" :disabled="databaseForm.processing" @click="saveDatabase">
                            <i v-if="databaseForm.processing" class="fas fa-circle-notch h-4 w-4 animate-spin" aria-hidden="true" ></i>
                            Uji koneksi &amp; buat skema
                        </button>
                    </div>
                </div>

                <div v-else-if="active === 2" class="space-y-5">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">Akun administrator</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Akun ini dipakai untuk masuk pertama kali dan mengelola seluruh data aplikasi.
                        </p>
                    </div>

                    <div v-if="adminDone" class="flex items-start gap-2 rounded-lg border border-success/30 bg-success/10 px-3 py-2.5 text-sm text-success">
                        <i class="fas fa-shield-alt mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" ></i>
                        <span>Administrator <strong>{{ admin.username }}</strong> sudah dibuat.</span>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-nama">Nama lengkap</label>
                            <input id="install-nama" v-model="adminForm.nama_lengkap" :class="inputClass" :disabled="adminDone" autocomplete="name" />
                            <p v-if="adminForm.errors.nama_lengkap" class="mt-1 text-xs text-destructive">{{ adminForm.errors.nama_lengkap }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-username">Username</label>
                            <input id="install-username" v-model="adminForm.username" :class="inputClass" :disabled="adminDone" autocomplete="off" />
                            <p v-if="adminForm.errors.username" class="mt-1 text-xs text-destructive">{{ adminForm.errors.username }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-email">Email <span class="text-muted-foreground">(opsional)</span></label>
                            <input id="install-email" v-model="adminForm.email" type="email" :class="inputClass" :disabled="adminDone" autocomplete="email" />
                            <p v-if="adminForm.errors.email" class="mt-1 text-xs text-destructive">{{ adminForm.errors.email }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-admin-pass">Password</label>
                            <input id="install-admin-pass" v-model="adminForm.password" type="password" :class="inputClass" :disabled="adminDone" autocomplete="new-password" />
                            <p class="mt-1 text-xs text-muted-foreground">Minimal 6 karakter.</p>
                            <p v-if="adminForm.errors.password" class="mt-1 text-xs text-destructive">{{ adminForm.errors.password }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-admin-pass2">Ulangi password</label>
                            <input id="install-admin-pass2" v-model="adminForm.password_confirmation" type="password" :class="inputClass" :disabled="adminDone" autocomplete="new-password" />
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-between gap-2">
                        <button type="button" :class="btnSecondary" @click="active = 1">Sebelumnya</button>
                        <button v-if="!adminDone" type="button" :class="btnPrimary" :disabled="adminForm.processing" @click="saveAdmin">
                            <i v-if="adminForm.processing" class="fas fa-circle-notch h-4 w-4 animate-spin" aria-hidden="true" ></i>
                            Simpan &amp; lanjut
                        </button>
                        <button v-else type="button" :class="btnPrimary" @click="active = 3">Lanjut</button>
                    </div>
                </div>

                <div v-else-if="active === 3" class="space-y-5">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">Identitas sekolah</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Data ini tampil pada kop rapor, leger, DKN, dan buku induk. Logo dapat diunggah setelah instalasi di menu Pengaturan Sekolah.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-app">Nama aplikasi</label>
                            <input id="install-app" v-model="schoolForm.nama_aplikasi" :class="inputClass" />
                            <p v-if="schoolForm.errors.nama_aplikasi" class="mt-1 text-xs text-destructive">{{ schoolForm.errors.nama_aplikasi }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-sekolah">Nama sekolah</label>
                            <input id="install-sekolah" v-model="schoolForm.sekolah" :class="inputClass" />
                            <p v-if="schoolForm.errors.sekolah" class="mt-1 text-xs text-destructive">{{ schoolForm.errors.sekolah }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-npsn">NPSN <span class="text-muted-foreground">(opsional)</span></label>
                            <input id="install-npsn" v-model="schoolForm.npsn" :class="inputClass" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-kepsek">Kepala sekolah</label>
                            <input id="install-kepsek" v-model="schoolForm.kepsek" :class="inputClass" />
                            <p v-if="schoolForm.errors.kepsek" class="mt-1 text-xs text-destructive">{{ schoolForm.errors.kepsek }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-jenjang">Jenjang</label>
                            <select id="install-jenjang" v-model="schoolForm.jenjang" :class="inputClass">
                                <option value="" disabled>Pilih jenjang</option>
                                <option v-for="(label, value) in jenjang" :key="value" :value="Number(value)">{{ label }}</option>
                            </select>
                            <p v-if="schoolForm.errors.jenjang" class="mt-1 text-xs text-destructive">{{ schoolForm.errors.jenjang }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-satuan">Satuan pendidikan</label>
                            <select id="install-satuan" v-model="schoolForm.satuan_pendidikan" :class="inputClass">
                                <option value="" disabled>Pilih satuan pendidikan</option>
                                <option v-for="(item, index) in satuanOptions" :key="item" :value="index + 1">{{ item }}</option>
                            </select>
                            <p v-if="schoolForm.errors.satuan_pendidikan" class="mt-1 text-xs text-destructive">{{ schoolForm.errors.satuan_pendidikan }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-alamat">Alamat</label>
                            <input id="install-alamat" v-model="schoolForm.alamat" :class="inputClass" />
                            <p v-if="schoolForm.errors.alamat" class="mt-1 text-xs text-destructive">{{ schoolForm.errors.alamat }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-desa">Desa/Kelurahan</label>
                            <input id="install-desa" v-model="schoolForm.desa" :class="inputClass" />
                            <p v-if="schoolForm.errors.desa" class="mt-1 text-xs text-destructive">{{ schoolForm.errors.desa }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-kec">Kecamatan</label>
                            <input id="install-kec" v-model="schoolForm.kecamatan" :class="inputClass" />
                            <p v-if="schoolForm.errors.kecamatan" class="mt-1 text-xs text-destructive">{{ schoolForm.errors.kecamatan }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-kota">Kabupaten/Kota</label>
                            <input id="install-kota" v-model="schoolForm.kota" :class="inputClass" />
                            <p v-if="schoolForm.errors.kota" class="mt-1 text-xs text-destructive">{{ schoolForm.errors.kota }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-prov">Provinsi</label>
                            <input id="install-prov" v-model="schoolForm.provinsi" :class="inputClass" />
                            <p v-if="schoolForm.errors.provinsi" class="mt-1 text-xs text-destructive">{{ schoolForm.errors.provinsi }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-telp">Telepon <span class="text-muted-foreground">(opsional)</span></label>
                            <input id="install-telp" v-model="schoolForm.telp" :class="inputClass" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground" for="install-email-sekolah">Email sekolah <span class="text-muted-foreground">(opsional)</span></label>
                            <input id="install-email-sekolah" v-model="schoolForm.email" type="email" :class="inputClass" />
                            <p v-if="schoolForm.errors.email" class="mt-1 text-xs text-destructive">{{ schoolForm.errors.email }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-between gap-2">
                        <button type="button" :class="btnSecondary" @click="active = 2">Sebelumnya</button>
                        <button v-if="!schoolDone" type="button" :class="btnPrimary" :disabled="schoolForm.processing" @click="saveSchool">
                            <i v-if="schoolForm.processing" class="fas fa-circle-notch h-4 w-4 animate-spin" aria-hidden="true" ></i>
                            Simpan &amp; lanjut
                        </button>
                        <button v-else type="button" :class="btnPrimary" @click="active = 4">Lanjut</button>
                    </div>
                </div>

                <div v-else class="space-y-5">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">Ringkasan instalasi</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Periksa kembali data berikut sebelum menyelesaikan instalasi.</p>
                    </div>

                    <dl class="grid gap-x-6 gap-y-2 rounded-lg border border-border bg-muted/40 p-4 sm:grid-cols-2">
                        <div v-for="[label, value] in summary" :key="label" class="flex gap-2 text-sm">
                            <dt class="w-44 shrink-0 text-muted-foreground">{{ label }}</dt>
                            <dd class="min-w-0 break-words font-medium text-foreground">: {{ value || '-' }}</dd>
                        </div>
                    </dl>

                    <p class="text-xs text-muted-foreground">
                        Setelah selesai, wizard tidak dapat dibuka lagi. Tahun pelajaran aktif akan disetel otomatis ke tahun berjalan.
                    </p>

                    <div class="flex flex-wrap justify-between gap-2">
                        <button type="button" :class="btnSecondary" @click="active = 3">Sebelumnya</button>
                        <button type="button" :class="btnPrimary" :disabled="finishForm.processing" @click="finish">
                            <i v-if="finishForm.processing" class="fas fa-circle-notch h-4 w-4 animate-spin" aria-hidden="true" ></i>
                            Simpan &amp; selesaikan instalasi
                        </button>
                    </div>
                </div>
            </section>

            <p class="mt-6 text-center text-xs text-muted-foreground">
                Butuh bantuan? Pastikan server MySQL aktif dan folder proyek dapat menulis berkas <code>.env</code>.
            </p>
        </div>
    </div>
</template>
