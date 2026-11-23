<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ExportLink from '@/Components/ExportLink.vue';
import { btnPrimary, btnSecondary, cardClass, inputClass, labelClass } from '@/ui';
import { computed, ref } from 'vue';
import { askAlert, askConfirm } from '@/confirm';

const props = defineProps({
    jadwalOptions: { type: Array, default: () => [] },
    selected: { type: Number, default: null },
    jadwal: { type: Object, default: null },
    peserta: { type: Array, default: () => [] },
    sesiRemedial: { type: Array, default: () => [] },
    efektivitas: { type: Object, default: null },
    periode: { type: String, default: '' },
});

const checked = ref([]);
const showForm = ref(false);

const form = useForm({
    tgl_mulai: '',
    tgl_selesai: '',
    durasi_ujian: 60,
    max_percobaan: 2,
});

const perlu = computed(() => props.peserta.filter((row) => row.perlu_remedial));
const belumDinilai = computed(() => props.peserta.filter((row) => row.nilai === null).length);

function changeJadwal(event) {
    router.get('/cbt/remedial', { jadwal: event.target.value }, { preserveState: false });
    checked.value = [];
    showForm.value = false;
}

function toggleAll() {
    checked.value = checked.value.length === perlu.value.length
        ? []
        : perlu.value.map((row) => row.id_siswa);
}

function openForm() {
    if (!checked.value.length) {
        askAlert('Pilih peserta yang akan mengikuti remedial.');

        return;
    }

    const now = new Date();
    const mulai = new Date(now.getTime() + 24 * 60 * 60 * 1000);
    const selesai = new Date(mulai.getTime() + 3 * 60 * 60 * 1000);

    form.tgl_mulai = toLocalInput(mulai);
    form.tgl_selesai = toLocalInput(selesai);
    form.durasi_ujian = Number(props.jadwal?.durasi_ujian) || 60;
    form.max_percobaan = 2;
    form.clearErrors();
    showForm.value = true;
}

function toLocalInput(date) {
    const pad = (value) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function submit() {
    if (!props.selected) return;

    form.transform((data) => ({ ...data, siswa: checked.value })).post(`/cbt/remedial/${props.selected}`, {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            checked.value = [];
        },
    });
}

