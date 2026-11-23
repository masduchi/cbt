<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, btnSecondary, inputClass, labelClass } from '@/ui';
import { ref, watch } from 'vue';

const props = defineProps({
    options: { type: Array, default: () => [] },
    selected: { type: [Number, String], default: null },
    combos: { type: Array, default: () => [] },
    guru: { type: Array, default: () => [] },
    periode: { type: String, default: '' },
});

const rows = ref([]);
const form = useForm({ rows: [] });

function syncRows() {
    rows.value = props.combos.map((row) => ({
        id_ruang: row.id_ruang,
        nama_ruang: row.nama_ruang,
        id_sesi: row.id_sesi,
        nama_sesi: row.nama_sesi,
        waktu: row.waktu,
        peserta: row.peserta,
        id_guru: Array.isArray(row.id_guru) ? [...row.id_guru] : [],
    }));
}

watch(() => props.combos, syncRows, { immediate: true });

function pilihJadwal(event) {
    if (!event.target.value) {
        return;
    }

    router.get('/cbt/pengawas', { jadwal: event.target.value }, { preserveScroll: true });
}

function toggleGuru(row, idGuru) {
    const index = row.id_guru.indexOf(idGuru);

    if (index === -1) {
        row.id_guru.push(idGuru);
    } else {
        row.id_guru.splice(index, 1);
    }
}

function save() {
    form.rows = rows.value.map((row) => ({
        id_jadwal: Number(props.selected),
        id_ruang: Number(row.id_ruang),
        id_sesi: Number(row.id_sesi),
        id_guru: row.id_guru,
    }));

    form.post('/cbt/pengawas', { preserveScroll: true });
}
</script>

<template>
    <Head title="Pengawas Ujian" />
    <AdminLayout title="Pengawas Ujian">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Pengawas Ujian">
                    <template #description>Tetapkan guru pengawas untuk setiap ruang dan sesi. Periode {{ periode }}.</template>
                
                </PageHeader>
                <button type="button" :class="btnPrimary" :disabled="!rows.length || form.processing" @click="save">
                    <i class="fas fa-save h-4 w-4" aria-hidden="true" ></i> {{ form.processing ? 'Menyimpan…' : 'Simpan Pengawas' }}
                </button>
            </div>

            <div class="rounded-xl border border-border bg-card p-4 shadow-card">
                <label :class="labelClass" for="jadwal">Jadwal ujian</label>
                <select id="jadwal" :class="inputClass" :value="props.selected ?? ''" :disabled="!props.options.length" @change="pilihJadwal">
                    <option v-if="!props.options.length" value="">Belum ada jadwal</option>
                    <option v-for="item in props.options" :key="item.id_jadwal" :value="item.id_jadwal">{{ item.label }}</option>
                </select>
            </div>

            <p v-if="form.errors.rows" role="alert" class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive">
                {{ form.errors.rows }}
            </p>

            <div v-if="!props.options.length" class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                <i class="fas fa-shield-alt mx-auto mb-3 h-9 w-9 text-muted-foreground/50" aria-hidden="true" ></i>
                <p class="text-sm font-medium text-foreground">Belum ada jadwal ujian pada periode ini.</p>
                <p class="mt-1 text-sm text-muted-foreground">Tambahkan jadwal terlebih dahulu agar pengawas dapat ditetapkan.</p>
                <Link href="/cbt/jadwal" :class="btnSecondary" class="mt-4">Buka Jadwal Ujian</Link>
            </div>

            <div v-else-if="!rows.length" class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                <i class="fas fa-shield-alt mx-auto mb-3 h-9 w-9 text-muted-foreground/50" aria-hidden="true" ></i>
                <p class="text-sm font-medium text-foreground">Belum ada ruang dan sesi untuk kelas peserta jadwal ini.</p>
                <p class="mt-1 text-sm text-muted-foreground">Tempatkan kelas ke ruang dan sesi terlebih dahulu.</p>
                <Link href="/cbt/kelas-ruang" :class="btnSecondary" class="mt-4">Atur Ruang &amp; Sesi</Link>
            </div>

            <div v-else class="overflow-x-auto rounded-xl border border-border bg-card shadow-card">
                <table class="data-table min-w-full divide-y divide-border text-sm">
                    <caption class="sr-only">Penetapan pengawas per ruang dan sesi</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="px-4 py-3">Ruang</th>
                            <th scope="col" class="px-4 py-3">Sesi</th>
                            <th scope="col" class="px-4 py-3">Peserta</th>
                            <th scope="col" class="px-4 py-3">Pengawas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="row in rows" :key="`${row.id_ruang}-${row.id_sesi}`" class="align-top hover:bg-muted/40">
                            <td class="px-4 py-3 font-medium text-foreground">{{ row.nama_ruang }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ row.nama_sesi }}
                                <span v-if="row.waktu" class="block text-xs text-muted-foreground">{{ row.waktu }}</span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ row.peserta }} siswa</td>
                            <td class="px-4 py-3">
                                <fieldset>
                                    <legend class="sr-only">Pilih pengawas untuk {{ row.nama_ruang }} {{ row.nama_sesi }}</legend>
                                    <div class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border p-2">
                                        <label
                                            v-for="g in props.guru"
                                            :key="g.id_guru"
                                            class="flex cursor-pointer items-center gap-2 rounded px-1.5 py-1 text-sm text-foreground hover:bg-muted/40 focus-within:ring-2 focus-within:ring-ring/40"
                                        >
                                            <input
 type="checkbox"
 class="h-4 w-4 rounded border-border text-primary focus:ring-ring"
 :checked="row.id_guru.includes(g.id_guru)"
 @change="toggleGuru(row, g.id_guru)"
 />
                                            <span>{{ g.nama_guru }}</span>
                                            <span v-if="g.nip" class="ml-auto text-xs text-muted-foreground">{{ g.nip }}</span>
                                        </label>
                                        <p v-if="!props.guru.length" class="px-1.5 py-1 text-sm text-muted-foreground">Belum ada data guru.</p>
                                    </div>
                                    <p class="mt-1.5 text-xs" :class="row.id_guru.length ? 'text-success' : 'text-muted-foreground'">
                                        {{ row.id_guru.length ? `${row.id_guru.length} pengawas dipilih` : 'Belum dipilih' }}
                                    </p>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
