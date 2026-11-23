// Token gaya bersama (tema Stisla): radius 3px, tombol 12-14px semibold, kartu berbayang halus.
const focusRing =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';

const baseButton =
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-xs font-semibold tracking-wide transition-colors duration-150 disabled:pointer-events-none disabled:opacity-50';

export const inputClass =
    'h-11 w-full rounded-md border border-input bg-card px-[15px] sm:h-[42px] text-sm text-foreground transition-colors placeholder:text-muted-foreground hover:border-muted-foreground/40 focus:border-primary focus:outline-none focus:ring-2 focus:ring-[#95a0f4]/25 disabled:cursor-not-allowed disabled:opacity-50';

export const labelClass = 'mb-1.5 block text-sm font-semibold text-foreground';

export const btnPrimary = `${baseButton} h-11 bg-primary sm:h-9 px-4 text-white shadow-[0_2px_6px_rgba(103,119,239,0.45)] hover:bg-primary-600 ${focusRing}`;

export const btnSecondary = `${baseButton} h-11 border sm:h-9 border-border bg-card px-4 text-foreground hover:bg-muted ${focusRing}`;

// Tombol utama selebar kartu untuk halaman masuk/reset password.
export const btnPrimaryBlock =
    'flex h-11 w-full items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-semibold text-white shadow-[0_2px_6px_rgba(103,119,239,0.45)] transition-colors duration-150 hover:bg-primary-600 disabled:cursor-not-allowed disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';

export const btnDanger =
    'inline-flex h-10 items-center justify-center gap-1.5 rounded-lg px-3 text-xs font-medium text-destructive sm:h-8 transition-colors duration-150 hover:bg-destructive/10 disabled:pointer-events-none disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-destructive/40 focus-visible:ring-offset-2';

export const btnIcon =
    'inline-flex h-11 w-11 items-center justify-center rounded-md transition-colors sm:h-8 sm:w-8 duration-150 disabled:pointer-events-none disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1';

export const btnIconDanger = `${btnIcon} text-destructive hover:bg-destructive/10`;

export const btnGhost =
    'inline-flex h-10 items-center justify-center gap-1.5 rounded-lg px-3 text-xs font-medium text-muted-foreground sm:h-8 transition-colors duration-150 hover:bg-muted hover:text-foreground disabled:pointer-events-none disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';

export const cardClass = 'rounded-xl bg-card shadow-card';

export const pageTitleClass = 'text-2xl font-bold text-foreground';

export const pageSubtitleClass = 'text-sm text-muted-foreground';

export const tableWrapClass = 'overflow-hidden rounded-xl bg-card shadow-card';

export const groupLabelClass = 'text-2xs font-semibold uppercase tracking-widest text-muted-foreground';

/**
 * Parse nilai tanggal mentah dari server ("YYYY-MM-DD", "YYYY-MM-DD HH:mm:ss",
 * atau ISO 8601) menjadi Date lokal. Mengembalikan null bila tidak terbaca.
 */
function parseDateValue(value) {
    const str = String(value ?? '').trim();
    if (!str) return null;

    const dateOnly = str.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (dateOnly) return new Date(Number(dateOnly[1]), Number(dateOnly[2]) - 1, Number(dateOnly[3]));

    const date = new Date(str.includes('T') ? str : str.replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? null : date;
}

/** Contoh keluaran: "15 November 2026". Nilai tak terbaca ditampilkan apa adanya. */
export function formatTanggal(value) {
    if (value === null || value === undefined || value === '') return '-';
    const date = parseDateValue(value);
    if (!date) return String(value);
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

/** Contoh keluaran: "14 Nov 2026, 08.07" (tanpa waktu bila hanya tanggal). */
export function formatTanggalWaktu(value) {
    if (value === null || value === undefined || value === '') return '-';
    const str = String(value).trim();
    const date = parseDateValue(str);
    if (!date) return str;
    if (/^\d{4}-\d{2}-\d{2}$/.test(str)) return formatTanggal(str);
    return date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}
