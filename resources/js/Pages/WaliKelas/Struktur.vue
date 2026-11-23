<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, inputClass, labelClass } from '@/ui';
import { computed } from 'vue';

const props = defineProps({
    classes: { type: Array, default: () => [] },
    selected: { type: Object, default: null },
    students: { type: Array, default: () => [] },
    struktur: { type: Object, default: null },
    roles: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const form = useForm({
    id_kelas: props.selected?.id_kelas ?? '',
    ...Object.fromEntries((props.roles ?? []).map((role) => [role.key, props.struktur?.[role.key] ? String(props.struktur[role.key]) : ''])),
});

const inti = computed(() => props.roles.slice(0, 6));
const seksi = computed(() => props.roles.slice(6));
const errorCount = computed(() => Object.keys(form.errors).length);

function changeClass(event) {
    router.get('/wali/struktur', { kelas: event.target.value }, { preserveScroll: true });
}

function submit() {
    form.post('/wali/struktur', { preserveScroll: true });
}
</script>

<template>
    <Head title="Struktur Kelas" />
    <AdminLayout title="Struktur Kelas">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Struktur Organisasi Kelas" description="Tetapkan pengurus kelas untuk setiap kelas yang Anda ampu sebagai wali kelas." /></div>

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

                <div
                    v-if="errorCount"
                    role="alert"
                    class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
                >
                    Periksa kembali {{ errorCount }} isian yang belum sesuai.
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <section class="rounded-xl border border-border bg-card p-5 shadow-card">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-users h-4 w-4 text-primary" ></i>
                            <h2 class="text-sm font-semibold text-foreground">Pengurus inti</h2>
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="role in inti" :key="role.key">
                                <label :for="role.key" :class="labelClass">{{ role.label }}</label>
                                <select :id="role.key" v-model="form[role.key]" :class="inputClass">
                                    <option value="">Belum ditentukan</option>
                                    <option v-for="siswa in props.students" :key="siswa.id_siswa" :value="String(siswa.id_siswa)">
                                        {{ siswa.nama }} ({{ siswa.nis }})
                                    </option>
                                </select>
                                <p v-if="form.errors[role.key]" class="mt-1 text-xs text-destructive">{{ form.errors[role.key] }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-border bg-card p-5 shadow-card">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-sitemap h-4 w-4 text-primary" ></i>
                            <h2 class="text-sm font-semibold text-foreground">Seksi bidang</h2>
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="role in seksi" :key="role.key">
                                <label :for="role.key" :class="labelClass">{{ role.label }}</label>
                                <select :id="role.key" v-model="form[role.key]" :class="inputClass">
                                    <option value="">Belum ditentukan</option>
                                    <option v-for="siswa in props.students" :key="siswa.id_siswa" :value="String(siswa.id_siswa)">
                                        {{ siswa.nama }} ({{ siswa.nis }})
                                    </option>
                                </select>
                                <p v-if="form.errors[role.key]" class="mt-1 text-xs text-destructive">{{ form.errors[role.key] }}</p>
                            </div>
                        </div>
                    </section>

                    <p v-if="!props.students.length" class="text-sm text-muted-foreground">
                        Kelas ini belum memiliki siswa. Tambahkan anggota kelas terlebih dahulu.
                    </p>

                    <div class="flex items-center justify-end">
                        <button type="submit" :class="btnPrimary" :disabled="form.processing || !props.students.length">
                            <i class="fas fa-save h-4 w-4" ></i> {{ form.processing ? 'Menyimpan…' : 'Simpan struktur' }}
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </AdminLayout>
</template>
