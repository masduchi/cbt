<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import GlobalSearch from '@/Components/GlobalSearch.vue';
import PeriodSelector from '@/Components/PeriodSelector.vue';
import { buildMenu } from '@/menu';

const page = usePage();
const props = defineProps({ title: { type: String, default: 'Beranda' } });
const user = computed(() => page.props.auth.user);
const menu = computed(() => buildMenu(user.value?.role));
const currentUrl = computed(() => page.url);

const SECTIONS_KEY = 'cbt.sidebar.sections';

const sidebarOpen = ref(false);
const collapsed = ref(localStorage.getItem('cbt.sidebar.collapsed') === '1');
const desktopQuery = window.matchMedia('(min-width: 1024px)');
const isDesktop = ref(desktopQuery.matches);
const isMini = computed(() => isDesktop.value && collapsed.value);
const sidebarClose = ref(null);
const menuButton = ref(null);

const routeLoading = ref(false);
const hoverCard = ref(null);
const shortcutsOpen = ref(false);
const darkTheme = ref(false);
const successDismissed = ref(false);
const warningDismissed = ref(false);
const errorDismissed = ref(false);
let successTimer = null;

// Setiap kunjungan Inertia membawa objek flash baru. Reset visibilitas toast
// berdasarkan objek tersebut agar pesan yang sama tetap muncul pada aksi berikutnya.
watch(
    () => page.props.flash,
    (flash) => {
        clearTimeout(successTimer);

        successDismissed.value = !flash?.success;
        warningDismissed.value = !flash?.warning;
        errorDismissed.value = !flash?.error;

        if (flash?.success) {
            successTimer = setTimeout(() => {
                successDismissed.value = true;
            }, 5000);
        }
    },
    { immediate: true, deep: true },
);

const userMenuOpen = ref(false);
const userMenuRoot = ref(null);
const userMenuButton = ref(null);

const roleLabel = { admin: 'Administrator', guru: 'Guru', siswa: 'Siswa', orangtua: 'Wali Murid' };

const periodLabel = computed(() => {
    const period = page.props.period;

    return period?.tahun ? [period.tahun, period.smt].filter(Boolean).join(' · ') : '';
});

const appTitle = computed(() => page.props.school?.nama_aplikasi || page.props.appName || 'CBT Sekolah');
const schoolName = computed(() => page.props.school?.sekolah || '');
const schoolLogo = computed(() => page.props.school?.logo || null);

// Aktif hanya untuk path yang sama persis atau sub-path (dengan pemisah '/'),
// sehingga '/ujian/hasil' tidak ikut menyalakan menu '/ujian'.
// Bila beberapa menu cocok, yang paling spesifik (href terpanjang) yang menang.
const activeHref = computed(() => {
    const path = currentUrl.value.split('?')[0].split('#')[0];
    let best = null;

    for (const entry of menu.value) {
        for (const item of entry.items ?? [entry]) {
            const href = item.href;
            if (typeof href !== 'string' || !href) continue;

            const target = typeof item.match === 'string' ? item.match : href;

            if (path === target || path.startsWith(`${target}/`)) {
                if (best === null || target.length > best.length) best = href;
            }
        }
    }

    return best;
});

const isActive = (href) => href === activeHref.value;

/** Section header menyala bila ada submenu di dalamnya yang sedang aktif. */
const sectionHasActive = (section) =>
    section.items.some((item) => isActive(item.href) || item.items?.some((sub) => isActive(sub.href)));

const navSections = computed(() => {
    const plain = menu.value.filter((entry) => !entry.items);
    const groups = menu.value
        .filter((entry) => entry.items)
        .map((entry) => ({ label: entry.label, icon: entry.icon, items: entry.items }));

    return plain.length ? [{ label: 'Umum', icon: 'home', items: plain }, ...groups] : groups;
});

const savedSections = (() => {
    try {
        const value = JSON.parse(localStorage.getItem(SECTIONS_KEY) ?? 'null');
        return Array.isArray(value) ? value : null;
    } catch {
        return null;
    }
})();

const openSections = ref(savedSections ?? navSections.value.map((section) => section.label));

const isSectionOpen = (label) => openSections.value.includes(label);

