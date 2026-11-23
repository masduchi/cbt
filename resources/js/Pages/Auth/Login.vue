<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { btnPrimaryBlock, inputClass, labelClass } from '@/ui';
import { ref } from 'vue';

const props = defineProps({
    status: { type: String, default: '' },
});

const form = useForm({
    identity: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const identityInput = ref(null);
const passwordInput = ref(null);

const fieldClass = (key) => [
    inputClass,
    form.errors[key] ? 'border-destructive! focus:border-destructive! focus:ring-destructive/25!' : '',
];

const submit = () => {
    // Pengisian otomatis browser kadang tidak memicu event input, sehingga v-model
    // masih kosong meski kolom terlihat terisi. Nilai DOM dipakai sebagai cadangan.
    if (!form.identity && identityInput.value) {
        form.identity = identityInput.value.value;
    }

    if (!form.password && passwordInput.value) {
        form.password = passwordInput.value.value;
    }

    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Masuk" />
    <AuthLayout>
        <div
            v-if="props.status"
            role="status"
            class="mt-6 flex items-start gap-2 rounded-lg border border-success/30 bg-success/10 px-3.5 py-3 text-sm text-success"
        >
            <i class="fas fa-check-circle mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" ></i>
            <span>{{ props.status }}</span>
        </div>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
            <div>
                <label for="login-identity" :class="labelClass">Username / Email</label>
                <input
                    id="login-identity"
                    ref="identityInput"
                    v-model="form.identity"
                    name="identity"
                    type="text"
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    autofocus
                    :aria-invalid="!!form.errors.identity"
                    :aria-describedby="form.errors.identity ? 'login-identity-error' : undefined"
                    :class="fieldClass('identity')"
                    placeholder="Masukkan username atau email"
                />
                <p v-if="form.errors.identity" id="login-identity-error" role="alert" class="mt-1.5 text-sm text-destructive">
                    {{ form.errors.identity }}
                </p>
            </div>

            <div>
                <label for="login-password" :class="labelClass">Password</label>
                <div class="relative">
                    <input
                        id="login-password"
                        ref="passwordInput"
                        v-model="form.password"
                        name="password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        :aria-invalid="!!form.errors.password"
                        :aria-describedby="form.errors.password ? 'login-password-error' : undefined"
                        :class="[...fieldClass('password'), 'pr-12!']"
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
                <p v-if="form.errors.password" id="login-password-error" role="alert" class="mt-1.5 text-sm text-destructive">
                    {{ form.errors.password }}
                </p>
            </div>

            <div class="flex items-center justify-between gap-3">
                <label class="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-muted-foreground sm:min-h-0">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="h-4 w-4 rounded border-border text-primary focus:ring-ring"
                    />
                    Ingat saya
                </label>
                <Link href="/lupa-password" class="text-sm font-medium text-primary transition hover:text-primary-700">
                    Lupa password?
                </Link>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                :aria-busy="form.processing"
                :class="btnPrimaryBlock"
            >
                <i v-if="form.processing" class="fas fa-circle-notch h-4 w-4 animate-spin" aria-hidden="true" ></i>
                <i v-else class="fas fa-sign-in-alt h-4 w-4" aria-hidden="true" ></i>
                {{ form.processing ? 'Memeriksa…' : 'Masuk' }}
            </button>
        </form>

        <p class="mt-8 text-center text-xs text-muted-foreground">
            Hubungi administrator sekolah jika Anda tidak dapat masuk.
        </p>
    </AuthLayout>
</template>
