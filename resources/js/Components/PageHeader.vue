<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { buildMenu } from '@/menu';
import { pageSubtitleClass, pageTitleClass } from '@/ui';

const props = defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    back: { type: Object, default: null },
});

const page = usePage();

const currentUrl = computed(() => {
    const [path] = page.url.split('?');
    return path.length > 1 ? path.replace(/\/$/, '') : path;
});

/**
 * Breadcrumb mengikuti struktur menu: grup (mis. "Data Master") lalu judul halaman.
 * Halaman detail otomatis memakai grup + judul item menu terdekat.
 */
const trail = computed(() => {
    const menu = buildMenu(page.props.auth?.user?.role);

    for (const entry of menu) {
        if (entry.items) {
            const match = entry.items
                .filter((item) => {
                    const target = typeof item.match === 'string' ? item.match : item.href;

                    return currentUrl.value === target || currentUrl.value.startsWith(`${target}/`);
                })
                .sort((a, b) => b.href.length - a.href.length)[0];

            if (match) {
                return [entry.label];
            }
        } else if (currentUrl.value === entry.href) {
            return [];
        }
    }

    return [];
});

const isHome = computed(() => currentUrl.value === '/dashboard');
</script>

<template>
    <div class="min-w-0 flex-1">
        <Link
            v-if="props.back"
            :href="props.back.href"
            class="mb-1 inline-flex items-center gap-1.5 text-xs font-semibold text-primary transition-colors hover:text-primary-600"
        >
            <i class="fas fa-chevron-right rotate-180 text-2xs" aria-hidden="true"></i>
            {{ props.back.label }}
        </Link>

        <div v-if="$slots.meta" class="mb-1.5">
            <slot name="meta" />
        </div>

        <h1 :class="pageTitleClass">
            <slot name="title">{{ props.title }}</slot>
        </h1>

        <p v-if="props.description || $slots.description" :class="[pageSubtitleClass, 'mt-1']">
            <slot name="description">{{ props.description }}</slot>
        </p>

        <nav
            v-if="!isHome"
            aria-label="Breadcrumb"
            class="mt-2 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-sm"
        >
            <Link
                href="/dashboard"
                class="shrink-0 text-primary transition-colors hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                Dashboard
            </Link>
            <template v-for="crumb in trail" :key="crumb">
                <span class="text-muted-foreground/40" aria-hidden="true">/</span>
                <span class="min-w-0 text-primary">{{ crumb }}</span>
            </template>
        </nav>
    </div>
</template>
