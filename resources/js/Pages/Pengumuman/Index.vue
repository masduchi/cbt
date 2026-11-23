<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PengumumanThread from '@/Components/PengumumanThread.vue';
import { btnPrimary, inputClass, labelClass } from '@/ui';

const props = defineProps({
    posts: { type: Array, default: () => [] },
    targets: { type: Array, default: () => [] },
    periode: { type: String, default: '' },
});

const form = useForm({ kepada: [], text: '' });

function focusComposer() {
    const field = document.getElementById('pengumuman-text');

    field?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    field?.focus();
}

function submit() {
    form.post('/pengumuman', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('text');
        },
    });
}
</script>

<template>
    <Head title="Pengumuman" />
    <AdminLayout title="Pengumuman">
        <div class="page-stack space-y-6">
            <div class="-mx-4 flex flex-col gap-4 bg-card px-6 py-5 shadow-card lg:-mx-[30px] lg:flex-row lg:items-end lg:justify-between lg:px-[30px]"><PageHeader title="Pengumuman">
                    <template #description>Kirim pengumuman ke guru, siswa, atau kelas tertentu. Periode {{ periode }}.</template>
                
                </PageHeader></div>

            <form class="space-y-4 rounded-xl border border-border bg-card p-5 shadow-card" @submit.prevent="submit">
                <div>
                    <label :class="labelClass" for="pengumuman-text">Isi pengumuman</label>
                    <textarea
 id="pengumuman-text"
 v-model="form.text"
 :class="inputClass"
 rows="4"
 maxlength="10000"
 placeholder="Tulis informasi yang perlu disampaikan"
 required
 ></textarea>
                    <p v-if="form.errors.text" role="alert" class="mt-1 text-xs font-medium text-destructive">{{ form.errors.text }}</p>
                </div>

                <fieldset>
                    <legend :class="labelClass">Tujuan</legend>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label
                            v-for="target in targets"
                            :key="target.value"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm text-foreground transition hover:border-primary/40 focus-within:ring-2 focus-within:ring-ring/30"
                            :class="form.kepada.includes(target.value) ? 'border-primary/50 bg-primary/5 text-primary' : 'border-border'"
                        >
                            <input
 v-model="form.kepada"
 type="checkbox"
 :value="target.value"
 class="h-4 w-4 rounded border-border text-primary focus:ring-ring"
 />
                            {{ target.label }}
                        </label>
                    </div>
                    <p v-if="form.errors.kepada" role="alert" class="mt-1 text-xs font-medium text-destructive">{{ form.errors.kepada }}</p>
                </fieldset>

                <div class="flex justify-end">
                    <button type="submit" :class="btnPrimary" :disabled="form.processing">
                        <i class="fas fa-paper-plane h-4 w-4" aria-hidden="true" ></i> {{ form.processing ? 'Mengirim…' : 'Kirim Pengumuman' }}
                    </button>
                </div>
            </form>

            <section aria-labelledby="daftar-pengumuman" class="space-y-4">
                <h2 id="daftar-pengumuman" class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Daftar pengumuman</h2>

                <div v-if="!props.posts.length" class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                    <i class="fas fa-bullhorn mx-auto mb-3 h-9 w-9 text-muted-foreground/50" aria-hidden="true" ></i>
                    <p class="text-sm font-medium text-foreground">Belum ada pengumuman.</p>
                    <p class="mt-1 text-sm text-muted-foreground">Gunakan formulir di atas untuk mengirim pengumuman pertama.</p>
                </div>

                <PengumumanThread @compose="focusComposer" v-else :posts="props.posts" />
            </section>
        </div>
    </AdminLayout>
</template>
