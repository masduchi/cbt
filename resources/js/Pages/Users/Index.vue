<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import { btnIcon, btnIconDanger, btnPrimary, btnSecondary, cardClass, inputClass, labelClass } from '@/ui';
import { ref, watch } from 'vue';
import { askConfirm } from '@/confirm';

const props = defineProps({
    role: { type: String, required: true },
    roleLabel: { type: String, required: true },
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
    counts: { type: Object, default: () => ({}) },
    siswaOptions: { type: Array, default: () => [] },
});

const roleTabs = [
    { role: 'admin', label: 'Administrator', icon: 'user-shield' },
    { role: 'guru', label: 'Guru', icon: 'user-tie' },
    { role: 'siswa', label: 'Siswa', icon: 'user-graduate' },
    { role: 'orangtua', label: 'Wali Murid', icon: 'user-friends' },
];

const search = ref(props.filters.search ?? '');
let searchTimer = null;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(`/users/${props.role}`, { search: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

const showModal = ref(false);
const editing = ref(null);

const form = useForm({
    username: '',
    nama: '',
    email: '',
    password: '',
    active: true,
    siswa_ids: [],
});

function blank() {
    return { username: '', nama: '', email: '', password: '', active: true, siswa_ids: [] };
}

function openCreate() {
    editing.value = null;
    form.defaults(blank());
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(row) {
    editing.value = row;
    form.username = row.username;
    form.nama = row.nama;
    form.email = row.email ?? '';
    form.password = '';
    form.active = row.active;
    form.siswa_ids = (row.siswa ?? []).map((item) => item.id_siswa);
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
        form.put(`/users/${props.role}/${editing.value.id}`, options);
    } else {
        form.post(`/users/${props.role}`, options);
    }
}

function toggleActive(row) {
    router.put(
        `/users/${props.role}/${row.id}`,
        { username: row.username, nama: row.nama, email: row.email, active: !row.active },
        { preserveScroll: true },
    );
}

async function destroy(row) {
    if (await askConfirm(`Hapus akun ${row.username}?`)) {
        router.delete(`/users/${props.role}/${row.id}`, { preserveScroll: true });
    }
}

const selected = ref([]);

function toggleSelect(id) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((item) => item !== id)
        : [...selected.value, id];
}

function toggleAll() {
    selected.value =
        selected.value.length === props.users.data.length ? [] : props.users.data.map((row) => row.id);
}

function bulkStatus(active) {
    if (!selected.value.length) return;

    router.post(
        `/users/${props.role}/status`,
        { ids: selected.value, active },
        { preserveScroll: true, onSuccess: () => (selected.value = []) },
    );
}

function formatLogin(value) {
    if (!value) return '-';
    return new Date(value * 1000).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}
</script>

<template>
    <Head title="Akun Pengguna" />
    <AdminLayout title="Akun Pengguna">
        <div class="page-stack space-y-5">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]">
                <PageHeader title="Akun Pengguna">
                    <template #description>
                        Kelola akun login administrator, guru, dan siswa dalam satu halaman.
                        <span v-if="props.role === 'siswa'">Akun siswa biasanya dibuat otomatis dari data master siswa.</span>
                    </template>
                </PageHeader>
                <div class="flex flex-wrap items-center gap-2">
                    <template v-if="selected.length">
                        <span class="text-xs font-medium text-muted-foreground">{{ selected.length }} dipilih</span>
                        <button type="button" :class="btnSecondary" @click="bulkStatus(true)">Aktifkan</button>
                        <button type="button" :class="btnDanger" @click="bulkStatus(false)">Nonaktifkan</button>
                    </template>
                    <button type="button" :class="btnPrimary" @click="openCreate">
                        <i class="fas fa-plus h-4 w-4" ></i> Tambah Akun
                    </button>
                </div>
            </div>

            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <nav class="flex flex-wrap items-center gap-2" aria-label="Pilih peran akun">
                    <Link
                        v-for="tab in roleTabs"
                        :key="tab.role"
                        :href="`/users/${tab.role}`"
                        preserve-scroll
                        class="inline-flex h-11 items-center gap-2 rounded border px-3.5 text-sm font-semibold transition-colors sm:h-9 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        :class="props.role === tab.role
                            ? 'border-primary bg-primary text-white'
                            : 'border-border bg-card text-muted-foreground hover:text-foreground'"
                        :aria-current="props.role === tab.role ? 'page' : undefined"
                    >
                        <i :class="'fas fa-' + tab.icon" class="text-xs" aria-hidden="true"></i>
                        {{ tab.label }}
                        <span
                            class="rounded-full px-1.5 text-2xs font-semibold"
                            :class="props.role === tab.role ? 'bg-white/20 text-white' : 'bg-muted text-muted-foreground'"
                        >{{ props.counts?.[tab.role] ?? 0 }}</span>
                    </Link>
                </nav>

                <div class="relative w-full max-w-xs">
                    <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" ></i>
                    <input v-model="search" :class="[inputClass, 'pl-9']" placeholder="Cari nama, username, email…" aria-label="Cari akun" />
                </div>
            </div>

            <div :class="cardClass" class="overflow-x-auto">
                <table class="data-table min-w-full divide-y divide-border text-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="w-10 px-4 py-3">
                                <span class="sr-only">Pilih</span>
                                <input
 type="checkbox"
 class="h-4 w-4 rounded border-border text-primary"
 aria-label="Pilih semua akun"
 :checked="selected.length > 0 && selected.length === props.users.data.length"
                                    @change="toggleAll"
                                />
                            </th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Username</th>
                            <th v-if="props.role === 'orangtua'" class="px-4 py-3">Siswa Terhubung</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Login Terakhir</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="row in props.users.data" :key="row.id" class="hover:bg-muted/40">
                            <td class="px-4 py-3">
                                <input
 type="checkbox"
 class="h-4 w-4 rounded border-border text-primary"
 :aria-label="`Pilih akun ${row.username}`"
 :checked="selected.includes(row.id)"
 @change="toggleSelect(row.id)" />
                            </td>
                            <td class="px-4 py-3 font-medium text-foreground">{{ row.nama }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ row.username }}</td>
                            <td v-if="props.role === 'orangtua'" class="px-4 py-3 text-xs text-muted-foreground">
                                <span v-if="row.siswa?.length">{{ row.siswa.map((item) => item.nama).join(', ') }}</span>
                                <span v-else class="text-warning">Belum ditautkan</span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ row.email || '-' }}</td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ formatLogin(row.last_login) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-medium"
                                    :class="row.active ? 'border-success/30 bg-success/10 text-success' : 'border-border bg-muted text-muted-foreground'">
                                    {{ row.active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" :class="btnIcon" class="text-warning hover:bg-warning/10" :title="row.active ? 'Nonaktifkan' : 'Aktifkan'" :aria-label="row.active ? 'Nonaktifkan akun' : 'Aktifkan akun'" @click="toggleActive(row)">
                                        <i class="fas fa-power-off h-3.5 w-3.5" ></i>
                                    </button>
                                    <button type="button" :class="btnIcon" class="text-primary hover:bg-primary/10" title="Ubah akun" aria-label="Ubah akun" @click="openEdit(row)">
                                        <i class="fas fa-pencil-alt h-3.5 w-3.5" ></i>
                                    </button>
                                    <button type="button" :class="btnIconDanger" title="Hapus akun" aria-label="Hapus akun" @click="destroy(row)">
                                        <i class="fas fa-trash-alt h-3.5 w-3.5" ></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!props.users.data.length">
                            <td :colspan="props.role === 'orangtua' ? 8 : 7" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                <i class="fas fa-users mx-auto mb-2 h-8 w-8 text-muted-foreground/50" ></i>
                                <p>{{ props.filters.search ? `Tidak ada akun yang cocok dengan "${props.filters.search}".` : `Belum ada akun ${props.roleLabel.toLowerCase()}.` }}</p>
                                <button v-if="!props.filters.search" type="button" :class="[btnPrimary, 'mt-4']" @click="openCreate">
                                    <i class="fas fa-plus h-4 w-4" aria-hidden="true"></i> Tambah Akun
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="props.users" />
        </div>

        <Modal :show="showModal" max-width="lg" @close="showModal = false">
            <form class="space-y-4 p-5" @submit.prevent="submit">
                <h2 class="text-base font-semibold text-foreground">
                    {{ editing ? 'Ubah Akun' : 'Tambah Akun' }} {{ props.roleLabel }}
                </h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label :class="labelClass" for="nama-lengkap">Nama Lengkap</label>
                        <input v-model="form.nama" type="text" :class="inputClass" required id="nama-lengkap" />
                        <p v-if="form.errors.nama" class="mt-1 text-xs text-destructive">{{ form.errors.nama }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="username">Username</label>
                        <input v-model="form.username" type="text" :class="inputClass" required id="username" />
                        <p v-if="form.errors.username" class="mt-1 text-xs text-destructive">{{ form.errors.username }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="email">Email</label>
                        <input v-model="form.email" type="email" :class="inputClass" id="email" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-destructive">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label :class="labelClass">{{ editing ? 'Password Baru (opsional)' : 'Password' }}</label>
                        <input v-model="form.password" type="password" :class="inputClass" :required="!editing" autocomplete="new-password" aria-label="Password baru" />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-destructive">{{ form.errors.password }}</p>
                    </div>
                </div>

                <div v-if="props.role === 'orangtua'">
                    <label :class="labelClass" for="siswa-wali">Siswa yang dipantau</label>
                    <select v-model="form.siswa_ids" multiple :class="inputClass" class="h-32" id="siswa-wali">
                        <option v-for="siswa in props.siswaOptions" :key="siswa.id_siswa" :value="siswa.id_siswa">{{ siswa.label }}</option>
                    </select>
                    <p class="mt-1 text-xs text-muted-foreground">Tekan Ctrl/Cmd untuk memilih lebih dari satu siswa.</p>
                    <p v-if="form.errors.siswa_ids" class="mt-1 text-xs text-destructive">{{ form.errors.siswa_ids }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-foreground">
                    <input v-model="form.active" type="checkbox" class="rounded border-border" /> Akun aktif
                </label>

                <div class="flex justify-end gap-2 border-t border-border pt-4">
                    <button type="button" :class="btnSecondary" @click="showModal = false">Batal</button>
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
