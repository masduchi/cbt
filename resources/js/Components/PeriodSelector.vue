<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const period = computed(() => page.props.period);
const isAdmin = computed(() => page.props.auth.user?.role === 'admin');
const value = computed(() => (period.value?.id_tp && period.value?.id_smt ? `${period.value.id_tp}:${period.value.id_smt}` : ''));

function change(event) {
    const [idTp, idSmt] = String(event.target.value).split(':');

    if (!idTp || !idSmt) {
        return;
    }

    router.post('/periode', { id_tp: Number(idTp), id_smt: Number(idSmt) }, { preserveScroll: true });
}

function reset() {
    router.post('/periode/reset', {}, { preserveScroll: true });
}
</script>

<template>
    <div v-if="isAdmin && period?.options?.length">
        <div class="flex items-center justify-between gap-2">
            <label for="periode-select" class="text-2xs font-semibold uppercase tracking-widest text-muted-foreground">
                Periode data
            </label>
            <button
                v-if="period.overridden"
                type="button"
                class="inline-flex items-center gap-1 rounded text-2xs font-medium text-warning transition-colors hover:text-warning/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                @click="reset"
            >
                <i class="fas fa-undo-alt h-3 w-3" aria-hidden="true" ></i> Periode aktif
            </button>
        </div>
        <select
 id="periode-select"
 :value="value"
 class="mt-1.5 h-8 w-full rounded-lg border border-input bg-card px-2.5 text-xs font-medium text-foreground shadow-card transition-colors hover:border-muted-foreground/40 focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
 @change="change"
 >
            <option v-for="option in period.options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
        <p v-if="period.overridden" class="mt-1.5 text-2xs leading-snug text-warning">
            Mode arsip. Data yang diubah tetap tersimpan pada periode ini.
        </p>
    </div>
</template>
