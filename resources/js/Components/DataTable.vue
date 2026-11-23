<script setup>
const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: 'id' },
    empty: { type: String, default: 'Belum ada data.' },
    emptyIcon: { type: [Object, String], default: null },
    stickyLast: { type: Boolean, default: true },
});

/**
 * Kolom aksi dibuat menempel di kanan agar tombol tetap terjangkau saat tabel digulir.
 */
const isSticky = (column) => props.stickyLast && (column.sticky === true || /aksi|action/i.test(String(column.key ?? '')));
</script>

<template>
    <div class="min-w-0 overflow-hidden rounded-xl bg-card shadow-card">
        <div class="max-h-[70vh] overflow-auto">
            <table class="data-table w-full text-sm">
                <thead>
                    <tr>
                        <th
                            v-for="c in columns"
                            :key="c.key"
                            scope="col"
                            class="px-4 py-3"
                            :class="[c.class, isSticky(c) && 'sticky right-0 z-10 bg-accent shadow-[-6px_0_10px_-6px_rgba(0,0,0,0.12)]']"
                        >
                            {{ c.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="(row, index) in rows" :key="row[rowKey] ?? index" class="transition-colors hover:bg-accent">
                        <td
                            v-for="c in columns"
                            :key="c.key"
                            class="px-4 py-4 align-middle"
                            :class="[c.class, isSticky(c) && 'sticky right-0 z-10 bg-card shadow-[-6px_0_10px_-6px_rgba(0,0,0,0.12)]']"
                        >
                            <slot :name="`cell-${c.key}`" :row="row" :index="index">
                                {{ row[c.key] ?? '-' }}
                            </slot>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td :colspan="columns.length" class="px-6 py-14 text-center">
                            <i
                                v-if="typeof emptyIcon === 'string'"
                                :class="'fas fa-' + emptyIcon"
                                class="mx-auto block text-4xl text-muted-foreground/30"
                                aria-hidden="true"
                            ></i>
                            <component
                                :is="emptyIcon"
                                v-else-if="emptyIcon"
                                class="mx-auto h-8 w-8 text-muted-foreground/50"
                            />
                            <p class="mt-2 text-sm text-muted-foreground">{{ empty }}</p>
                            <div v-if="$slots['empty-action']" class="mt-4 flex justify-center">
                                <slot name="empty-action" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="sr-only">Geser tabel ke samping untuk melihat kolom lainnya.</p>
    </div>
</template>
