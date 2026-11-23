<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import { btnPrimary, btnSecondary, formatTanggalWaktu, inputClass, labelClass } from '@/ui';
import { ref } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    jadwal: { type: Array, default: () => [] },
    bankOptions: { type: Array, default: () => [] },
    jenisOptions: { type: Array, default: () => [] },
    isAdmin: { type: Boolean, default: false },
    periode: { type: String, default: '' },
});

const showModal = ref(false);
const editing = ref(null);

function blankForm() {
    return {
        id_bank: '',
        id_jenis: '',
        tgl_mulai: '',
        tgl_selesai: '',
        durasi_ujian: 90,
        acak_soal: false,
        acak_opsi: false,
        pilih_acak: false,
        hasil_tampil: false,
        token: false,
        status: true,
        ulang: false,
        reset_login: false,
        jam_ke: 0,
    };
}

const form = useForm(blankForm());

function openCreate() {
    editing.value = null;
    form.defaults(blankForm());
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(row) {
    editing.value = row;
    form.id_bank = row.id_bank;
    form.id_jenis = row.id_jenis;
    form.tgl_mulai = row.tgl_mulai;
    form.tgl_selesai = row.tgl_selesai;
    form.durasi_ujian = Number(row.durasi_ujian) || 0;
    form.acak_soal = row.acak_soal === 1;
    form.acak_opsi = row.acak_opsi === 1;
    form.pilih_acak = row.pilih_acak === 1;
    form.hasil_tampil = row.hasil_tampil === 1;
    form.token = row.token === 1;
    form.status = row.status === 1;
    form.ulang = row.ulang === 1;
    form.reset_login = row.reset_login === 1;
    form.jam_ke = Number(row.jam_ke) || 0;
    form.clearErrors();
    showModal.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
        },
    };

    if (editing.value) {
        form.put(`/cbt/jadwal/${editing.value.id_jadwal}`, options);
    } else {
        form.post('/cbt/jadwal', options);
    }
}

function toggleStatus(row) {
    router.post(`/cbt/jadwal/${row.id_jadwal}/status`, {}, { preserveScroll: true });
}

async function destroy(row) {
    if (await askConfirm(`Hapus jadwal ${row.bank_kode ?? ''} ${row.mapel ?? ''}?`)) {
        router.delete(`/cbt/jadwal/${row.id_jadwal}`, { preserveScroll: true });
    }
}

const selected = ref([]);

function toggleSelect(id) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((item) => item !== id)
        : [...selected.value, id];
}

function toggleAll() {
    selected.value = selected.value.length === props.jadwal.length ? [] : props.jadwal.map((row) => row.id_jadwal);
}

async function destroySelected() {
    if (
        !selected.value.length ||
        !await askConfirm(`Hapus ${selected.value.length} jadwal terpilih? Jadwal yang sudah dikerjakan dan belum direkap akan dilewati.`)
    ) {
        return;
    }

    router.post(
        '/cbt/jadwal/hapus-terpilih',
        { ids: selected.value },
        { preserveScroll: true, onSuccess: () => (selected.value = []) },
    );
}
</script>

