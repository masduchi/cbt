import fs from 'node:fs';
import path from 'node:path';

const root = path.join(process.cwd(), 'resources/js/Pages');

function walk(dir) {
    return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const full = path.join(dir, entry.name);
        return entry.isDirectory() ? walk(full) : [full];
    });
}

const tokenMap = [
    ['hover:bg-slate-50/60', 'hover:bg-muted/40'],
    ['hover:bg-slate-50', 'hover:bg-muted/40'],
    ['bg-slate-50', 'bg-muted/60'],
    ['bg-slate-100', 'bg-muted'],
    ['bg-slate-200', 'bg-muted'],
    ['bg-white', 'bg-card'],
    ['text-slate-900', 'text-foreground'],
    ['text-slate-800', 'text-foreground'],
    ['text-slate-700', 'text-foreground'],
    ['text-slate-600', 'text-muted-foreground'],
    ['text-slate-500', 'text-muted-foreground'],
    ['text-slate-400', 'text-muted-foreground'],
    ['text-slate-300', 'text-muted-foreground/50'],
    ['border-slate-200', 'border-border'],
    ['border-slate-100', 'border-border'],
    ['border-slate-300', 'border-border'],
    ['divide-slate-200', 'divide-border'],
    ['divide-slate-100', 'divide-border'],
    ['bg-indigo-600', 'bg-primary'],
    ['hover:bg-indigo-700', 'hover:bg-primary-600'],
    ['hover:bg-indigo-50', 'hover:bg-primary/10'],
    ['hover:text-indigo-600', 'hover:text-primary'],
    ['bg-indigo-50', 'bg-primary/10'],
    ['text-indigo-700', 'text-primary'],
    ['text-indigo-600', 'text-primary'],
    ['text-indigo-500', 'text-primary'],
    ['border-indigo-200', 'border-primary/30'],
    ['ring-indigo-500', 'ring-ring'],
    ['bg-emerald-50', 'bg-success/10'],
    ['hover:bg-emerald-50', 'hover:bg-success/10'],
    ['hover:text-emerald-700', 'hover:text-success'],
    ['text-emerald-700', 'text-success'],
    ['text-emerald-600', 'text-success'],
    ['border-emerald-200', 'border-success/30'],
    ['bg-emerald-500', 'bg-success'],
    ['bg-rose-50', 'bg-destructive/10'],
    ['hover:bg-rose-50', 'hover:bg-destructive/10'],
    ['hover:text-rose-600', 'hover:text-destructive'],
    ['text-rose-700', 'text-destructive'],
    ['text-rose-600', 'text-destructive'],
    ['border-rose-200', 'border-destructive/30'],
    ['bg-rose-600', 'bg-destructive'],
    ['hover:bg-rose-700', 'hover:bg-destructive/90'],
    ['bg-amber-50', 'bg-warning/10'],
    ['text-amber-800', 'text-warning'],
    ['text-amber-700', 'text-warning'],
    ['text-amber-600', 'text-warning'],
    ['border-amber-200', 'border-warning/30'],
    ['bg-blue-50', 'bg-info/10'],
    ['text-blue-700', 'text-info'],
    ['border-blue-200', 'border-info/30'],
    ['shadow-sm', 'shadow-card'],
];

const headerPattern =
    /<div>\s*<h1 class="text-xl font-semibold tracking-tight text-slate-900">([\s\S]*?)<\/h1>\s*(?:<p class="text-sm text-slate-500">([\s\S]*?)<\/p>\s*)?<\/div>/g;

let transformed = [];
let tokenized = [];

for (const file of walk(root)) {
    if (!file.endsWith('.vue')) continue;

    const relative = path.relative(root, file).replace(/\\/g, '/');
    if (relative.startsWith('Cbt/Cetak/')) continue;

    let source = fs.readFileSync(file, 'utf8');
    const original = source;

    const usesLayout = source.includes('AdminLayout');
    let replacedHeader = false;

    if (usesLayout) {
        source = source.replace(headerPattern, (match, title, description) => {
            replacedHeader = true;
            const titlePlain = !/[{<]/.test(title);
            const descriptionPlain = description === undefined || !/[{<]/.test(description);

            if (titlePlain && descriptionPlain) {
                const attributes = [`title="${title.trim()}"`];
                if (description) {
                    attributes.push(`description="${description.replace(/\s+/g, ' ').trim()}"`);
                }
                return `<PageHeader ${attributes.join(' ')} />`;
            }

            const descriptionSlot = description
                ? `\n                    <template #description>${description.trim()}</template>\n                `
                : '';

            return `<PageHeader>\n                    <template #title>${title.trim()}</template>${descriptionSlot}\n                </PageHeader>`;
        });
    }

    if (usesLayout) {
        for (const [from, to] of tokenMap) {
            source = source.split(from).join(to);
        }
    }

    if (replacedHeader && !source.includes("import PageHeader from '@/Components/PageHeader.vue';")) {
        source = source.replace(
            /(import AdminLayout from '@\/Layouts\/AdminLayout\.vue';\n)/,
            `$1import PageHeader from '@/Components/PageHeader.vue';\n`,
        );
    }

    if (source !== original) {
        fs.writeFileSync(file, source);
        if (replacedHeader) transformed.push(relative);
        else tokenized.push(relative);
    }
}

console.log(JSON.stringify({ header: transformed.length, tokensOnly: tokenized.length, files: transformed }, null, 2));
