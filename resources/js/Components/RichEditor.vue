<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import { sanitizeHtml } from '@/sanitize';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Tulis di sini…' },
    minHeight: { type: String, default: '120px' },
    id: { type: String, default: 'rich-editor' },
});

const emit = defineEmits(['update:modelValue']);

const el = ref(null);
const fileInput = ref(null);
const uploading = ref(false);
const uploadError = ref('');
const mathOpen = ref(false);
const mathTex = ref('');
const mathDisplay = ref(false);
const savedRange = ref(null);

onMounted(() => {
    if (el.value) el.value.innerHTML = props.modelValue || '';
});

watch(
    () => props.modelValue,
    (value) => {
        const html = value || '';
        if (el.value && el.value.innerHTML !== html) {
            el.value.innerHTML = html;
        }
    },
);

function emitValue() {
    emit('update:modelValue', el.value?.innerHTML ?? '');
}

function selectionInside() {
    const selection = window.getSelection();
    if (!selection || !selection.rangeCount || !el.value) return false;

    return el.value.contains(selection.anchorNode);
}

function rememberSelection() {
    if (!selectionInside()) return;
    savedRange.value = window.getSelection().getRangeAt(0).cloneRange();
}

function restoreSelection() {
    el.value?.focus();
    if (!savedRange.value) return;

    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(savedRange.value);
}

function exec(command, value = null) {
    el.value?.focus();
    document.execCommand(command, false, value);
    emitValue();
}

function insertText(text) {
    restoreSelection();
    document.execCommand('insertText', false, text);
    emitValue();
}

function toggleFormula() {
    rememberSelection();
    mathOpen.value = !mathOpen.value;
    if (mathOpen.value) {
        mathTex.value = '';
        nextTick(() => document.getElementById(`${props.id}-math`)?.focus());
    }
}

function insertFormula() {
    const tex = mathTex.value.trim();
    if (!tex) return;

    const wrapped = mathDisplay.value ? `\\[${tex}\\]` : `\\(${tex}\\)`;
    insertText(wrapped);
    mathOpen.value = false;
    mathTex.value = '';
}

function insertImage(url) {
    restoreSelection();
    document.execCommand('insertImage', false, url);
    emitValue();
}

async function upload(file) {
    if (!file || !file.type.startsWith('image/')) return;

    uploading.value = true;
    uploadError.value = '';

    try {
        const data = new FormData();
        data.append('image', file);

        const response = await fetch('/cbt/media', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
            body: data,
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message ?? 'Gagal mengunggah gambar.');
        }

        const payload = await response.json();
        insertImage(payload.url);
    } catch (error) {
        uploadError.value = error.message ?? 'Gagal mengunggah gambar.';
    } finally {
        uploading.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}

function pickImage(event) {
    upload(event.target.files?.[0]);
}

async function onPaste(event) {
    const items = Array.from(event.clipboardData?.items ?? []);
    const image = items.find((item) => item.kind === 'file' && item.type.startsWith('image/'));

    if (image) {
        event.preventDefault();
        rememberSelection();
        upload(image.getAsFile());
        return;
    }

    const html = event.clipboardData?.getData('text/html');
    if (html) {
        event.preventDefault();
        restoreSelection();
        document.execCommand('insertHTML', false, sanitizeHtml(html));
        emitValue();
    }
}

function onToolbarMouseDown() {
    rememberSelection();
}
</script>

