<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { btnPrimary, inputClass, labelClass } from '@/ui';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    profile: { type: Object, required: true },
    mustChange: { type: Boolean, default: false },
});

const roleLabel = { admin: 'Administrator', guru: 'Guru', siswa: 'Siswa' };
const initial = computed(() => (props.profile.nama || '?').charAt(0).toUpperCase());
const lastLogin = computed(() => {
    if (!props.profile.last_login) return 'Belum pernah masuk';
    return new Date(props.profile.last_login * 1000).toLocaleString('id-ID', {
        dateStyle: 'long',
        timeStyle: 'short',
    });
});

const identity = computed(() => {
    const fields = props.profile.details?.fields;
    if (!fields) return null;
    if (props.profile.details.type === 'guru') return { label: 'NIP', value: fields.nip };
    return { label: 'NIS / NISN', value: [fields.nis, fields.nisn].filter(Boolean).join(' / ') };
});

const genderOptions = [
    { value: 'L', label: 'Laki-laki' },
    { value: 'P', label: 'Perempuan' },
];
const religionOptions = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'].map((v) => ({ value: v, label: v }));
const maritalOptions = ['Belum Menikah', 'Menikah', 'Cerai Hidup', 'Cerai Mati'].map((v) => ({ value: v, label: v }));

const guruFields = [
    { key: 'nama_guru', label: 'Nama lengkap', required: true, max: 50 },
    { key: 'jenis_kelamin', label: 'Jenis kelamin', type: 'select', options: genderOptions },
    { key: 'tempat_lahir', label: 'Tempat lahir', max: 30 },
    { key: 'tgl_lahir', label: 'Tanggal lahir', type: 'date' },
    { key: 'agama', label: 'Agama', type: 'select', options: religionOptions },
    { key: 'status_nikah', label: 'Status perkawinan', type: 'select', options: maritalOptions },
    { key: 'no_hp', label: 'Nomor HP', type: 'tel', max: 13 },
    { key: 'email', label: 'Email', type: 'email', max: 254 },
    { key: 'alamat_jalan', label: 'Alamat jalan', type: 'textarea', max: 255, wide: true },
    { key: 'rt_rw', label: 'RT / RW', max: 8 },
    { key: 'dusun', label: 'Dusun', max: 50 },
    { key: 'kelurahan', label: 'Kelurahan / desa', max: 50 },
    { key: 'kecamatan', label: 'Kecamatan', max: 30 },
    { key: 'kabupaten', label: 'Kabupaten / kota', max: 30 },
    { key: 'provinsi', label: 'Provinsi', max: 30 },
    { key: 'kode_pos', label: 'Kode pos', type: 'number', max: 5 },
    { key: 'kewarganegaraan', label: 'Kewarganegaraan', max: 10 },
];

const siswaFields = [
    { key: 'nama', label: 'Nama lengkap', required: true, max: 50 },
    { key: 'jenis_kelamin', label: 'Jenis kelamin', type: 'select', options: genderOptions },
    { key: 'tempat_lahir', label: 'Tempat lahir', max: 100 },
    { key: 'tanggal_lahir', label: 'Tanggal lahir', type: 'date' },
    { key: 'agama', label: 'Agama', type: 'select', options: religionOptions },
    { key: 'hp', label: 'Nomor HP', type: 'tel', max: 15 },
    { key: 'email', label: 'Email', type: 'email', max: 254 },
    { key: 'alamat', label: 'Alamat lengkap', type: 'textarea', max: 2000, wide: true },
    { key: 'rt', label: 'RT', max: 5 },
    { key: 'rw', label: 'RW', max: 5 },
    { key: 'kelurahan', label: 'Kelurahan / desa', max: 100 },
    { key: 'kecamatan', label: 'Kecamatan', max: 100 },
    { key: 'kabupaten', label: 'Kabupaten / kota', max: 100 },
    { key: 'provinsi', label: 'Provinsi', max: 100 },
    { key: 'kode_pos', label: 'Kode pos', type: 'number', max: 5 },
];

const activeFields = computed(() => {
    if (props.profile.details?.type === 'guru') return guruFields;
    if (props.profile.details?.type === 'siswa') return siswaFields;
    return [];
});

const initialDetails = {};
for (const field of activeFields.value) {
    initialDetails[field.key] = props.profile.details.fields[field.key] ?? '';
}

const detailForm = useForm({ photo: null, ...initialDetails });

const photoInput = ref(null);
const previewUrl = ref(null);
const photoName = ref('');
const avatarSrc = computed(() => previewUrl.value || props.profile.foto_url);