// Section aktif ikut terbuka saat pengguna pindah halaman (mis. lewat pencarian global).
watch(
    [navSections, activeHref],
    ([sections]) => {
        const activeSection = sections.find((section) => sectionHasActive(section));

        if (activeSection && !openSections.value.includes(activeSection.label)) {
            openSections.value = [...openSections.value, activeSection.label];
        }
    },
    { immediate: true },
);

const actionButton =
    'flex h-10 w-10 items-center justify-center rounded-md text-white/90 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80';

function showTooltip(event, entry) {
    if (!isMini.value) {
        return;
    }

    const rect = event.currentTarget.getBoundingClientRect();
    hoverCard.value = {
        label: entry.label,
        items: entry.items?.map((item) => item.label) ?? [],
        top: Math.min(rect.top, window.innerHeight - 220),
    };
}

function hideTooltip() {
    hoverCard.value = null;
}

function applyTheme() {
    document.documentElement.classList.toggle('dark', darkTheme.value);
    localStorage.setItem('cbt.theme', darkTheme.value ? 'dark' : 'light');
}

function toggleTheme() {
    darkTheme.value = !darkTheme.value;
    applyTheme();
}

function toggleSidebar() {
    if (window.matchMedia('(min-width: 1024px)').matches) {
        toggleCollapse();
    } else {
        openSidebar();
    }
}

function toggleCollapse() {
    collapsed.value = !collapsed.value;
    localStorage.setItem('cbt.sidebar.collapsed', collapsed.value ? '1' : '0');
}

/**
 * ID elemen tidak boleh mengandung spasi, sedangkan label menu berbahasa
 * Indonesia sering memakainya ("Data Master"). Slug dipakai bersama oleh
 * tombol (aria-controls) dan panelnya (id) agar tetap terhubung.
 */
function sectionId(label) {
    return `nav-section-${label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')}`;
}

function toggleSection(label) {
    const next = openSections.value.includes(label)
        ? openSections.value.filter((section) => section !== label)
        : [...openSections.value, label];

    openSections.value = next;
    localStorage.setItem(SECTIONS_KEY, JSON.stringify(next));
}

function openSidebar() {
    sidebarOpen.value = true;
    nextTick(() => sidebarClose.value?.focus());
}

function closeSidebar() {
    sidebarOpen.value = false;
    nextTick(() => menuButton.value?.focus());
}

function onKeydown(event) {
    const target = event.target;
    const typing = target instanceof HTMLElement
        && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

    if (event.key === '?' && !typing) {
        event.preventDefault();
        shortcutsOpen.value = true;

        return;
    }

    if (event.key !== 'Escape') {
        return;
    }

    shortcutsOpen.value = false;

    if (userMenuOpen.value) {
        userMenuOpen.value = false;
        userMenuButton.value?.focus();
    }

    if (sidebarOpen.value) {
        closeSidebar();
    }
}

function onPointerDown(event) {
    if (userMenuOpen.value && !userMenuRoot.value?.contains(event.target)) {
        userMenuOpen.value = false;
    }
}

const onDesktopChange = (event) => {
    isDesktop.value = event.matches;
};

const syncDesktop = () => {
    isDesktop.value = window.matchMedia('(min-width: 1024px)').matches;
};

onMounted(() => {
    const savedTheme = localStorage.getItem('cbt.theme');
    darkTheme.value = savedTheme ? savedTheme === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme();

    window.addEventListener('keydown', onKeydown);
    document.addEventListener('pointerdown', onPointerDown);
    desktopQuery.addEventListener('change', onDesktopChange);
    window.addEventListener('resize', syncDesktop);
    router.on('start', () => {
        routeLoading.value = true;
        hideTooltip();
    });
    router.on('finish', () => {
        routeLoading.value = false;
    });
});

onBeforeUnmount(() => {
    clearTimeout(successTimer);
    window.removeEventListener('keydown', onKeydown);
    document.removeEventListener('pointerdown', onPointerDown);
    desktopQuery.removeEventListener('change', onDesktopChange);
    window.removeEventListener('resize', syncDesktop);
});

watch(currentUrl, () => {
    sidebarOpen.value = false;
    userMenuOpen.value = false;
});
</script>

