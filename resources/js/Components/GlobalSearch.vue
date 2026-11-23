<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const page = usePage();
const canSearch = computed(() => ['admin', 'guru'].includes(page.props.auth.user?.role));

const open = ref(false);
const query = ref('');
const groups = ref([]);
const loading = ref(false);
const activeIndex = ref(0);
const inputEl = ref(null);
const listEl = ref(null);

let timer = null;
let controller = null;
let sequence = 0;

const rows = computed(() => {
    const output = [];
    let index = 0;

    for (const group of groups.value) {
        output.push({ type: 'label', label: group.label, key: `label-${group.label}` });
        for (const item of group.items) {
            output.push({ type: 'item', ...item, index, key: `item-${index}` });
            index++;
        }
    }

    return output;
});

const total = computed(() => rows.value.filter((row) => row.type === 'item').length);
const activeItem = computed(() => rows.value.find((row) => row.type === 'item' && row.index === activeIndex.value) ?? null);

function openSearch() {
    open.value = true;
    nextTick(() => inputEl.value?.focus());
}

function close() {
    open.value = false;
    query.value = '';
    groups.value = [];
    activeIndex.value = 0;
    loading.value = false;
    sequence++;
    controller?.abort();
}

watch(open, (visible) => {
    document.body.style.overflow = visible ? 'hidden' : '';
});

watch(query, (value) => {
    clearTimeout(timer);
    controller?.abort();
    sequence++;

    const term = value.trim();

    if (term.length < 2) {
        groups.value = [];
        loading.value = false;
        activeIndex.value = 0;
        return;
    }

    loading.value = true;
    timer = setTimeout(() => fetchResults(term), 250);
});

async function fetchResults(term) {
    const current = ++sequence;
    controller = new AbortController();

    try {
        const response = await fetch(`/search?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error('Pencarian gagal.');
        }

        const data = await response.json();
        if (current !== sequence) return;

        groups.value = data.groups ?? [];
        activeIndex.value = 0;
    } catch (error) {
        if (error.name !== 'AbortError' && current === sequence) {
            groups.value = [];
        }
    } finally {
        if (current === sequence) {
            loading.value = false;
        }
    }
}

function go(item) {
    if (!item?.href) return;
    close();
    router.visit(item.href);
}

function move(step) {
    if (!total.value) return;

    activeIndex.value = (activeIndex.value + step + total.value) % total.value;
    nextTick(() => listEl.value?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' }));
}

function onInputKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
    } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        move(1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(-1);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        go(activeItem.value);
    }
}

function onShortcut(event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        open.value ? close() : openSearch();
    }
}

onMounted(() => window.addEventListener('keydown', onShortcut));

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onShortcut);
    document.body.style.overflow = '';
    clearTimeout(timer);
    controller?.abort();
});
</script>

<template>
    <button
        v-if="canSearch"
        type="button"
        class="ml-1 hidden h-10 w-[220px] items-center rounded bg-card pl-4 pr-3 text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80 lg:flex xl:w-[320px]"
        aria-label="Buka pencarian global"
        aria-haspopup="dialog"
        :aria-expanded="open"
        @click="openSearch"
    >
        <span class="flex-1 truncate text-left">Cari</span>
        <kbd class="hidden shrink-0 rounded border border-input bg-muted px-1.5 py-0.5 text-2xs font-semibold text-muted-foreground xl:inline">Ctrl K</kbd>
        <i class="fas fa-search ml-2 text-[13px] text-foreground" aria-hidden="true"></i>
    </button>

    <button
        v-if="canSearch"
        type="button"
        class="ml-1 flex h-10 w-10 items-center justify-center rounded-md text-white/90 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80 lg:hidden"
        aria-label="Buka pencarian global"
        aria-haspopup="dialog"
        :aria-expanded="open"
        @click="openSearch"
    >
        <i class="fas fa-search text-base" aria-hidden="true"></i>
    </button>

    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-[10vh]"
            role="dialog"
            aria-modal="true"
            aria-label="Pencarian global"
        >
            <div class="fixed inset-0 bg-foreground/40" aria-hidden="true" @click="close" />

            <div class="relative w-full max-w-xl overflow-hidden rounded-xl border border-border bg-card shadow-dropdown">
                <div class="flex items-center gap-3 border-b border-border px-4">
                    <i class="fas fa-search shrink-0 text-sm text-muted-foreground"></i>
                    <input
 ref="inputEl"
 v-model="query"
 type="search"
 class="h-12 w-full bg-transparent text-sm text-foreground outline-none placeholder:text-muted-foreground"
 placeholder="Cari siswa, guru, mapel, bank soal, kelas…"
 aria-label="Kata kunci pencarian"
 autocomplete="off"
 @keydown="onInputKeydown"
 />
                    <i v-if="loading" class="fas fa-spinner shrink-0 animate-spin text-sm text-primary"></i>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-muted-foreground transition hover:bg-muted hover:text-muted-foreground"
                        aria-label="Tutup pencarian"
                        @click="close"
                    >
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <div ref="listEl" class="max-h-[min(60vh,420px)] overflow-y-auto p-2">
                    <template v-for="row in rows" :key="row.key">
                        <p
                            v-if="row.type === 'label'"
                            class="px-3 pb-1 pt-3 text-2xs font-semibold uppercase tracking-wide text-muted-foreground"
                        >
                            {{ row.label }}
                        </p>
                        <button
                            v-else
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition"
                            :class="row.index === activeIndex ? 'bg-primary/10 text-primary' : 'text-foreground hover:bg-muted/50'"
                            :data-active="row.index === activeIndex"
                            @mouseenter="activeIndex = row.index"
                            @click="go(row)"
                        >
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ row.title }}</span>
                                <span class="block truncate text-xs text-muted-foreground">{{ row.subtitle }}</span>
                            </span>
                            <i v-if="row.index === activeIndex" class="fas fa-reply shrink-0 rotate-180 text-2xs text-primary"></i>
                        </button>
                    </template>

                    <div v-if="query.trim().length < 2" class="px-3 py-10 text-center text-sm text-muted-foreground">
                        Ketik minimal 2 karakter untuk mencari siswa, guru, mapel, bank soal, dan kelas.
                    </div>
                    <div v-else-if="!loading && !total" class="px-3 py-10 text-center text-sm text-muted-foreground">
                        Tidak ada hasil untuk “{{ query.trim() }}”.
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-border px-4 py-2.5 text-2xs text-muted-foreground">
                    <span>↑↓ pilih · Enter buka · Esc tutup</span>
                    <span role="status" aria-live="polite">{{ total }} hasil</span>
                </div>
            </div>
        </div>
    </Teleport>
</template>
