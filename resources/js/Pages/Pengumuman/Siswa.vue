<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PengumumanThread from '@/Components/PengumumanThread.vue';

const props = defineProps({
    posts: { type: Array, default: () => [] },
    siswa: { type: Object, default: () => ({}) },
    periode: { type: String, default: '' },
});
</script>

<template>
    <Head title="Pengumuman" />
    <AdminLayout title="Pengumuman">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Pengumuman">
                    <template #description>Informasi untuk {{ siswa.nama }}<span v-if="siswa.kelas"> · {{ siswa.kelas }}</span>. Periode {{ periode }}.</template>
                
                </PageHeader></div>

            <div v-if="!props.posts.length" class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                <i class="fas fa-bullhorn mx-auto mb-3 h-9 w-9 text-muted-foreground/50" aria-hidden="true" ></i>
                <p class="text-sm font-medium text-foreground">Belum ada pengumuman untuk Anda.</p>
                <p class="mt-1 text-sm text-muted-foreground">Pengumuman dari guru atau sekolah akan tampil di halaman ini.</p>
            </div>

            <PengumumanThread v-else :posts="props.posts" />
        </div>
    </AdminLayout>
</template>