<template>
    <div class="min-h-screen bg-background">
        <div
            v-if="routeLoading"
            class="route-progress fixed inset-x-0 top-0 z-[70] h-[2px] bg-primary/15"
            role="progressbar"
            aria-label="Memuat halaman"
        >
            <span class="route-progress__bar block h-full bg-primary"></span>
        </div>

        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-primary focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white"
        >
            Lewati ke konten utama
        </a>

        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-foreground/40 lg:hidden"
            aria-hidden="true"
            @click="closeSidebar"
        />

        <aside
            id="app-sidebar"
            aria-label="Menu aplikasi"
            class="app-sidebar fixed inset-y-0 left-0 z-50 flex flex-col border-r border-sidebar-border bg-sidebar transition-[width,transform] duration-200 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            :style="{ '--sidebar-width': isMini ? '60px' : '250px' }"
        >
            <div
                class="flex h-[60px] shrink-0 items-center gap-2 border-b border-sidebar-border"
                :class="isMini ? 'justify-center px-2' : 'justify-between px-4'"
            >
                <Link
                    href="/dashboard"
                    :aria-label="`${appTitle} - ${schoolName}`"
                    class="flex min-w-0 items-center gap-2.5"
                >
                    <img
                        v-if="schoolLogo"
                        :src="schoolLogo"
                        alt="Logo sekolah"
                        class="h-8 w-8 shrink-0 object-contain"
                    />
                    <span
                        v-else
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-primary/10 text-primary"
                        aria-hidden="true"
                    >
                        <i class="fas fa-graduation-cap text-sm"></i>
                    </span>

                    <span v-if="!isMini" class="min-w-0 leading-tight">
                        <span class="block truncate text-[13px] font-bold uppercase tracking-[0.08em] text-foreground">
                            {{ appTitle }}
                        </span>
                        <span v-if="schoolName" class="block truncate text-2xs font-medium text-sidebar-foreground">
                            {{ schoolName }}
                        </span>
                    </span>
                </Link>

                <button
                    ref="sidebarClose"
                    type="button"
                    class="shrink-0 rounded-md p-2 text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring lg:hidden"
                    aria-label="Tutup menu navigasi"
                    @click="closeSidebar"
                >
                    <i class="fas fa-times text-sm" aria-hidden="true"></i>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto py-4" aria-label="Navigasi utama">
                <div>
                    <div v-for="(section, sectionIndex) in navSections" :key="section.label">
                        <div
                            v-if="isMini && sectionIndex > 0"
                            class="mx-3 my-1 border-t border-sidebar-border"
                            aria-hidden="true"
                        ></div>
                        <button
                            v-if="!isMini"
                            type="button"
                            class="relative mt-4 flex w-full items-center gap-2 py-2 pl-4 pr-3 text-left text-xs font-bold uppercase tracking-[0.12em] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            :class="sectionHasActive(section) ? 'text-primary' : 'text-muted-foreground hover:text-foreground'"
                            :aria-expanded="isSectionOpen(section.label)"
                            :aria-controls="sectionId(section.label)"
                            :title="`${isSectionOpen(section.label) ? 'Sembunyikan' : 'Tampilkan'} ${section.label}`"
                            @click="toggleSection(section.label)"
                        >
                            <span
                                v-if="sectionHasActive(section)"
                                class="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-primary"
                                aria-hidden="true"
                            ></span>
                            <span class="flex-1 truncate">{{ section.label }}</span>
                            <i
                                class="fas fa-chevron-down h-3 w-3 shrink-0 transition-transform duration-200"
                                :class="[!isSectionOpen(section.label) && '-rotate-90', sectionHasActive(section) ? 'text-primary' : 'text-muted-foreground']"
                                aria-hidden="true"
                            ></i>
                        </button>
                        <div
                            v-show="isMini || isSectionOpen(section.label)"
                            :id="sectionId(section.label)"
                            class="mt-1.5 space-y-0.5"
                        >
                            <template v-for="entry in section.items" :key="entry.label">
                                <Link
                                    v-if="!entry.items"
                                    :href="entry.href"
                                    :title="isMini ? entry.label : undefined"
                                    :aria-label="isMini ? entry.label : undefined"
                                    :aria-current="isActive(entry.href) ? 'page' : undefined"
                                    class="flex h-[50px] w-full items-center px-5 text-sm font-normal transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                                    @mouseenter="showTooltip($event, entry)"
                                    @mouseleave="hideTooltip"
                                    @focus="showTooltip($event, entry)"
                                    @blur="hideTooltip"
                                    :class="[
                                        isActive(entry.href)
                                            ? 'bg-sidebar-accent font-semibold text-primary'
                                            : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-foreground',
                                        isMini && 'justify-center px-2',
                                    ]"
                                >
                                    <span class="flex w-7 shrink-0 justify-center" :class="!isMini && 'mr-5'"><i :class="'fas fa-' + entry.icon" class="text-[15px]" aria-hidden="true"></i></span>
                                    <span v-if="!isMini" class="flex-1 truncate">{{ entry.label }}</span>
                                </Link>

                                <Link
                                    v-else
                                    :href="entry.items?.[0]?.href"
                                    :title="isMini ? entry.label : undefined"
                                    :aria-label="isMini ? entry.label : undefined"
                                    class="flex h-[50px] w-full items-center text-sm font-normal text-sidebar-foreground transition-colors duration-150 hover:bg-sidebar-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    :class="isMini ? 'justify-center px-2' : 'px-5'"
                                    @mouseenter="showTooltip($event, entry)"
                                    @mouseleave="hideTooltip"
                                    @focus="showTooltip($event, entry)"
                                    @blur="hideTooltip"
                                >
                                    <span class="flex w-7 shrink-0 justify-center" :class="!isMini && 'mr-5'"><i :class="'fas fa-' + entry.icon" class="text-[15px]" aria-hidden="true"></i></span>
                                    <span v-if="!isMini" class="flex-1 truncate">{{ entry.label }}</span>
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>
            </nav>

            <div v-if="user?.role === 'admin' && !isMini" class="border-t border-sidebar-border p-3">
                <PeriodSelector />
            </div>
        </aside>

        <div class="app-main transition-[padding] duration-200" :style="{ '--sidebar-width': isMini ? '60px' : '250px' }">
            <header
                class="sticky top-0 z-30 flex h-[60px] items-center bg-primary px-4 shadow-[0_2px_6px_rgba(103,119,239,0.35)] lg:px-5"
            >
                <div class="flex w-full items-center gap-3">
                    <button
                        ref="menuButton"
                        type="button"
                        :class="actionButton"
                        aria-label="Buka atau sembunyikan menu navigasi"
                        aria-controls="app-sidebar"
                        @click="toggleSidebar"
                    >
                        <i class="fas fa-bars text-lg" aria-hidden="true"></i>
                    </button>

                    <GlobalSearch />

                    <span
                        v-if="periodLabel"
                        class="ml-2 hidden shrink-0 items-center gap-2 rounded bg-white/15 px-3 py-1.5 text-xs font-semibold text-white lg:inline-flex"
                        :title="`Periode aktif: ${periodLabel}${page.props.period?.overridden ? ' (mode arsip)' : ''}`"
                    >
                        <i class="fas fa-calendar-alt text-2xs" aria-hidden="true"></i>
                        {{ periodLabel }}
                        <span
                            v-if="page.props.period?.overridden"
                            class="rounded bg-amber-400 px-1.5 py-0.5 text-2xs font-bold uppercase tracking-wide text-slate-900"
                        >Arsip</span>
                    </span>

                    <div class="min-w-0 flex-1 lg:hidden">
                        <p class="truncate text-sm font-semibold text-white">{{ props.title }}</p>
                        <p v-if="page.props.period?.tahun" class="truncate text-2xs text-white">
                            {{ page.props.period.tahun }} · {{ page.props.period.smt }}
                        </p>
                    </div>

                    <div class="hidden min-w-0 flex-1 lg:block"></div>

                    <div class="flex items-center gap-2">
                        <ConfirmDialog />

                        <button
                            type="button"
                            class="flex h-10 w-10 items-center justify-center rounded-md text-white/90 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80"
                            :aria-pressed="darkTheme"
                            :aria-label="darkTheme ? 'Aktifkan tema terang' : 'Aktifkan tema gelap'"
                            :title="darkTheme ? 'Tema terang' : 'Tema gelap'"
                            @click="toggleTheme"
                        >
                            <i :class="darkTheme ? 'fas fa-sun' : 'fas fa-moon'" class="text-base" aria-hidden="true"></i>
                        </button>

                        <Link
                            v-if="user?.role !== 'orangtua'"
                            href="/pengumuman"
                            class="relative flex h-10 w-10 items-center justify-center rounded-md text-white/90 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80"
                            aria-label="Pengumuman"
                            title="Pengumuman"
                        >
                            <i class="fas fa-bell text-lg" aria-hidden="true"></i>
                            <span class="absolute right-2 top-2 h-[7px] w-[7px] rounded-full bg-warning" aria-hidden="true"></span>
                        </Link>

                        <div ref="userMenuRoot" class="relative">
                            <button
                                ref="userMenuButton"
                                type="button"
                                class="flex h-10 items-center gap-2 rounded-md px-1 transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80 sm:px-1.5"
                                aria-haspopup="menu"
                                :aria-expanded="userMenuOpen"
                                aria-label="Buka menu pengguna"
                                @click="userMenuOpen = !userMenuOpen"
                            >
                                <span
                                    class="flex h-[30px] w-[30px] items-center justify-center rounded-full bg-white text-xs font-bold text-primary-700"
                                >
                                    {{ (user?.name || '?').charAt(0).toUpperCase() }}
                                </span>
                                <span class="hidden text-left md:block">
                                    <span class="block max-w-[9rem] truncate text-xs font-semibold leading-none text-white">
                                        Hi, {{ user?.name }}
                                    </span>
                                    <span class="hidden">
                                        {{ roleLabel[user?.role] }}
                                    </span>
                                </span>
                                <i class="fas fa-chevron-down hidden text-2xs text-white/70 transition-transform duration-200 sm:block" :class="{ 'rotate-180': userMenuOpen }" aria-hidden="true"></i>
                            </button>

                            <div
                                v-if="userMenuOpen"
                                role="menu"
                                aria-label="Menu pengguna"
                                class="absolute right-0 top-full z-50 mt-2 w-56 rounded-xl border border-border bg-card p-1.5 shadow-dropdown"
                            >
                                <div class="border-b border-border px-2.5 pb-2.5 pt-1.5">
                                    <p class="truncate text-sm font-semibold text-foreground">{{ user?.name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">{{ user?.username }}</p>
                                </div>
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="mt-1 flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-sm text-foreground transition-colors hover:bg-muted focus-visible:bg-muted focus-visible:outline-none"
                                    @click="userMenuOpen = false; shortcutsOpen = true"
                                >
                                    <i class="fas fa-keyboard text-xs text-muted-foreground" aria-hidden="true"></i>
                                    Pintasan keyboard
                                    <kbd class="ml-auto rounded border border-border bg-muted px-1.5 text-2xs font-semibold text-muted-foreground">?</kbd>
                                </button>
                                <Link
                                    href="/profil"
                                    role="menuitem"
                                    class="mt-1 flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-sm text-foreground transition-colors hover:bg-muted focus-visible:bg-muted focus-visible:outline-none"
                                >
                                    <i class="fas fa-user text-xs text-muted-foreground" aria-hidden="true"></i>
                                    Profil & Password
                                </Link>
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    role="menuitem"
                                    class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-sm text-destructive transition-colors hover:bg-destructive/10 focus-visible:bg-destructive/10 focus-visible:outline-none"
                                >
                                    <i class="fas fa-sign-out-alt text-xs" aria-hidden="true"></i>
                                    Keluar
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div
                v-if="page.props.runningText?.length"
                class="running-text border-b border-warning/30 bg-warning/10 text-warning"
                aria-label="Teks berjalan"
            >
                <div class="running-text__track py-1.5 text-xs font-medium">
                    <span v-for="(text, index) in page.props.runningText" :key="index">{{ text }}</span>
                </div>
            </div>

            <main id="main-content" tabindex="-1" class="px-4 pb-6 focus:outline-none lg:px-[30px] lg:pb-8">
                <slot />
            </main>

            <footer class="px-4 pb-8 pt-2 text-center text-xs text-muted-foreground lg:px-[30px] lg:text-left">
                Copyright &copy; {{ new Date().getFullYear() }}
                <span class="font-semibold text-foreground">{{ page.props.appName }}</span>
            </footer>
        </div>


        <div class="pointer-events-none fixed inset-x-4 top-[68px] z-[75] flex flex-col items-end gap-2 sm:inset-x-auto sm:right-4">
            <div
                v-if="page.props.flash?.success && !successDismissed"
                role="status"
                aria-live="polite"
                class="toast-item pointer-events-auto flex w-full max-w-sm items-start justify-between gap-3 rounded border border-success bg-success px-4 py-3 text-sm font-semibold text-white shadow-dropdown dark:border-success/40 dark:bg-success/15 dark:text-success"
            >
                <span>{{ page.props.flash.success }}</span>
                <button
                    type="button"
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-current opacity-80 transition hover:bg-white/20 hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current"
                    aria-label="Tutup pesan"
                    @click="successDismissed = true"
                >
                    <i class="fas fa-times text-xs" aria-hidden="true"></i>
                </button>
            </div>

            <div
                v-if="page.props.flash?.warning && !warningDismissed"
                role="status"
                aria-live="polite"
                class="toast-item pointer-events-auto flex w-full max-w-sm items-start justify-between gap-3 rounded border border-warning bg-warning px-4 py-3 text-sm font-semibold text-white shadow-dropdown dark:border-warning/40 dark:bg-warning/15 dark:text-warning"
            >
                <span>{{ page.props.flash.warning }}</span>
                <button
                    type="button"
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-current opacity-80 transition hover:bg-white/20 hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current"
                    aria-label="Tutup pesan"
                    @click="warningDismissed = true"
                >
                    <i class="fas fa-times text-xs" aria-hidden="true"></i>
                </button>
            </div>

            <div
                v-if="page.props.flash?.error && !errorDismissed"
                role="alert"
                class="toast-item pointer-events-auto flex w-full max-w-sm items-start justify-between gap-3 rounded border border-destructive bg-destructive px-4 py-3 text-sm font-semibold text-white shadow-dropdown dark:border-destructive/40 dark:bg-destructive/15 dark:text-destructive"
            >
                <span>{{ page.props.flash.error }}</span>
                <button
                    type="button"
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-current opacity-80 transition hover:bg-white/20 hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current"
                    aria-label="Tutup pesan"
                    @click="errorDismissed = true"
                >
                    <i class="fas fa-times text-xs" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div
            v-if="shortcutsOpen"
            class="fixed inset-0 z-[80] flex items-start justify-center p-4 pt-[15vh]"
            role="dialog"
            aria-modal="true"
            aria-label="Pintasan papan tombol"
        >
            <div class="fixed inset-0 bg-foreground/40" aria-hidden="true" @click="shortcutsOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg border border-border bg-card p-5 shadow-modal">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-base font-bold text-foreground">Pintasan papan tombol</h2>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        aria-label="Tutup pintasan"
                        @click="shortcutsOpen = false"
                    >
                        <i class="fas fa-times text-sm" aria-hidden="true"></i>
                    </button>
                </div>
                <dl class="space-y-2 text-sm">
                    <div v-for="shortcut in [{ keys: 'Ctrl + K', label: 'Buka pencarian global' }, { keys: '?', label: 'Tampilkan daftar pintasan' }, { keys: 'Esc', label: 'Tutup dialog atau menu' }]" :key="shortcut.keys" class="flex items-center justify-between gap-3">
                        <dt class="text-muted-foreground">{{ shortcut.label }}</dt>
                        <dd><kbd class="rounded border border-border bg-muted px-2 py-0.5 text-xs font-semibold text-foreground">{{ shortcut.keys }}</kbd></dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs text-muted-foreground">Pintasan tidak aktif saat kursor berada di kolom isian.</p>
            </div>
        </div>

        <div
            v-if="hoverCard"
            class="pointer-events-none fixed z-[60] max-w-56 min-w-40 rounded-md border border-border bg-card p-1.5 shadow-dropdown"
            :style="{ left: '68px', top: hoverCard.top + 'px' }"
            role="tooltip"
        >
            <p class="truncate px-2 py-1 text-xs font-semibold text-foreground">{{ hoverCard.label }}</p>
            <ul v-if="hoverCard.items.length" class="mt-1 space-y-0.5 border-t border-border pt-1">
                <li
                    v-for="item in hoverCard.items"
                    :key="item"
                    class="truncate px-2 py-0.5 text-2xs text-muted-foreground"
                >
                    {{ item }}
                </li>
            </ul>
        </div>
    </div>
</template>