function optionsFor(field) {
    const current = detailForm[field.key];
    if (current && !field.options.some((option) => option.value === current)) {
        return [...field.options, { value: current, label: current }];
    }
    return field.options;
}

function pickPhoto(event) {
    const file = event.target.files?.[0] ?? null;

    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }

    if (file) {
        previewUrl.value = URL.createObjectURL(file);
        photoName.value = file.name;
    } else {
        photoName.value = '';
    }

    detailForm.photo = file;
}

onBeforeUnmount(() => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
});

function submitDetails() {
    detailForm
        .transform((data) => {
            const payload = { ...data };
            if (!(payload.photo instanceof File)) delete payload.photo;
            return payload;
        })
        .post('/profil', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                if (previewUrl.value) {
                    URL.revokeObjectURL(previewUrl.value);
                    previewUrl.value = null;
                }
                photoName.value = '';
                detailForm.photo = null;
            },
        });
}

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submitPassword() {
    passwordForm.post('/profil/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <Head title="Profil & Password" />
    <AdminLayout title="Profil & Password">
        <div class="page-stack space-y-6">
            <div
                v-if="props.mustChange"
                class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning"
                role="status"
            >
                <i class="fas fa-info-circle mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" ></i>
                <div>
                    <p class="font-semibold">Ganti password awal Anda</p>
                    <p class="text-warning">
                        Akun ini masih memakai password default. Buat password baru sebelum memakai aplikasi.
                    </p>
                </div>
            </div>

            <section class="rounded-xl border border-border bg-card shadow-card">
                <div class="flex items-center gap-4 border-b border-border p-5">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary/10 text-xl font-semibold text-primary">
                        <img
                            v-if="avatarSrc"
                            :src="avatarSrc"
                            :alt="`Foto ${props.profile.nama}`"
                            class="h-full w-full object-cover"
                        />
                        <span v-else aria-hidden="true">{{ initial }}</span>
                    </div>
                    <div class="min-w-0">
                        <h1 class="truncate text-base font-semibold text-foreground">{{ props.profile.nama }}</h1>
                        <p class="text-sm text-muted-foreground">{{ roleLabel[props.profile.role] || props.profile.role }}</p>
                    </div>
                </div>
                <dl class="grid gap-4 p-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Username</dt>
                        <dd class="mt-1 text-sm text-foreground">{{ props.profile.username }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Email</dt>
                        <dd class="mt-1 truncate text-sm text-foreground">{{ props.profile.email || 'Belum diisi' }}</dd>
                    </div>
                    <div v-if="identity">
                        <dt class="text-xs font-medium text-muted-foreground">{{ identity.label }}</dt>
                        <dd class="mt-1 text-sm text-foreground">{{ identity.value || 'Belum diisi' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">Login terakhir</dt>
                        <dd class="mt-1 text-sm text-foreground">{{ lastLogin }}</dd>
                    </div>
                </dl>
            </section>

            <section v-if="activeFields.length" class="rounded-xl border border-border bg-card shadow-card">
                <div class="flex items-center gap-2 border-b border-border p-5">
                    <i class="fas fa-camera h-4 w-4 text-primary" aria-hidden="true" ></i>
                    <h2 class="text-sm font-semibold text-foreground">Data diri</h2>
                </div>
                <form class="space-y-5 p-5" enctype="multipart/form-data" @submit.prevent="submitDetails">
                    <div
                        v-if="Object.keys(detailForm.errors).length"
                        role="alert"
                        class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                    >
                        Periksa kembali isian yang ditandai.
                    </div>

                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted text-2xl font-semibold text-muted-foreground">
                            <img
                                v-if="avatarSrc"
                                :src="avatarSrc"
                                :alt="`Pratinjau foto ${props.profile.nama}`"
                                class="h-full w-full object-cover"
                            />
                            <span v-else aria-hidden="true">{{ initial }}</span>
                        </div>
                        <div>
                            <p :class="labelClass">Foto profil</p>
                            <input
 id="photo"
 ref="photoInput"
 type="file"
 accept="image/png,image/jpeg,image/webp"
 class="sr-only"
 :aria-invalid="Boolean(detailForm.errors.photo)"
 :aria-describedby="detailForm.errors.photo ? 'photo-error' : 'photo-help'"
 @change="pickPhoto"
 />
                            <label
                                for="photo"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-border bg-card px-3.5 py-2 text-sm font-medium text-foreground transition hover:bg-muted/40 focus-within:ring-2 focus-within:ring-ring/30"
                            >
                                <i class="fas fa-camera h-4 w-4" aria-hidden="true" ></i> Pilih foto
                            </label>
                            <p v-if="photoName" class="mt-1 text-xs text-muted-foreground">{{ photoName }}</p>
                            <p v-if="detailForm.errors.photo" id="photo-error" role="alert" class="mt-1 text-xs text-destructive">
                                {{ detailForm.errors.photo }}
                            </p>
                            <p v-else id="photo-help" class="mt-1 text-xs text-muted-foreground">
                                JPG, PNG, atau WebP. Maksimal 2 MB.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div
                            v-for="field in activeFields"
                            :key="field.key"
                            :class="field.wide ? 'sm:col-span-2' : ''"
                        >
                            <label :class="labelClass" :for="field.key">
                                {{ field.label }}
                                <span v-if="field.required" class="text-destructive" aria-hidden="true">*</span>
                            </label>

                            <select
 v-if="field.type === 'select'"
 :id="field.key"
 v-model="detailForm[field.key]"
 :class="inputClass"
 :aria-invalid="Boolean(detailForm.errors[field.key])"
 :aria-describedby="detailForm.errors[field.key] ? `${field.key}-error` : undefined"
 >
                                <option value="">Pilih {{ field.label.toLowerCase() }}</option>
                                <option v-for="option in optionsFor(field)" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>

                            <textarea
 v-else-if="field.type === 'textarea'"
 :id="field.key"
 v-model="detailForm[field.key]"
 rows="3"
 :class="inputClass"
 :maxlength="field.max"
 :aria-invalid="Boolean(detailForm.errors[field.key])"
 :aria-describedby="detailForm.errors[field.key] ? `${field.key}-error` : undefined"
 />

                            <input
 v-else
 :id="field.key"
 v-model="detailForm[field.key]"
 :type="field.type || 'text'"
 :class="inputClass"
 :maxlength="field.max"
 :inputmode="field.type === 'number' ? 'numeric' : undefined"
 :aria-invalid="Boolean(detailForm.errors[field.key])"
 :aria-describedby="detailForm.errors[field.key] ? `${field.key}-error` : undefined"
 />

                            <p
                                v-if="detailForm.errors[field.key]"
                                :id="`${field.key}-error`"
                                role="alert"
                                class="mt-1 text-xs text-destructive"
                            >
                                {{ detailForm.errors[field.key] }}
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" :class="btnPrimary" :disabled="detailForm.processing">
                            <i class="fas fa-save h-4 w-4" aria-hidden="true" ></i> Simpan data diri
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-xl border border-border bg-card shadow-card">
                <div class="flex items-center gap-2 border-b border-border p-5">
                    <i class="fas fa-key h-4 w-4 text-primary" aria-hidden="true" ></i>
                    <h2 class="text-sm font-semibold text-foreground">Ubah Password</h2>
                </div>
                <form class="space-y-4 p-5" @submit.prevent="submitPassword">
                    <div>
                        <label :class="labelClass" for="current_password">Password saat ini</label>
                        <input
 id="current_password"
 v-model="passwordForm.current_password"
 type="password"
 :class="inputClass"
 autocomplete="current-password"
 :aria-invalid="Boolean(passwordForm.errors.current_password)"
 :aria-describedby="passwordForm.errors.current_password ? 'current_password-error' : undefined"
 />
                        <p
                            v-if="passwordForm.errors.current_password"
                            id="current_password-error"
                            role="alert"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ passwordForm.errors.current_password }}
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label :class="labelClass" for="password">Password baru</label>
                            <input
 id="password"
 v-model="passwordForm.password"
 type="password"
 :class="inputClass"
 autocomplete="new-password"
 :aria-invalid="Boolean(passwordForm.errors.password)"
 :aria-describedby="passwordForm.errors.password ? 'password-error' : undefined"
 />
                            <p
                                v-if="passwordForm.errors.password"
                                id="password-error"
                                role="alert"
                                class="mt-1 text-xs text-destructive"
                            >
                                {{ passwordForm.errors.password }}
                            </p>
                        </div>
                        <div>
                            <label :class="labelClass" for="password_confirmation">Ulangi password baru</label>
                            <input
 id="password_confirmation"
 v-model="passwordForm.password_confirmation"
 type="password"
 :class="inputClass"
 autocomplete="new-password"
 />
                        </div>
                    </div>
                    <p class="flex items-start gap-2 text-xs text-muted-foreground">
                        <i class="fas fa-shield-alt mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" aria-hidden="true" ></i>
                        Minimal 8 karakter. Setelah diganti, semua sesi lain akan keluar otomatis.
                    </p>
                    <div class="flex justify-end">
                        <button type="submit" :class="btnPrimary" :disabled="passwordForm.processing">
                            <i class="fas fa-key h-4 w-4" aria-hidden="true" ></i> Simpan password
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </AdminLayout>
</template>
