<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { btnPrimaryBlock, inputClass, labelClass } from '@/ui';

const props = defineProps({
    status: { type: String, default: '' },
});

const form = useForm({ email: '' });

const submit = () => {
    form.post('/lupa-password', {
        onSuccess: () => form.reset('email'),
    });
};
</script>

<template>
    <Head title="Lupa Password" />
    <AuthLayout
        title="Lupa password?"
        subtitle="Masukkan email akun Anda, kami akan mengirim tautan untuk membuat password baru."
    >
        <div
            v-if="props.status"
            role="status"
            class="mt-6 flex items-start gap-2 rounded-lg border border-success/30 bg-success/10 px-3.5 py-3 text-sm text-success"
        >
            <i class="fas fa-check-circle mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" ></i>
            <span>{{ props.status }}</span>
        </div>

        <form class="mt-6 space-y-5" @submit.prevent="submit">
            <div>
                <label for="forgot-email" :class="labelClass">Email</label>
                <div class="relative">
                    <i class="fas fa-envelope pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" ></i>
                    <input
                        id="forgot-email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        autofocus
                        :aria-invalid="!!form.errors.email"
                        :aria-describedby="form.errors.email ? 'forgot-email-error' : undefined"
                        :class="[inputClass, 'pl-10!', form.errors.email ? 'border-destructive! focus:border-destructive! focus:ring-destructive/25!' : '']"
                        placeholder="nama@sekolah.sch.id"
                    />
                </div>
                <p v-if="form.errors.email" id="forgot-email-error" role="alert" class="mt-1.5 text-sm text-destructive">
                    {{ form.errors.email }}
                </p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                :aria-busy="form.processing"
                :class="btnPrimaryBlock"
            >
                <i v-if="form.processing" class="fas fa-circle-notch h-4 w-4 animate-spin" aria-hidden="true" ></i>
                <i v-else class="fas fa-envelope h-4 w-4" aria-hidden="true" ></i>
                {{ form.processing ? 'Mengirim…' : 'Kirim tautan reset' }}
            </button>
        </form>

        <Link
            href="/login"
            class="mt-6 inline-flex items-center gap-1.5 text-sm font-medium text-primary transition hover:text-primary-700"
        >
            <i class="fas fa-arrow-left h-4 w-4" aria-hidden="true" ></i> Kembali ke halaman masuk
        </Link>
    </AuthLayout>
</template>
