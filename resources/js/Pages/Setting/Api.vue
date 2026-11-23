<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { btnPrimary, cardClass, formatTanggalWaktu } from '@/ui';
import { askConfirm } from '@/confirm';

const props = defineProps({
    setting: { type: Object, required: true },
    tokens: { type: Array, default: () => [] },
    endpoints: { type: Object, required: true },
});

const roleLabel = { admin: 'Administrator', guru: 'Guru', siswa: 'Siswa' };

const form = useForm({
    auto_sync: props.setting.auto_sync,
    edit_profile_siswa: props.setting.edit_profile_siswa,
    edit_profile_guru: props.setting.edit_profile_guru,
});

function submit() {
    form.post('/setting/api', { preserveScroll: true });
}

async function revoke(row) {
    if (await askConfirm(`Cabut token milik ${row.nama}? Klien yang memakai token ini akan langsung kehilangan akses.`)) {
        router.delete(`/setting/api/token/${row.id_api}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="API & Sinkronisasi" />
    <AdminLayout title="API & Sinkronisasi">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="API & Sinkronisasi" description="Kelola token akses aplikasi luar dan atur data apa yang boleh diubah lewat API." /></div>

            <section :class="cardClass">
                <div class="flex items-center gap-2 border-b border-border p-5">
                    <i class="fas fa-shield-alt h-4 w-4 text-primary" aria-hidden="true" ></i>
                    <h2 class="text-sm font-semibold text-foreground">Pengaturan akses</h2>
                </div>
                <form class="space-y-5 p-5" @submit.prevent="submit">
                    <div class="space-y-4">
                        <label class="flex items-start gap-3">
                            <input
 v-model="form.auto_sync"
 type="checkbox"
 class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-ring"
 />
                            <span>
                                <span class="block text-sm font-medium text-foreground">Sinkronisasi otomatis</span>
                                <span class="block text-xs text-muted-foreground">
                                    Klien diizinkan menarik data secara berkala melalui endpoint sinkronisasi. Bila dimatikan, tarik data dilakukan manual oleh admin.
                                </span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3">
                            <input
 v-model="form.edit_profile_guru"
 type="checkbox"
 class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-ring"
 />
                            <span>
                                <span class="block text-sm font-medium text-foreground">Guru boleh mengubah profil via API</span>
                                <span class="block text-xs text-muted-foreground">Nama, kontak, alamat, dan data pribadi guru dapat diperbarui lewat endpoint profil.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3">
                            <input
 v-model="form.edit_profile_siswa"
 type="checkbox"
 class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-ring"
 />
                            <span>
                                <span class="block text-sm font-medium text-foreground">Siswa boleh mengubah profil via API</span>
                                <span class="block text-xs text-muted-foreground">Siswa dapat memperbarui kontak dan alamatnya sendiri melalui aplikasi klien.</span>
                            </span>
                        </label>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" :class="btnPrimary" :disabled="form.processing">
                            <i class="fas fa-sync-alt h-4 w-4" aria-hidden="true" ></i> Simpan pengaturan
                        </button>
                    </div>
                </form>
            </section>

            <section :class="cardClass">
                <div class="flex items-center gap-2 border-b border-border p-5">
                    <i class="fas fa-key h-4 w-4 text-primary" aria-hidden="true" ></i>
                    <h2 class="text-sm font-semibold text-foreground">Endpoint API</h2>
                </div>
                <div class="space-y-3 p-5 text-sm">
                    <div class="grid gap-1 sm:grid-cols-[130px_1fr] sm:items-center">
                        <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Token</span>
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-success/10 px-2 py-0.5 text-2xs font-semibold text-success">POST</span>
                            <code class="break-all rounded bg-muted px-2 py-1 text-xs text-foreground">{{ endpoints.token }}</code>
                        </div>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[130px_1fr] sm:items-center">
                        <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Profil</span>
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-info/10 px-2 py-0.5 text-2xs font-semibold text-info">GET / PUT</span>
                            <code class="break-all rounded bg-muted px-2 py-1 text-xs text-foreground">{{ endpoints.profil }}</code>
                        </div>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[130px_1fr] sm:items-center">
                        <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Sinkronisasi</span>
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-success/10 px-2 py-0.5 text-2xs font-semibold text-success">GET</span>
                            <code class="break-all rounded bg-muted px-2 py-1 text-xs text-foreground">{{ endpoints.sync }}</code>
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Semua endpoint kecuali penerbitan token wajib menyertakan header
                        <code class="rounded bg-muted px-1.5 py-0.5">Authorization: Bearer &lt;token&gt;</code>.
                        Token disimpan dalam bentuk hash sehingga hanya ditampilkan sekali saat diterbitkan.
                    </p>
                </div>
            </section>

            <section :class="cardClass">
                <div class="flex items-center justify-between gap-2 border-b border-border p-5">
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-foreground">
                        <i class="fas fa-key h-4 w-4 text-primary" aria-hidden="true" ></i> Token aktif
                    </h2>
                    <span class="text-xs text-muted-foreground">{{ tokens.length }} token</span>
                </div>

                <div v-if="tokens.length" class="overflow-x-auto">
                    <table class="data-table w-full text-left text-sm">
                        <caption class="sr-only">Daftar token API aktif</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="px-5 py-2.5 font-medium">Pengguna</th>
                                <th scope="col" class="px-5 py-2.5 font-medium">Perangkat</th>
                                <th scope="col" class="px-5 py-2.5 font-medium">Alamat IP</th>
                                <th scope="col" class="px-5 py-2.5 font-medium">Dibuat</th>
                                <th scope="col" class="px-5 py-2.5 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in tokens" :key="row.id_api">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-foreground">{{ row.nama }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ row.username || '-' }} · {{ roleLabel[row.role] || row.role || 'Tanpa peran' }}
                                    </p>
                                </td>
                                <td class="px-5 py-3 text-muted-foreground">{{ row.device || '-' }}</td>
                                <td class="px-5 py-3 text-muted-foreground">{{ row.address || '-' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-muted-foreground">{{ formatTanggalWaktu(row.created_at) || '-' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-destructive transition hover:bg-destructive/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-destructive/30"
                                        :aria-label="`Cabut token milik ${row.nama}`"
                                        @click="revoke(row)"
                                    >
                                        <i class="fas fa-trash-alt h-3.5 w-3.5" aria-hidden="true" ></i> Cabut
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="px-5 py-10 text-center">
                    <i class="fas fa-key mx-auto h-9 w-9 text-muted-foreground/50" aria-hidden="true" ></i>
                    <p class="mt-3 text-sm font-medium text-foreground">Belum ada token aktif</p>
                    <p class="mt-1 text-xs text-muted-foreground">Token dibuat oleh aplikasi klien melalui endpoint penerbitan token.</p>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
