<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { sanitizeHtml } from '@/sanitize';

const props = defineProps({
    html: { type: String, default: '' },
});

const el = ref(null);
const clean = computed(() => sanitizeHtml(props.html ?? ''));

let renderMathInElement = null;

async function loadMathRenderer() {
    if (!renderMathInElement) {
        const [module] = await Promise.all([
            import('katex/contrib/auto-render'),
            import('katex/dist/katex.min.css'),
        ]);

        renderMathInElement = module.default;
    }

    return renderMathInElement;
}

async function renderMath() {
    const render = await loadMathRenderer();

    if (!el.value) {
        return;
    }

    render(el.value, {
        delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '\\[', right: '\\]', display: true },
            { left: '\\(', right: '\\)', display: false },
        ],
        throwOnError: false,
        ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code', 'option'],
    });
}

watch(clean, () => nextTick(renderMath));
onMounted(renderMath);
</script>

<template>
    <div ref="el" v-html="clean" />
</template>
