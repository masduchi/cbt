import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import globals from 'globals';

export default [
    {
        ignores: ['public/build/**', 'vendor/**', 'node_modules/**', 'storage/**', 'bootstrap/cache/**'],
    },
    js.configs.recommended,
    ...pluginVue.configs['flat/essential'],
    {
        languageOptions: {
            ecmaVersion: 2023,
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node,
            },
        },
        rules: {
            'no-unused-vars': ['warn', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
            // Halaman Inertia memakai nama berkas satu kata (Index, Show, Kerjakan).
            'vue/multi-word-component-names': 'off',
        },
    },
];