<template>
    <Head title="Jadwal Ujian" />
    <AdminLayout title="Jadwal Ujian">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Jadwal Ujian">
                    <template #description>Atur jadwal, durasi, dan pengaturan pelaksanaan ujian. Periode {{ periode }}.</template>
                
                </PageHeader>
                <div class="flex items-center gap-2">
                    <button
                        v-if="selected.length"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-destructive px-3 py-2 text-sm font-medium text-white transition hover:bg-destructive/90"
                        @click="destroySelected"
                    >
                        <i class="fas fa-trash-alt h-4 w-4" ></i> Hapus {{ selected.length }} jadwal
                    </button>
                    <button type="button" :class="btnPrimary" @click="openCreate">
                        <i class="fas fa-plus h-4 w-4" ></i> Tambah Jadwal
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-border bg-card shadow-card">
                <table class="data-table min-w-full divide-y divide-border text-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="w-10 px-4 py-3">
                                <input
 type="checkbox"
 class="h-4 w-4 rounded border-border text-primary"
 aria-label="Pilih semua jadwal"
 :checked="selected.length > 0 && selected.length === props.jadwal.length"
                                    @change="toggleAll"
                                />
                            </th>
                            <th class="px-4 py-3">Ujian</th>
                            <th class="px-4 py-3">Jenis</th>
                            <th class="px-4 py-3">Pelaksanaan</th>
                            <th class="px-4 py-3">Pengaturan</th>
                            <th class="px-4 py-3">Peserta</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="!props.jadwal.length">
                            <td colspan="8" class="px-4 py-12 text-center text-muted-foreground">
                                <i class="fas fa-clock mx-auto mb-2 h-8 w-8 text-muted-foreground/50" ></i>
                                Belum ada jadwal ujian.
                            </td>
                        </tr>
                        <tr v-for="row in props.jadwal" :key="row.id_jadwal" class="hover:bg-muted/40">
                            <td class="px-4 py-3">
                                <input
 type="checkbox"
 class="h-4 w-4 rounded border-border text-primary"
 :aria-label="`Pilih jadwal ${row.bank_nama}`"
 :checked="selected.includes(row.id_jadwal)"
 @change="toggleSelect(row.id_jadwal)" />
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-foreground">{{ row.mapel ?? 'Tanpa mapel' }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.bank_kode }} · {{ row.bank_nama }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">{{ row.jenis ?? '-' }}</span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div>{{ formatTanggalWaktu(row.tgl_mulai) }}</div>
                                <div class="text-xs text-muted-foreground">s.d. {{ formatTanggalWaktu(row.tgl_selesai) }} · {{ row.durasi_ujian }} menit</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span v-if="row.acak_soal" class="rounded bg-primary/10 px-1.5 py-0.5 text-xs text-primary">Acak soal</span>
                                    <span v-if="row.acak_opsi" class="rounded bg-primary/10 px-1.5 py-0.5 text-xs text-primary">Acak opsi</span>
                                    <span v-if="row.pilih_acak" class="rounded bg-primary/10 px-1.5 py-0.5 text-xs text-primary">Soal acak dari kumpulan</span>
                                    <span v-if="row.token" class="rounded bg-warning/10 px-1.5 py-0.5 text-xs text-warning">Token</span>
                                    <span v-if="row.hasil_tampil" class="rounded bg-success/10 px-1.5 py-0.5 text-xs text-success">Nilai tampil</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ row.peserta_count }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="row.status === 1 ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground'"
                                >
                                    {{ row.status === 1 ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/cbt/kehadiran?jadwal=${row.id_jadwal}`" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-primary/10 hover:text-primary" title="Kehadiran peserta">
                                        <i class="fas fa-qrcode h-4 w-4" ></i>
                                    </Link>
                                    <Link :href="`/cbt/jadwal/${row.id_jadwal}/kartu`" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-primary/10 hover:text-primary" title="Kartu peserta">
                                        <i class="fas fa-print h-4 w-4" ></i>
                                    </Link>
                                    <Link :href="`/cbt/jadwal/${row.id_jadwal}/hadir`" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-primary/10 hover:text-primary" title="Daftar hadir">
                                        <i class="fas fa-user-check h-4 w-4" ></i>
                                    </Link>
                                    <Link :href="`/cbt/jadwal/${row.id_jadwal}/berita-acara`" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-primary/10 hover:text-primary" title="Berita acara">
                                        <i class="fas fa-file-signature h-4 w-4" ></i>
                                    </Link>
                                    <button aria-label="Aktif/nonaktif" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Aktif/nonaktif" @click="toggleStatus(row)">
                                        <i class="fas fa-power-off h-4 w-4" ></i>
                                    </button>
                                    <button aria-label="Edit" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Edit" @click="openEdit(row)">
                                        <i class="fas fa-pencil-alt h-4 w-4" ></i>
                                    </button>
                                    <button aria-label="Hapus" type="button" class="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1" title="Hapus" @click="destroy(row)">
                                        <i class="fas fa-trash-alt h-4 w-4" ></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="showModal" :title="editing ? 'Edit Jadwal Ujian' : 'Tambah Jadwal Ujian'" max-width="max-w-2xl" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label :class="labelClass" for="bank-soal">Bank Soal</label>
                        <select v-model="form.id_bank" :class="inputClass" id="bank-soal">
                            <option value="">Pilih bank soal</option>
                            <option v-for="bank in props.bankOptions" :key="bank.id_bank" :value="bank.id_bank">
                                {{ bank.bank_kode }} · {{ bank.bank_nama }}{{ bank.mapel ? ' (' + bank.mapel + ')' : '' }}{{ bank.ready ? '' : ' · belum siap' }}
                            </option>
                        </select>
                        <p v-if="form.errors.id_bank" class="mt-1 text-xs text-destructive">{{ form.errors.id_bank }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="jenis-ujian">Jenis Ujian</label>
                        <select v-model="form.id_jenis" :class="inputClass" id="jenis-ujian">
                            <option value="">Pilih jenis</option>
                            <option v-for="jenis in props.jenisOptions" :key="jenis.id_jenis" :value="jenis.id_jenis">{{ jenis.nama_jenis }}</option>
                        </select>
                        <p v-if="form.errors.id_jenis" class="mt-1 text-xs text-destructive">{{ form.errors.id_jenis }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="durasi-menit">Durasi (menit)</label>
                        <input v-model.number="form.durasi_ujian" type="number" min="1" :class="inputClass" id="durasi-menit" />
                        <p v-if="form.errors.durasi_ujian" class="mt-1 text-xs text-destructive">{{ form.errors.durasi_ujian }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="mulai">Mulai</label>
                        <input v-model="form.tgl_mulai" type="datetime-local" :class="inputClass" id="mulai" />
                        <p v-if="form.errors.tgl_mulai" class="mt-1 text-xs text-destructive">{{ form.errors.tgl_mulai }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="selesai">Selesai</label>
                        <input v-model="form.tgl_selesai" type="datetime-local" :class="inputClass" id="selesai" />
                        <p v-if="form.errors.tgl_selesai" class="mt-1 text-xs text-destructive">{{ form.errors.tgl_selesai }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="jam-ke">Jam ke-</label>
                        <input v-model.number="form.jam_ke" type="number" min="0" :class="inputClass" id="jam-ke" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm text-foreground">
                        <input v-model="form.acak_soal" type="checkbox" class="h-4 w-4 rounded border-border text-primary" /> Acak urutan soal
                    </label>
                    <label class="flex items-center gap-2 text-sm text-foreground">
                        <input v-model="form.acak_opsi" type="checkbox" class="h-4 w-4 rounded border-border text-primary" /> Acak opsi jawaban
                    </label>
                    <label class="flex items-center gap-2 text-sm text-foreground sm:col-span-2">
                        <input v-model="form.pilih_acak" type="checkbox" class="h-4 w-4 rounded border-border text-primary" /> Ambil soal acak dari seluruh kumpulan (bila kumpulan lebih banyak dari jumlah tampil)
                    </label>
                    <label class="flex items-center gap-2 text-sm text-foreground">
                        <input v-model="form.token" type="checkbox" class="h-4 w-4 rounded border-border text-primary" /> Gunakan token
                    </label>
                    <label class="flex items-center gap-2 text-sm text-foreground">
                        <input v-model="form.hasil_tampil" type="checkbox" class="h-4 w-4 rounded border-border text-primary" /> Tampilkan nilai ke siswa
                    </label>
                    <label class="flex items-center gap-2 text-sm text-foreground">
                        <input v-model="form.ulang" type="checkbox" class="h-4 w-4 rounded border-border text-primary" /> Izinkan mengulang
                    </label>
                    <label class="flex items-center gap-2 text-sm text-foreground">
                        <input v-model="form.reset_login" type="checkbox" class="h-4 w-4 rounded border-border text-primary" /> Reset login
                    </label>
                    <label class="flex items-center gap-2 text-sm text-foreground">
                        <input v-model="form.status" type="checkbox" class="h-4 w-4 rounded border-border text-primary" /> Jadwal aktif
                    </label>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" :class="btnSecondary" @click="showModal = false">Batal</button>
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">Simpan</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
