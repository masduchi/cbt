<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, inputClass } from '@/ui';
import { ref, watch } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    kelas: { type: Object, required: true },
    members: { type: Array, default: () => [] },
    available: { type: Array, default: () => [] },
    search: { type: String, default: '' },
    periode: { type: String, default: '' },
});

const page = usePage();
const isAdmin = page.props.auth.user?.role === 'admin';

const search = ref(props.search ?? '');
const selected = ref([]);
let timer = null;

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            `/master/kelas/${props.kelas.id_kelas}/anggota`,
            { search: value || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function toggle(id) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((item) => item !== id)
        : [...selected.value, id];
}

function addSelected() {
    if (!selected.value.length) return;

    router.post(
        `/master/kelas/${props.kelas.id_kelas}/anggota`,
        { siswa: selected.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
            },
        },
    );
}

async function removeMember(row) {
    if (await askConfirm(`Keluarkan ${row.nama} dari kelas ${props.kelas.nama_kelas}?`)) {
        router.delete(`/master/kelas/${props.kelas.id_kelas}/anggota/${row.id_siswa}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="`Anggota ${props.kelas.nama_kelas}`" />
    <AdminLayout :title="`Anggota ${props.kelas.nama_kelas}`">
        <div class="page-stack space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                    <Link href="/master/kelas" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-border bg-card p-2 text-muted-foreground transition hover:bg-muted/40 sm:h-9 sm:w-9">
                        <i class="fas fa-arrow-left h-4 w-4" ></i>
                    </Link>
                    <PageHeader :title="props.kelas.nama_kelas">
                    <template #description>{{ props.kelas.level?.level ? `Tingkat ${props.kelas.level.level}` : '' }}
                            {{ props.kelas.jurusan?.nama_jurusan ? `· ${props.kelas.jurusan.nama_jurusan}` : '' }}
                            · {{ props.periode }}</template>
                
                </PageHeader>
                </div>
                <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">{{ props.members.length }} siswa</span>
            </div>

            <div class="grid gap-6 lg:grid-cols-[1.2fr_1fr]">
                <section class="space-y-3">
                    <h2 class="text-sm font-semibold text-foreground">Anggota Kelas</h2>
                    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-card">
                        <div class="divide-y divide-border">
                            <div v-for="row in props.members" :key="row.id_siswa" class="flex items-center gap-3 px-4 py-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                    {{ row.nama.charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-foreground">{{ row.nama }}</p>
                                    <p class="text-xs text-muted-foreground">{{ row.nisn }} · {{ row.jenis_kelamin || '-' }}</p>
                                </div>
                                <button aria-label="Keluarkan dari kelas" type="button" v-if="isAdmin" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Keluarkan dari kelas" @click="removeMember(row)">
                                    <i class="fas fa-user-minus h-4 w-4" ></i>
                                </button>
                            </div>
                            <div v-if="!props.members.length" class="px-4 py-12 text-center">
                                <i class="fas fa-users mx-auto h-8 w-8 text-muted-foreground/50" ></i>
                                <p class="mt-2 text-sm text-muted-foreground">Belum ada anggota kelas.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="isAdmin" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-foreground">Tambah Siswa</h2>
                        <button type="button" :class="btnPrimary" :disabled="!selected.length" @click="addSelected">
                            <i class="fas fa-user-plus h-4 w-4" ></i> Tambah {{ selected.length || '' }}
                        </button>
                    </div>
                    <div class="relative">
                        <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" ></i>
                        <input v-model="search" :class="[inputClass, 'pl-9']" placeholder="Cari siswa tanpa kelas…" aria-label="Cari siswa tanpa kelas" />
                    </div>
                    <div class="max-h-[32rem] overflow-y-auto rounded-xl border border-border bg-card shadow-card">
                        <div class="divide-y divide-border">
                            <label
                                v-for="row in props.available"
                                :key="row.id_siswa"
                                class="flex cursor-pointer items-center gap-3 px-4 py-2.5 transition hover:bg-muted/40"
                            >
                                <input
 type="checkbox"
 class="rounded border-border text-primary focus:ring-ring"
 :checked="selected.includes(row.id_siswa)"
 @change="toggle(row.id_siswa)"
 />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-foreground">{{ row.nama }}</p>
                                    <p class="text-xs text-muted-foreground">{{ row.nisn }} · {{ row.jenis_kelamin || '-' }}</p>
                                </div>
                            </label>
                            <div v-if="!props.available.length" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                Tidak ada siswa tersedia.
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">Hanya siswa yang belum masuk kelas pada periode aktif yang ditampilkan (maks. 50).</p>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