async function hapus(sesi) {
    if (!await askConfirm(`Hapus jadwal remedial ${sesi.tgl_mulai}? Jawaban peserta pada sesi ini ikut terhapus.`)) {
        return;
    }

    router.delete(`/cbt/remedial/${sesi.id_jadwal}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Remedial" />
    <AdminLayout title="Remedial">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Remedial Ujian">
                    <template #description>
                        Peserta dengan nilai di bawah KKM dapat dijadwalkan ulang dengan paket soal yang sama. Periode {{ props.periode }}.
                    </template>
                </PageHeader>
                <div class="w-full max-w-sm">
                    <label class="mb-1 block text-xs font-medium text-muted-foreground" for="jadwal-ujian">Jadwal Ujian</label>
                    <select id="jadwal-ujian" :class="inputClass" :value="props.selected ?? ''" @change="changeJadwal">
                        <option v-for="item in props.jadwalOptions" :key="item.id_jadwal" :value="item.id_jadwal">
                            {{ item.label }} · {{ item.tgl_mulai }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="!props.jadwal" :class="cardClass" class="p-10 text-center">
                <i class="fas fa-rotate-left mx-auto h-10 w-10 text-muted-foreground/50" aria-hidden="true"></i>
                <p class="mt-3 text-sm font-medium text-foreground">Belum ada jadwal ujian pada periode aktif.</p>
            </div>

            <template v-else>
                <div :class="cardClass" class="flex flex-wrap items-center justify-between gap-4 p-4">
                    <div>
                        <p class="text-sm font-semibold text-foreground">{{ props.jadwal.bank_nama }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ props.jadwal.jenis }} · KKM {{ props.jadwal.kkm || 'belum diisi' }} · {{ props.jadwal.tgl_mulai }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium text-muted-foreground">{{ perlu.length }} peserta di bawah KKM</span>
                        <button type="button" :class="btnSecondary" :disabled="!perlu.length" @click="toggleAll">
                            {{ checked.length === perlu.length && perlu.length ? 'Batal pilih' : 'Pilih semua di bawah KKM' }}
                        </button>
                        <button type="button" :class="btnPrimary" :disabled="!checked.length" @click="openForm">
                            <i class="fas fa-calendar-plus h-4 w-4" aria-hidden="true"></i> Jadwalkan Remedial
                        </button>
                    </div>
                </div>

                <p v-if="props.jadwal.kkm === 0" class="rounded-lg border border-warning/40 bg-warning/10 px-4 py-2 text-sm text-warning">
                    KKM bank soal belum diisi, sehingga daftar peserta di bawah KKM tidak bisa dihitung. Isi KKM pada bank soal terlebih dahulu.
                </p>

                <div :class="cardClass" class="overflow-x-auto">
                    <table class="data-table min-w-full divide-y divide-border text-sm">
                        <thead>
                            <tr>
                                <th class="w-10 px-4 py-3"><span class="sr-only">Pilih</span></th>
                                <th class="px-4 py-3">Peserta</th>
                                <th class="px-4 py-3">Kelas</th>
                                <th class="px-4 py-3">Nilai</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Remedial</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in props.peserta" :key="row.id_siswa" class="hover:bg-muted/40">
                                <td class="px-4 py-3">
                                    <input
                                        v-model="checked"
                                        type="checkbox"
                                        :value="row.id_siswa"
                                        :disabled="!row.perlu_remedial"
                                        class="rounded border-border disabled:opacity-40"
                                        :aria-label="`Pilih ${row.nama}`"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-foreground">{{ row.nama }}</p>
                                    <p class="text-xs text-muted-foreground">{{ row.nis }}</p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ row.kelas || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="row.nilai !== null" class="font-semibold text-foreground">{{ row.nilai }}</span>
                                    <span v-else class="text-muted-foreground">-</span>
                                    <span
                                        v-if="row.dari_remedial"
                                        class="ml-1 rounded bg-primary/10 px-1.5 py-0.5 text-2xs font-semibold text-primary"
                                        :title="`Nilai terbaik dari sesi remedial; nilai ujian utama ${row.nilai_induk ?? '-'}`"
                                    >terbaik</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="row.tuntas"
                                        class="rounded-full border border-success/30 bg-success/10 px-2.5 py-1 text-xs font-medium text-success"
                                    >Tuntas</span>
                                    <span
                                        v-else-if="row.perlu_remedial"
                                        class="rounded-full border border-destructive/30 bg-destructive/10 px-2.5 py-1 text-xs font-medium text-destructive"
                                    >Perlu remedial</span>
                                    <span v-else class="text-xs text-muted-foreground">Belum dinilai</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">
                                    <span v-if="row.remedial.sesi">
                                        {{ row.remedial.percobaan }}/{{ row.remedial.max_percobaan }} percobaan
                                        <span v-if="row.remedial.nilai_terbaik !== null"> · terbaik {{ row.remedial.nilai_terbaik }}</span>
                                        <span v-if="row.remedial.aktif" class="ml-1 rounded bg-primary/10 px-1.5 py-0.5 font-medium text-primary">aktif</span>
                                    </span>
                                    <span v-else>-</span>
                                </td>
                            </tr>
                            <tr v-if="!props.peserta.length">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                    Belum ada peserta terdaftar pada jadwal ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="belumDinilai > 0" class="text-xs text-muted-foreground">
                    {{ belumDinilai }} peserta belum memiliki nilai sehingga belum bisa ditentukan remedialnya.
                </div>

                <div :class="cardClass">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-3.5">
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">Sesi Remedial</h2>
                            <p class="mt-1 text-2xs text-muted-foreground">Jadwal ulang yang sudah dibuat untuk ujian ini.</p>
                        </div>
                        <ExportLink
                            v-if="props.selected && props.sesiRemedial.length"
                            :href="`/cbt/remedial/export?jadwal=${props.selected}`"
                            label="Ekspor Excel"
                            icon="file-excel"
                        />
                    </div>
                    <div v-if="props.efektivitas" class="border-b border-border bg-muted/40 px-5 py-3 text-xs text-muted-foreground">
                        Efektivitas: {{ props.efektivitas.dinilai }} dari {{ props.efektivitas.peserta }} peserta dinilai
                        · {{ props.efektivitas.dibandingkan }} peserta dibandingkan
                        · {{ props.efektivitas.naik }} naik
                        · rata-rata {{ props.efektivitas.rata_sebelum ?? '-' }} → {{ props.efektivitas.rata_sesudah ?? '-' }}
                        <template v-if="props.efektivitas.tuntas_sesudah !== null">
                            · tuntas {{ props.efektivitas.tuntas_sebelum }} → {{ props.efektivitas.tuntas_sesudah }}
                        </template>
                    </div>
                    <div v-if="props.sesiRemedial.length" class="divide-y divide-border">
                        <div v-for="sesi in props.sesiRemedial" :key="sesi.id_jadwal" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-foreground">{{ sesi.tgl_mulai }} – {{ sesi.tgl_selesai }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ sesi.peserta }} peserta · {{ sesi.selesai }} selesai · {{ sesi.durasi_ujian }} menit
                                    <span v-if="!sesi.status" class="ml-1 text-destructive">nonaktif</span>
                                </p>
                                <p class="mt-1 text-2xs text-muted-foreground">
                                    Dinilai {{ sesi.dinilai }} · {{ sesi.dibandingkan }} dibandingkan · naik {{ sesi.naik }}
                                    · rata-rata {{ sesi.rata_sebelum ?? '-' }} → {{ sesi.rata_sesudah ?? '-' }}
                                    <template v-if="sesi.tuntas_sesudah !== null">
                                        · tuntas {{ sesi.tuntas_sebelum }} → {{ sesi.tuntas_sesudah }}
                                    </template>
                                </p>
                            </div>
                            <button type="button" :class="btnSecondary" @click="hapus(sesi)">
                                <i class="fas fa-trash-alt h-3.5 w-3.5" aria-hidden="true"></i> Hapus
                            </button>
                        </div>
                    </div>
                    <div v-else class="px-5 py-8 text-center text-sm text-muted-foreground">
                        Belum ada sesi remedial untuk ujian ini.
                    </div>
                </div>
            </template>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-foreground/60 p-4" role="dialog" aria-modal="true" aria-label="Jadwalkan remedial">
            <div class="w-full max-w-lg rounded-xl bg-card p-5 shadow-modal">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-foreground">Jadwalkan Remedial</h2>
                        <p class="mt-1 text-xs text-muted-foreground">{{ checked.length }} peserta · {{ props.jadwal?.bank_nama }}</p>
                    </div>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted" aria-label="Tutup" @click="showForm = false">
                        <i class="fas fa-times h-4 w-4" aria-hidden="true"></i>
                    </button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <label :class="labelClass" for="remedial-mulai">Mulai</label>
                        <input v-model="form.tgl_mulai" type="datetime-local" :class="inputClass" id="remedial-mulai" required />
                        <p v-if="form.errors.tgl_mulai" class="mt-1 text-xs text-destructive">{{ form.errors.tgl_mulai }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="remedial-selesai">Selesai</label>
                        <input v-model="form.tgl_selesai" type="datetime-local" :class="inputClass" id="remedial-selesai" required />
                        <p v-if="form.errors.tgl_selesai" class="mt-1 text-xs text-destructive">{{ form.errors.tgl_selesai }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label :class="labelClass" for="remedial-durasi">Durasi (menit)</label>
                            <input v-model.number="form.durasi_ujian" type="number" min="5" max="300" :class="inputClass" id="remedial-durasi" required />
                            <p v-if="form.errors.durasi_ujian" class="mt-1 text-xs text-destructive">{{ form.errors.durasi_ujian }}</p>
                        </div>
                        <div>
                            <label :class="labelClass" for="remedial-percobaan">Maks. percobaan</label>
                            <input v-model.number="form.max_percobaan" type="number" min="1" max="5" :class="inputClass" id="remedial-percobaan" required />
                            <p v-if="form.errors.max_percobaan" class="mt-1 text-xs text-destructive">{{ form.errors.max_percobaan }}</p>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-foreground">
                        <input type="checkbox" class="rounded border-border" checked disabled />
                        Gunakan paket soal yang sama dengan ujian utama
                    </label>

                    <p v-if="form.errors.siswa" class="text-xs text-destructive">{{ form.errors.siswa }}</p>

                    <div class="flex justify-end gap-2 border-t border-border pt-4">
                        <button type="button" :class="btnSecondary" @click="showForm = false">Batal</button>
                        <button type="submit" :class="btnPrimary" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan…' : 'Buat jadwal remedial' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