<template>
    <div class="rounded-lg border border-border bg-card shadow-card transition focus-within:border-primary/30 focus-within:ring-2 focus-within:ring-primary/30">
        <div class="flex flex-wrap items-center gap-1 border-b border-border px-2 py-1.5" role="toolbar" aria-label="Format teks">
            <button
                type="button"
                class="toolbar-btn"
                title="Tebal"
                aria-label="Tebal"
                @mousedown.prevent="onToolbarMouseDown"
                @click="exec('bold')"
            >
                <i class="fas fa-bold h-4 w-4" ></i>
            </button>
            <button
                type="button"
                class="toolbar-btn"
                title="Miring"
                aria-label="Miring"
                @mousedown.prevent="onToolbarMouseDown"
                @click="exec('italic')"
            >
                <i class="fas fa-italic h-4 w-4" ></i>
            </button>
            <button
                type="button"
                class="toolbar-btn"
                title="Garis bawah"
                aria-label="Garis bawah"
                @mousedown.prevent="onToolbarMouseDown"
                @click="exec('underline')"
            >
                <i class="fas fa-underline h-4 w-4" ></i>
            </button>
            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>
            <button
                type="button"
                class="toolbar-btn"
                title="Daftar berpoin"
                aria-label="Daftar berpoin"
                @mousedown.prevent="onToolbarMouseDown"
                @click="exec('insertUnorderedList')"
            >
                <i class="fas fa-list h-4 w-4" ></i>
            </button>
            <button
                type="button"
                class="toolbar-btn"
                title="Daftar bernomor"
                aria-label="Daftar bernomor"
                @mousedown.prevent="onToolbarMouseDown"
                @click="exec('insertOrderedList')"
            >
                <i class="fas fa-list-ol h-4 w-4" ></i>
            </button>
            <button
                type="button"
                class="toolbar-btn"
                title="Pangkat"
                aria-label="Pangkat"
                @mousedown.prevent="onToolbarMouseDown"
                @click="exec('superscript')"
            >
                <i class="fas fa-superscript h-4 w-4" ></i>
            </button>
            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>
            <button
                type="button"
                class="toolbar-btn"
                :class="{ 'bg-primary/10 text-primary': mathOpen }"
                title="Sisipkan rumus"
                aria-label="Sisipkan rumus"
                :aria-expanded="mathOpen"
                @mousedown.prevent="onToolbarMouseDown"
                @click="toggleFormula"
            >
                <i class="fas fa-sigma h-4 w-4" ></i>
            </button>
            <button
                type="button"
                class="toolbar-btn"
                title="Sisipkan gambar"
                aria-label="Sisipkan gambar"
                :disabled="uploading"
                @mousedown.prevent="onToolbarMouseDown"
                @click="fileInput?.click()"
            >
                <i v-if="uploading" class="fas fa-spinner h-4 w-4 animate-spin" ></i>
                <i v-else class="fas fa-image h-4 w-4" ></i>
            </button>
            <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="pickImage" aria-label="Unggah gambar" />
        </div>

        <div v-if="mathOpen" class="flex flex-wrap items-end gap-2 border-b border-border bg-muted/50 px-2 py-2">
            <div class="min-w-0 flex-1">
                <label :for="`${id}-math`" class="mb-1 block text-xs font-medium text-muted-foreground">Rumus LaTeX</label>
                <input
 :id="`${id}-math`"
 v-model="mathTex"
 type="text"
 class="w-full rounded-lg border border-border px-2.5 py-1.5 font-mono text-sm outline-none focus:border-primary/30 focus:ring-2 focus:ring-primary/30"
 placeholder="mis. \frac{a}{b} atau x^2 + 3x"
 @keydown.enter.prevent="insertFormula"
 />
            </div>
            <label class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                <input v-model="mathDisplay" type="checkbox" class="rounded border-border" />
                Blok
            </label>
            <button type="button" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary" @click="insertFormula">
                Sisipkan
            </button>
            <button type="button" class="rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-medium text-muted-foreground hover:bg-muted/50" @click="mathOpen = false">
                Tutup
            </button>
        </div>

        <div
            :id="id"
            ref="el"
            class="editor-body px-3 py-2 text-sm leading-relaxed text-foreground outline-none"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            :data-placeholder="placeholder"
            :style="{ minHeight }"
            @input="emitValue"
            @paste="onPaste"
            @keyup="rememberSelection"
            @mouseup="rememberSelection"
        ></div>

        <p v-if="uploadError" class="border-t border-destructive/15 bg-destructive/10 px-3 py-1.5 text-xs text-destructive">{{ uploadError }}</p>
    </div>
</template>

<style scoped>
.toolbar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    padding: 0.375rem;
    color: var(--color-muted-foreground);
    transition: background-color 0.15s, color 0.15s;
}

.toolbar-btn:hover:not(:disabled) {
    background-color: var(--color-muted);
    color: var(--color-foreground);
}

.toolbar-btn:focus-visible {
    outline: 2px solid var(--color-ring);
    outline-offset: 1px;
}

.toolbar-btn:disabled {
    opacity: 0.5;
}

.editor-body:empty::before {
    content: attr(data-placeholder);
    color: var(--color-muted-foreground);
    pointer-events: none;
}

.editor-body :deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: 0.5rem;
}
</style>
