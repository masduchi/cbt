<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { btnPrimaryBlock, inputClass, labelClass } from '@/ui';
import { ref } from 'vue';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Reset Password" />
    <AuthLayout
        title="Buat password baru"
        subtitle="Masukkan password baru untuk akun Anda, minimal 8 karakter."
    >
        <form class="mt-6 space-y-5" @submit.prevent="submit">
            <div>
                <label for="reset-email" :class="labelClass">Email</label>
                <input
                    id="reset-email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    :aria-invalid="!!form.errors.email"
                    :aria-describedby="form.errors.email ? 'reset-email-error' : undefined"
                    :class="[inputClass, form.errors.email ? 'border-destructive! focus:border-destructive! focus:ring-destructive/25!' : '']"
                    placeholder="nama@sekolah.sch.id"
                />
                <p v-if="form.errors.email" id="reset-email-error" role="alert" class="mt-1.5 text-sm text-destructive">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <label for="reset-password" :class="labelClass">Password baru</label>
                <div class="relative">
                    <input
                        id="reset-password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="new-password"
                        :aria-invalid="!!form.errors.password"
                        :aria-describedby="form.errors.password ? 'reset-password-error' : undefined"
                        :class="[inputClass, 'pr-12!', form.errors.password ? 'border-destructive! focus:border-destructive! focus:ring-destructive/25!' : '']"
                        placeholder="Masukkan password"
                    />
                    <button
                        type="button"
                        :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                        :aria-pressed="showPassword"
                        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-md text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
                        @click="showPassword = !showPassword"
                    >
                        <i v-if="showPassword" class="fas fa-eye-slash h-4 w-4" aria-hidden="true" ></i>
                        <i v-else class="fas fa-eye h-4 w-4" aria-hidden="true" ></i>
                    </button>
                </div>
                <p v-if="form.errors.password" id="reset-password-error" role="alert" class="mt-1.5 text-sm text-destructive">
                    {{ form.errors.password }}
                </p>
            </div>

            <div>
                <label for="reset-password-confirmation" :class="labelClass">Ulangi password baru</label>
                <input
                    id="reset-password-confirmation"
                    v-model="form.password_confirmation"
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="new-password"
                    :class="inputClass"
                    placeholder="Ulangi password"
                />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                :aria-busy="form.processing"
                :class="btnPrimaryBlock"
            >
                <i v-if="form.processing" class="fas fa-circle-notch h-4 w-4 animate-spin" aria-hidden="true" ></i>
                <i v-else class="fas fa-key h-4 w-4" aria-hidden="true" ></i>
                {{ form.processing ? 'Menyimpan…' : 'Simpan password baru' }}
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
