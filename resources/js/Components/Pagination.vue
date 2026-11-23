<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    paginator: { type: Object, default: null },
});

function pageUrl(page) {
    const base = props.paginator?.first_page_url ?? props.paginator?.path ?? '';

    try {
        const url = new URL(base, window.location.origin);
        url.searchParams.set('page', String(page));
        return url.pathname + url.search;
    } catch {
        return `${props.paginator?.path ?? ''}?page=${page}`;
    }
}

const perPage = computed(() => props.paginator?.per_page ?? 25);
const showPerPage = computed(() => (props.paginator?.total ?? 0) > 25 || perPage.value !== 25);

function changePerPage(event) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', event.target.value);
    url.searchParams.delete('page');

    router.get(`${url.pathname}${url.search}`, {}, { preserveScroll: true, preserveState: true });
}

const pages = computed(() => {
    if (!props.paginator) return [];
    const current = props.paginator.current_page;
    const last = props.paginator.last_page;
    const list = [];
    for (let i = 1; i <= last; i++) {
        if (i === 1 || i === last || Math.abs(i - current) <= 2) {
            list.push(i);
        } else if (list[list.length - 1] !== '...') {
            list.push('...');
        }
    }
    return list;
});
</script>

<template>
    <nav
        v-if="paginator && (paginator.last_page > 1 || showPerPage)"
        aria-label="Navigasi halaman"
        class="flex flex-col items-center justify-between gap-3 sm:flex-row"
    >
        <p class="text-[13px] text-muted-foreground">
            Menampilkan {{ paginator.from ?? 0 }}–{{ paginator.to ?? 0 }} dari {{ paginator.total }} data
        </p>
        <div class="flex flex-wrap items-center gap-1.5">
            <label v-if="showPerPage" class="mr-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                Baris
                <select
                    :value="perPage"
                    class="h-8 rounded-md border border-input bg-card px-2 text-xs font-semibold text-foreground transition-colors hover:border-muted-foreground/40 focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
                    aria-label="Jumlah baris per halaman"
                    @change="changePerPage"
                >
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                    <option :value="100">100</option>
                </select>
            </label>
            <span class="sr-only">Halaman {{ paginator.current_page }} dari {{ paginator.last_page }}</span>
            <Link
                v-if="paginator.prev_page_url"
                :href="paginator.prev_page_url"
                rel="prev"
                aria-label="Halaman sebelumnya"
                preserve-scroll
                class="inline-flex h-11 items-center rounded-md bg-muted px-3 sm:h-9 text-[13px] font-semibold text-foreground transition-colors hover:bg-primary hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                Sebelumnya
            </Link>
            <template v-for="(page, index) in pages" :key="index">
                <span v-if="page === '...'" aria-hidden="true" class="px-1.5 text-[13px] text-muted-foreground">…</span>
                <Link
                    v-else
                    :href="pageUrl(page)"
                    :aria-label="`Halaman ${page}`"
                    :aria-current="page === paginator.current_page ? 'page' : undefined"
                    preserve-scroll
                    class="inline-flex h-11 min-w-11 items-center justify-center rounded-md px-2.5 sm:h-9 sm:min-w-9 text-[13px] font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    :class="page === paginator.current_page
                        ? 'bg-primary text-white'
                        : 'bg-muted text-foreground hover:bg-primary hover:text-white'"
                >
                    {{ page }}
                </Link>
            </template>
            <Link
                v-if="paginator.next_page_url"
                :href="paginator.next_page_url"
                rel="next"
                aria-label="Halaman berikutnya"
                preserve-scroll
                class="inline-flex h-11 items-center rounded-md bg-muted px-3 sm:h-9 text-[13px] font-semibold text-foreground transition-colors hover:bg-primary hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                Berikutnya
            </Link>
        </div>
    </nav>
</template>
