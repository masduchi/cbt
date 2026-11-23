<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { cardClass } from '@/ui';

const props = defineProps({
    anak: { type: Array, default: () => [] },
    periode: { type: String, default: '' },
});

function formatNumber(value) {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(Number(value) || 0);
}
</script>

<template>
    <Head title="Anak Saya" />
    <AdminLayout title="Anak Saya">
        <div class="page-stack space-y-6">
            <div class="-mx-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:px-[30px]">
                <PageHeader title="Portal Wali Murid">
                    <template #description>
                        Pantau nilai ujian, kehadiran, dan catatan guru. Hanya baca. Periode {{ props.periode }}.
                    </template>
                </PageHeader>
            </div>

            <div v-if="!props.anak.length" :class="cardClass" class="p-10 text-center">
                <i class="fas fa-user-friends mx-auto h-10 w-10 text-muted-foreground/50" aria-hidden="true"></i>
                <p class="mt-3 text-sm font-medium text-foreground">Akun ini belum ditautkan ke data siswa.</p>
                <p class="mt-1 text-xs text-muted-foreground">Hubungi administrator sekolah untuk menautkan akun dengan siswa.</p>
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2">
                <Link
                    v-for="item in props.anak"
                    :key="item.id_siswa"
                    :href="`/wali-murid/${item.id_siswa}`"
                    :class="cardClass"
                    class="block p-5 transition-shadow hover:shadow-dropdown focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-foreground">{{ item.nama }}</p>
                            <p class="text-xs text-muted-foreground">{{ item.nis }} · {{ item.kelas || 'Kelas belum diatur' }}</p>
                        </div>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <i class="fas fa-user-graduate h-4 w-4" aria-hidden="true"></i>
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-muted/60 px-2 py-2">
                            <p class="text-2xs text-muted-foreground">Ujian</p>
                            <p class="text-sm font-semibold text-foreground">{{ item.ujian }}</p>
                        </div>
                        <div class="rounded-lg bg-muted/60 px-2 py-2">
                            <p class="text-2xs text-muted-foreground">Rata nilai</p>
                            <p class="text-sm font-semibold text-foreground">{{ item.rata_nilai === null ? '-' : formatNumber(item.rata_nilai) }}</p>
                        </div>
                        <div class="rounded-lg bg-muted/60 px-2 py-2">
                            <p class="text-2xs text-muted-foreground">Kehadiran</p>
                            <p class="text-sm font-semibold text-foreground">{{ item.kehadiran === null ? '-' : `${formatNumber(item.kehadiran)}%` }}</p>
                        </div>
                    </div>

                    <p class="mt-3 inline-flex items-center gap-1 text-xs font-medium text-primary">
                        Lihat detail <i class="fas fa-arrow-right h-3 w-3" aria-hidden="true"></i>
                    </p>
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>
