import { reactive } from 'vue';

// State dialog konfirmasi global; dirender sekali oleh ConfirmDialog di AdminLayout.
export const confirmState = reactive({
    show: false,
    title: '',
    message: '',
    confirmLabel: '',
    cancelLabel: 'Batal',
    tone: 'primary',
    resolve: null,
});

const destructivePattern = /hapus|kosongkan|cabut|batalkan|lepas|pulihkan|kunci/i;

function open(options) {
    return new Promise((resolve) => {
        confirmState.show = true;
        confirmState.title = options.title ?? 'Konfirmasi';
        confirmState.message = options.message ?? '';
        confirmState.confirmLabel = options.confirmLabel ?? 'Ya, lanjutkan';
        confirmState.cancelLabel = options.cancelLabel ?? 'Batal';
        confirmState.tone = options.tone ?? 'primary';
        confirmState.resolve = resolve;
    });
}

/** Pengganti window.confirm(): mengembalikan Promise<boolean>. */
export function askConfirm(message, options = {}) {
    const text = String(message ?? '');

    return open({
        title: options.title ?? 'Konfirmasi',
        message: text,
        confirmLabel: options.confirmLabel ?? 'Ya, lanjutkan',
        cancelLabel: options.cancelLabel ?? 'Batal',
        tone: options.tone ?? (destructivePattern.test(text) ? 'danger' : 'primary'),
    });
}

/** Pengganti window.alert() untuk pesan singkat tanpa pilihan. */
export function askAlert(message, options = {}) {
    return open({
        title: options.title ?? 'Perhatian',
        message: String(message ?? ''),
        confirmLabel: options.confirmLabel ?? 'Mengerti',
        cancelLabel: null,
        tone: options.tone ?? 'primary',
    });
}

export function resolveConfirm(value) {
    const resolve = confirmState.resolve;

    confirmState.show = false;
    confirmState.resolve = null;
    resolve?.(value);
}
