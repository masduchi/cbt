<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, cardClass, inputClass, labelClass } from '@/ui';

const props = defineProps({
    kartu: { type: Object, default: null },
    berita: { type: Object, default: null },
    absensi: { type: Object, default: null },
    sekolah: { type: String, default: '' },
});

const form = useForm({
    kartu: {
        header_1: props.kartu?.header_1 ?? '',
        header_2: props.kartu?.header_2 ?? '',
        header_3: props.kartu?.header_3 ?? '',
        header_4: props.kartu?.header_4 ?? '',
        tanggal: props.kartu?.tanggal ?? '',
    },
    berita: {
        header_1: props.berita?.header_1 ?? '',
        header_2: props.berita?.header_2 ?? '',
        header_3: props.berita?.header_3 ?? '',
        header_4: props.berita?.header_4 ?? '',
    },
    absensi: {
        header_1: props.absensi?.header_1 ?? '',
        header_2: props.absensi?.header_2 ?? '',
        header_3: props.absensi?.header_3 ?? '',
        header_4: props.absensi?.header_4 ?? '',
        proktor: props.absensi?.proktor ?? '',
        pengawas_1: props.absensi?.pengawas_1 ?? '',
        pengawas_2: props.absensi?.pengawas_2 ?? '',
    },
});

function submit() {
    form.post('/setting/kop', { preserveScroll: true });
}

const sections = [
    {
        key: 'kartu',
        title: 'Kop Kartu Peserta',
        icon: 'print',
        hint: 'Baris 1 biasanya nama sekolah, baris 2 alamat, baris 3 kontak, baris 4 keterangan tambahan.',
        fields: [
            ['header_1', 'Baris 1'],
            ['header_2', 'Baris 2'],
            ['header_3', 'Baris 3'],
            ['header_4', 'Baris 4'],
            ['tanggal', 'Keterangan tanggal'],
        ],
    },
    {
        key: 'berita',
        title: 'Kop Berita Acara',
        icon: 'file-signature',
        hint: 'Kop ini dicetak di bagian atas berita acara pelaksanaan ujian.',
        fields: [
            ['header_1', 'Baris 1'],
            ['header_2', 'Baris 2'],
            ['header_3', 'Baris 3'],
            ['header_4', 'Baris 4'],
        ],
    },
    {
        key: 'absensi',
        title: 'Kop Daftar Hadir',
        icon: 'user-check',
        hint: 'Nama proktor dan pengawas dipakai pada bagian tanda tangan daftar hadir.',
        fields: [
            ['header_1', 'Baris 1'],
            ['header_2', 'Baris 2'],
            ['header_3', 'Baris 3'],
            ['header_4', 'Baris 4'],
            ['proktor', 'Proktor'],
            ['pengawas_1', 'Pengawas 1'],
            ['pengawas_2', 'Pengawas 2'],
        ],
    },
];
</script>

<template>
    <Head title="Kop Cetak Ujian" />
    <AdminLayout title="Kop Cetak Ujian">
        <form class="space-y-6" @submit.prevent="submit">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Kop Cetak Ujian">
                    <template #description>Kosongkan untuk memakai nama sekolah dari profil sekolah{{ props.sekolah ? ` (${props.sekolah})` : '' }}.</template>
                
                </PageHeader>
                <button type="submit" :class="btnPrimary" :disabled="form.processing">Simpan Kop</button>
            </div>

            <section v-for="section in sections" :key="section.key" :class="cardClass">
                <div class="flex items-center gap-2 border-b border-border p-5">
                    <i :class="'fas fa-' + (section.icon)" class="h-4 w-4 text-primary" ></i>
                    <h2 class="text-sm font-semibold text-foreground">{{ section.title }}</h2>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <div v-for="[field, label] in section.fields" :key="field">
                        <label :class="labelClass">{{ label }}</label>
                        <input v-model="form[section.key][field]" :class="inputClass" :aria-label="`${section.title}: ${label}`" />
                        <p v-if="form.errors[`${section.key}.${field}`]" class="mt-1 text-xs text-destructive">
                            {{ form.errors[`${section.key}.${field}`] }}
                        </p>
                    </div>
                    <p class="text-xs text-muted-foreground sm:col-span-2">{{ section.hint }}</p>
                </div>
            </section>
        </form>
    </AdminLayout>
</template>
